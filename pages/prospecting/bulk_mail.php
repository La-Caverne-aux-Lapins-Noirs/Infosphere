<?php
// Mailing individuel : seulement les prospects actifs de toutes les campagnes.
require_once (__DIR__.'/campaign_tools.php');
require_once (__DIR__.'/../../tools/bulk_mail_queue.php');

function prospect_bulk_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function prospect_bulk_targets($id_school, $kind = 'prospect')
{
    $id_school = (int)$id_school;
    if ($id_school <= 0) return [];
    if ($kind === 'organization')
    {
        $rows = db_select_all(" DISTINCT organization.id, organization.mail,
            COALESCE(NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), organization.codename) AS label
            FROM organization
            WHERE organization.deleted IS NULL
            ORDER BY label");
        $usable = [];
        foreach ($rows as $row)
        {
            $address = trim((string)($row['mail'] ?? ''));
            if (filter_var($address, FILTER_VALIDATE_EMAIL))
                $usable[(int)$row['id']] = $row + ['first_name' => $row['label'], 'family_name' => ''];
        }
        return $usable;
    }
    $rows = db_select_all("user.id, user.first_name, user.family_name, user.mail,
        user.registration_date, user.target_class, user.target_entry
        FROM user
        WHERE user.profile_status = 'prospect' AND user.deleted IS NULL
          AND EXISTS (
            SELECT 1 FROM user_school us
            WHERE us.id_user = user.id AND us.id_school = $id_school
          )
          AND (user.password IS NULL OR user.password = '')
          AND NOT EXISTS (
            SELECT 1 FROM prospection p
            JOIN action a ON a.id = p.id_action
            WHERE p.id_user = user.id AND a.consequence IN ('lost', 'transformed')
          )
        ORDER BY user.family_name, user.first_name, user.id");
    $usable = [];
    foreach ($rows as $row)
    {
        $address = trim((string)($row['mail'] ?? ''));
        if (filter_var($address, FILTER_VALIDATE_EMAIL))
            $usable[(int)$row['id']] = $row;
    }
    return $usable;
}

// Refuser tout HTML actif, même si la source vient de contenteditable.
function prospect_bulk_clean_html($source)
{
    $source = trim((string)$source);
    if (!class_exists('DOMDocument'))
        return '<p>'.nl2br(prospect_bulk_escape(strip_tags($source))).'</p>';
    $old = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="utf-8"?><div id="mail-root">'.$source.'</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    $allowed = ['div','p','br','strong','b','em','i','u','ul','ol','li','a','h2','h3','blockquote'];
    $walk = function($node) use (&$walk, $allowed, $dom) {
        for ($child = $node->firstChild; $child !== null; )
        {
            $next = $child->nextSibling;
            if ($child->nodeType === XML_COMMENT_NODE) $node->removeChild($child);
            else if ($child->nodeType === XML_ELEMENT_NODE)
            {
                $tag = strtolower($child->nodeName);
                if (!in_array($tag, $allowed, true))
                {
                    // Elements potentiellement actifs n'ont aucun contenu utile à conserver.
                    if (in_array($tag, ['script','style','iframe','object','svg','math','form','input','video','audio'], true))
                        $node->removeChild($child);
                    else
                    {
                        $walk($child);
                        while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                        $node->removeChild($child);
                    }
                }
                else
                {
                    $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
                    while ($child->attributes->length) $child->removeAttributeNode($child->attributes->item(0));
                    if ($tag === 'a' && preg_match('~^https?://~i', $href))
                        $child->setAttribute('href', $href);
                    $walk($child);
                }
            }
            $child = $next;
        }
    };
    $root = $dom->getElementById('mail-root');
    if (!$root) return '';
    $walk($root);
    $result = '';
    foreach ($root->childNodes as $child) $result .= $dom->saveHTML($child);
    return $result;
}

function prospect_bulk_token()
{
    global $Configuration, $User;
    $secret = (string)($Configuration->Properties['mailgun_key'] ?? '');
    if ($secret === '') return '';
    return hash_hmac('sha256', 'prospect-bulk:'.(int)$User['id'].':'.date('Y-m-d'), $secret);
}

// Les brouillons sont communs aux membres autorises d'un meme etablissement.
// La selection d'ecole est validee par le serveur, jamais par le chemin demande.
function prospect_bulk_available_schools()
{
    global $User;
    $id = (int)($User['id'] ?? 0);
    if ($id <= 0) return [];
    $rows = db_select_all("school.id, school.codename, user_school.authority
        FROM user_school JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id AND school.deleted IS NULL
        ORDER BY school.codename");
    $schools = [];
    foreach ($rows as $row)
    {
        $role = strtoupper((string)($row['authority'] ?? ''));
        if (!in_array($role, ['1', '2', '3', 'DIRECTOR', 'SECRETARIAT', 'COMMERCIAL'], true))
            continue;
        $schools[(int)$row['id']] = $row['codename'];
    }
    return $schools;
}

function prospect_bulk_draft_directory()
{
    global $prospect_bulk_school_id;
    if ($prospect_bulk_school_id <= 0) return null;
    return dirname(__DIR__, 2).'/dres/prospect_mail_drafts/school_'.$prospect_bulk_school_id;
}

function prospect_bulk_personal_draft_directory()
{
    global $User;
    return dirname(__DIR__, 2).'/dres/prospect_mail_drafts/user_'.(int)$User['id'];
}

// Ancien format : lecture exclusivement pour permettre une importation volontaire.
function prospect_bulk_read_personal_draft($id)
{
    if (!is_string($id) || !preg_match('/\A[a-f0-9]{24}\z/D', $id)) return null;
    return prospect_bulk_read_file(prospect_bulk_personal_draft_directory().'/'.$id.'.php');
}

function prospect_bulk_read_file($path)
{
    if ($path === null || !is_file($path)) return null;
    $data = file_get_contents($path);
    $prefix = "<?php http_response_code(404); exit; ?>\n";
    if ($data === false || !str_starts_with($data, $prefix)) return null;
    $draft = json_decode(substr($data, strlen($prefix)), true);
    return is_array($draft) ? $draft : null;
}

function prospect_bulk_personal_drafts()
{
    $out = [];
    foreach (glob(prospect_bulk_personal_draft_directory().'/*.php') ?: [] as $path)
    {
        $id = basename($path, '.php');
        $record = prospect_bulk_read_personal_draft($id);
        if ($record !== null) $out[$id] = $record;
    }
    return $out;
}

function prospect_bulk_draft_filename($id)
{
    if (!is_string($id) || !preg_match('/\A[a-f0-9]{24}\z/D', $id)) return null;
    return prospect_bulk_draft_directory() === null ? null : prospect_bulk_draft_directory().'/'.$id.'.php';
}

function prospect_bulk_read_draft($id)
{
    return prospect_bulk_read_file(prospect_bulk_draft_filename($id));
}

function prospect_bulk_list_drafts()
{
    $out = [];
    foreach (glob(prospect_bulk_draft_directory().'/*.php') ?: [] as $path)
    {
        $id = basename($path, '.php');
        $draft = prospect_bulk_read_draft($id);
        if ($draft !== null) $out[$id] = $draft;
    }
    uasort($out, fn($a, $b) => strcmp((string)($b['updated'] ?? ''), (string)($a['updated'] ?? '')));
    return $out;
}

function prospect_bulk_write_draft($id, $draft)
{
    $directory = prospect_bulk_draft_directory();
    if ($directory === null) return false;
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) return false;
    // Même si la configuration Apache change, un fichier demandé par URL ne divulgue rien.
    $payload = "<?php http_response_code(404); exit; ?>\n"
        .json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $file = prospect_bulk_draft_filename($id);
    if ($file === null) return false;
    $tmp = tempnam($directory, '.draft-');
    if ($tmp === false) return false;
    $ok = file_put_contents($tmp, $payload, LOCK_EX) !== false;
    if ($ok) $ok = chmod($tmp, 0600) && rename($tmp, $file);
    if (!$ok) @unlink($tmp);
    return $ok;
}

$prospect_bulk_schools = prospect_bulk_available_schools();
$prospect_bulk_school_id = (int)($_POST['id_school'] ?? $_GET['id_school'] ?? 0);
if (!$prospect_bulk_school_id && count($prospect_bulk_schools) === 1)
    $prospect_bulk_school_id = (int)array_key_first($prospect_bulk_schools);
if (!isset($prospect_bulk_schools[$prospect_bulk_school_id]))
{
    if ($prospect_bulk_school_id || !$prospect_bulk_schools)
        echo '<p>École inaccessible pour la prospection.</p>';
    else
    {
        echo '<div style="padding:15px"><h2>Choisir une école</h2>';
        foreach ($prospect_bulk_schools as $id => $name)
            echo '<p><a href="?p=ProspectingMenu&amp;bulk_mail=1&amp;id_school='.(int)$id.'">'
                .prospect_bulk_escape($name).'</a></p>';
        echo '</div>';
    }
    return;
}

$prospect_bulk_kind = (string)($_POST['target_kind'] ?? $_GET['target_kind'] ?? 'prospect');
if (!in_array($prospect_bulk_kind, ['prospect', 'organization'], true)) $prospect_bulk_kind = 'prospect';
$targets = prospect_bulk_targets($prospect_bulk_school_id, $prospect_bulk_kind);
$errors = [];
$notice = '';
$sent = 0;
$draft_id = (string)($_POST['draft_id'] ?? $_GET['draft'] ?? '');
$draft = prospect_bulk_read_draft($draft_id);
if ($draft !== null && ($draft['target_kind'] ?? 'prospect') !== $prospect_bulk_kind) $draft = null;
$subject = (string)($_POST['subject'] ?? ($draft['subject'] ?? ''));
$content = (string)($_POST['content'] ?? ($draft['content'] ?? ''));
$draft_name = (string)($_POST['draft_name'] ?? ($draft['name'] ?? ''));
$requested = $_POST['recipients'] ?? ($draft['recipients'] ?? []);
if (!is_array($requested)) $requested = [];
$selected = [];
foreach ($requested as $id)
    if (ctype_digit((string)$id) && isset($targets[(int)$id])) $selected[(int)$id] = $targets[(int)$id];
$draft_action = (string)($_POST['draft_action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $draft_action !== '')
{
    if (prospect_bulk_token() === '' || !hash_equals(prospect_bulk_token(), (string)($_POST['csrf'] ?? '')))
        $errors[] = 'Jeton de protection invalide. Recharge la page.';
    if ($draft_action === 'save')
    {
        if (trim($draft_name) === '' || strlen($draft_name) > 120)
            $errors[] = 'Donne un nom au brouillon (120 caractères maximum).';
        if (strlen($subject) > 250 || strlen($content) > 60000)
            $errors[] = 'Le brouillon est trop volumineux.';
        if (!$errors)
        {
            if ($draft === null) $draft_id = bin2hex(random_bytes(12));
            $record = ['name' => trim($draft_name), 'subject' => $subject,
                'content' => prospect_bulk_clean_html($content),
                'recipients' => array_keys($selected), 'target_kind' => $prospect_bulk_kind, 'updated' => date('c')];
            if (!prospect_bulk_write_draft($draft_id, $record))
                $errors[] = 'Impossible d’enregistrer le brouillon sur le disque.';
            else { $draft = $record; $notice = 'Brouillon enregistré.'; }
        }
    }
    elseif ($draft_action === 'import')
    {
        $personal_id = (string)($_POST['personal_draft_id'] ?? '');
        $personal = prospect_bulk_read_personal_draft($personal_id);
        if ($personal === null) $errors[] = 'Brouillon personnel introuvable.';
        if (!$errors)
        {
            $new_id = bin2hex(random_bytes(12));
            $personal['updated'] = date('c');
            $personal['imported_by'] = (int)($User['id'] ?? 0);
            if (!prospect_bulk_write_draft($new_id, $personal))
                $errors[] = 'Impossible d’importer le brouillon.';
            else
            {
                $draft_id = $new_id; $draft = $personal;
                $subject = (string)($draft['subject'] ?? '');
                $content = (string)($draft['content'] ?? '');
                $draft_name = (string)($draft['name'] ?? '');
                $requested = $draft['recipients'] ?? [];
                $selected = [];
                foreach ($requested as $recipient)
                    if (ctype_digit((string)$recipient) && isset($targets[(int)$recipient]))
                        $selected[(int)$recipient] = $targets[(int)$recipient];
                $notice = 'Brouillon importé dans l’école. L’original personnel est conservé.';
            }
        }
    }
    elseif ($draft_action === 'delete')
    {
        $file = prospect_bulk_draft_filename($draft_id);
        if ($draft !== null && $file !== null && @unlink($file))
        {
            $draft_id = ''; $draft = null; $draft_name = ''; $subject = ''; $content = '';
            $requested = []; $selected = []; $notice = 'Brouillon supprimé.';
        }
        else $errors[] = 'Impossible de supprimer ce brouillon.';
    }
    else $errors[] = 'Action inconnue.';
}
$queued_mail_status = [];
foreach (glob(bulk_mail_queue_dir().'/*.php') ?: [] as $queue_path)
{
    $queue_data = json_decode((string)preg_replace('/^<\?php[^\n]*\n/', '', (string)@file_get_contents($queue_path)), true);
    if (!is_array($queue_data) || (int)($queue_data['school'] ?? 0) !== $prospect_bulk_school_id) continue;
    $queued_mail_status[] = [
        'id' => basename($queue_path, '.php'),
        'subject' => (string)($queue_data['subject'] ?? ''),
        'created' => (int)($queue_data['created'] ?? 0),
        'kind' => (string)($queue_data['kind'] ?? 'prospect'),
        'recipients' => array_values(array_filter($queue_data['recipients'] ?? [], 'is_string')),
        'next_at' => (int)($queue_data['next_at'] ?? 0),
        'history_pending' => count($queue_data['history_pending'] ?? []),
        'pending' => count($queue_data['recipients'] ?? []),
        'sent' => count($queue_data['sent'] ?? []),
        'failed' => count($queue_data['failed'] ?? []),
        'uncertain' => count($queue_data['uncertain'] ?? []),
    ];
}
usort($queued_mail_status, function ($a, $b) { return $b['created'] <=> $a['created']; });
$drafts = prospect_bulk_list_drafts();
$personal_drafts = prospect_bulk_personal_drafts();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $draft_action === '')
{
    if (prospect_bulk_token() === '' || !hash_equals(prospect_bulk_token(), (string)($_POST['csrf'] ?? '')))
        $errors[] = 'Jeton de protection invalide. Recharge la page.';
    // Revalider auprès de la BDD pour l’école courante, sans se fier aux cases postées.
    $targets = prospect_bulk_targets($prospect_bulk_school_id, $prospect_bulk_kind);
    $selected = [];
    foreach ($requested as $id)
        if (ctype_digit((string)$id) && isset($targets[(int)$id]))
            $selected[(int)$id] = $targets[(int)$id];
    if (count($requested) !== count($selected))
        $errors[] = 'La sélection contient des prospects non éligibles pour cette école : recharge la page.';
    if (!$selected)
        $errors[] = 'Sélectionne au moins un destinataire.';
    if (trim($subject) === '' || strlen($subject) > 250)
        $errors[] = 'Objet manquant ou trop long (250 caractères maximum).';
    if (strlen($content) > 60000 || trim(strip_tags($content)) === '')
        $errors[] = 'Le contenu est vide ou trop volumineux.';
    if (($_POST['compliance'] ?? '') !== 'yes')
        $errors[] = 'Vérifie la base de contact et les modalités de désinscription avant l’envoi.';
    if ((string)($_POST['confirm'] ?? '') !== 'yes')
        $errors[] = 'L’envoi doit être confirmé.';
    if (!$errors)
    {
        $html = prospect_bulk_clean_html($content);
        $plain = trim(html_entity_decode(strip_tags(str_replace(['</p>', '</div>', '<br>', '<br/>', '<br />', '</li>'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $addresses = [];
        $recipients = [];
        foreach ($selected as $entry)
        {
            $address = strtolower(trim($entry['mail']));
            if (isset($addresses[$address])) continue;
            $addresses[$address] = true;
            $recipients[] = $address;
        }
        $job = bulk_mail_enqueue($prospect_bulk_school_id, $prospect_bulk_kind,
            $subject, $plain, $html, $recipients, (int)$User['id']);
        if ($job === false)
            $errors[] = 'Impossible de mettre les courriels en file d’attente.';
        else
            $notice = count($recipients).' courriel(s) programmé(s). Référence : '.$job.'. Envoi progressif par Albedo.';
        // Une nouvelle action volontaire reste nécessaire pour un autre lot.
        $selected = [];
    }
}
?>
<style>
.prospect-bulk {padding:10px 14px;max-width:none;margin:0 auto;color:inherit;
  height:auto;box-sizing:border-box;display:flex;flex-direction:column;min-height:0}
.prospect-bulk-title {display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap;flex:none}
.prospect-bulk-title h2 {margin:0 0 3px}
.prospect-bulk-form {display:flex;flex-direction:column;height:80dvh;min-height:560px;flex:none}
.prospect-bulk-grid {display:grid;grid-template-columns:minmax(240px,1fr) minmax(310px,1.22fr) minmax(300px,1.1fr);
  gap:12px;flex:1;min-height:0;align-items:stretch}
.prospect-bulk-card {border:1px solid rgba(170,170,170,.35);background:rgba(255,255,255,.055);
  border-radius:10px;padding:11px;min-width:0;min-height:0;display:flex;flex-direction:column;overflow:hidden}
.prospect-bulk-card h3 {margin:0 0 7px;flex:none}
.prospect-bulk-card-scroll {overflow:auto;min-height:0;flex:1;scrollbar-gutter:stable}
.prospect-bulk-list {overflow:auto;min-height:0;flex:1;border:1px solid #7775;border-radius:7px;margin-top:8px}
.prospect-bulk-line {display:flex;gap:9px;align-items:center;padding:7px 9px;border-bottom:1px solid #8883;cursor:pointer}
.prospect-bulk-line:hover {background:rgba(255,255,255,.09)}
.prospect-bulk-line small {opacity:.7;margin-left:auto}
.prospect-bulk-toolbar {display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:7px 0}
.prospect-bulk button {cursor:pointer;border:1px solid #607066;border-radius:6px;padding:6px 9px;
  background:#24352b;color:#f3f8f4;box-shadow:none;text-shadow:none}
.prospect-bulk button:hover:not(:disabled),.prospect-bulk button:focus-visible {
  background:#355443;border-color:#91b5a0;color:#fff}
.prospect-bulk button:focus-visible {outline:2px solid #a8d7b5;outline-offset:2px}
.prospect-bulk button:disabled {background:#28302b;color:#b4bdb6;opacity:.6;cursor:default}
.prospect-bulk #prospect-bulk-send,.prospect-bulk #prospect-bulk-save {
  background:#17652d;border-color:#348f4e;color:#fff;font-weight:600}
.prospect-bulk #prospect-bulk-send:hover:not(:disabled),
.prospect-bulk #prospect-bulk-save:hover:not(:disabled) {background:#21813b;color:#fff}
.prospect-bulk #prospect-bulk-delete {background:#542525;border-color:#a24a4a;color:#fff}
.prospect-bulk #prospect-bulk-delete:hover:not(:disabled) {background:#733333;color:#fff}
.prospect-bulk select {background:#1d2921;border:1px solid #657568;border-radius:6px;
  color:#f3f8f4;padding:5px 7px}
.prospect-bulk select option {background:#1d2921;color:#f3f8f4}
.prospect-bulk input[type=text] {width:100%;box-sizing:border-box;padding:7px;margin:5px 0}
.prospect-bulk-editor {box-sizing:border-box;min-height:180px;height:100%;overflow:auto;border:1px solid #888;
  border-radius:8px;padding:13px;background:#fff;color:#222;}
.prospect-bulk-editor div { color: inherit; }
.prospect-bulk-preview {width:100%;flex:1;min-height:0;border:1px solid #888;border-radius:8px;background:white}
.prospect-bulk-note {font-size:.86em;opacity:.8;margin:6px 0}
.prospect-bulk-actions {flex:none;border-top:1px solid #8885;padding-top:9px;margin-top:9px}
.prospect-bulk-drafts {display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:8px}
.prospect-bulk-drafts select {max-width:230px}
@media(max-width:1100px) {
 .prospect-bulk {height:auto;min-height:0;overflow:visible}
 .prospect-bulk-grid {grid-template-columns:1fr}
 .prospect-bulk-card {min-height:360px}
 .prospect-bulk-list {max-height:55vh}
 .prospect-bulk-preview {min-height:300px}
 .prospect-bulk-editor {min-height:230px}
 .prospect-bulk-form {height:auto;min-height:0}
}
 .prospect-bulk-queue {flex:none;margin:16px 0;padding:10px;border:1px solid #888;border-radius:6px}
 .prospect-bulk-queue-job {margin:8px 0;padding:8px;border:1px solid #aaa;border-radius:5px}
 .prospect-bulk-queue summary {cursor:pointer;font-weight:bold}
 .prospect-bulk-queue-addresses {max-height:200px;overflow:auto;overflow-wrap:anywhere}
</style>
<div class="prospect-bulk">
  <div class="prospect-bulk-title"><h2>Courriel collectif</h2>
    <a href="?p=ProspectingMenu">← Retour à la prospection</a></div>
  <?php if ($notice): ?><p role="status"><?=prospect_bulk_escape($notice)?></p><?php endif; ?>
  <?php if ($sent): ?><p role="status"><?= $sent ?> message(s) accepté(s) par Mailgun (distribution non garantie).</p><?php endif; ?>
  <?php foreach ($errors as $error): ?><p role="alert" style="color:#ff7777"><?=prospect_bulk_escape($error)?></p><?php endforeach; ?>
  <div class="prospect-bulk-drafts">
    <label for="prospect-bulk-school">École :</label>
    <select id="prospect-bulk-school">
    <?php foreach ($prospect_bulk_schools as $school_id => $school_name): ?>
      <option value="<?=(int)$school_id?>" <?=$school_id===$prospect_bulk_school_id?'selected':''?>><?=prospect_bulk_escape($school_name)?></option>
    <?php endforeach; ?>
    </select>
    <label for="prospect-bulk-kind">Destinataires :</label>
    <select id="prospect-bulk-kind"><option value="prospect" <?=$prospect_bulk_kind==='prospect'?'selected':''?>>Prospects</option><option value="organization" <?=$prospect_bulk_kind==='organization'?'selected':''?>>Organisations</option></select>
    <label for="prospect-bulk-draft-list">Brouillons de l’école :</label>
    <select id="prospect-bulk-draft-list"><option value="">Choisir un brouillon…</option>
      <?php foreach ($drafts as $id => $saved): ?>
      <option value="<?=prospect_bulk_escape($id)?>" <?=$id===$draft_id?'selected':''?>>
        <?=prospect_bulk_escape($saved['name'] ?? 'Sans nom')?>
      </option><?php endforeach; ?>
    </select>
    <button type="button" id="prospect-bulk-draft-load">Charger</button>
    <a href="?p=ProspectingMenu&amp;bulk_mail=1&amp;id_school=<?=$prospect_bulk_school_id?>&amp;target_kind=<?=prospect_bulk_escape($prospect_bulk_kind)?>">Nouveau courriel</a>
    <?php if ($personal_drafts): ?>
      <select id="prospect-bulk-personal"><option value="">Ancien brouillon personnel…</option>
      <?php foreach ($personal_drafts as $id => $old): ?>
        <option value="<?=prospect_bulk_escape($id)?>"><?=prospect_bulk_escape($old['name'] ?? 'Sans nom')?></option>
      <?php endforeach; ?></select>
      <button type="button" id="prospect-bulk-import">Importer dans l’école</button>
    <?php endif; ?>
  </div>
  <form id="prospect-bulk-form" class="prospect-bulk-form" method="post" action="?p=ProspectingMenu&amp;bulk_mail=1&amp;id_school=<?=$prospect_bulk_school_id?>&amp;target_kind=<?=prospect_bulk_escape($prospect_bulk_kind)?>">
    <input type="hidden" name="id_school" value="<?=$prospect_bulk_school_id?>" />
    <input type="hidden" name="target_kind" value="<?=prospect_bulk_escape($prospect_bulk_kind)?>" />
    <input type="hidden" name="personal_draft_id" id="prospect-bulk-personal-id" value="" />
    <input type="hidden" name="csrf" value="<?=prospect_bulk_escape(prospect_bulk_token())?>" />
    <input type="hidden" name="confirm" value="no" />
    <input type="hidden" name="draft_id" value="<?=prospect_bulk_escape($draft !== null ? $draft_id : '')?>" />
    <input type="hidden" name="content" id="prospect-bulk-content" value="<?=prospect_bulk_escape($content)?>" />
    <div class="prospect-bulk-grid">
      <section class="prospect-bulk-card">
        <h3>Destinataires <small id="prospect-bulk-count">(0)</small></h3>
        <p class="prospect-bulk-note"><?=$prospect_bulk_kind==='prospect'?'Prospects actifs de cette école, toutes campagnes confondues.':'Toutes les organisations dotés d’un courriel.'?><br />L'envoi est progressif (un mail toutes les 1m30 en moyenne)</p>
        <input type="text" id="prospect-bulk-filter" placeholder="Rechercher un nom ou une adresse…" aria-label="Filtrer les prospects" />
        <div class="prospect-bulk-toolbar"><button type="button" id="prospect-bulk-all">Tout sélectionner</button><button type="button" id="prospect-bulk-none">Tout désélectionner</button></div>
        <div class="prospect-bulk-list" id="prospect-bulk-list">
          <?php foreach ($targets as $id => $person): ?>
            <label class="prospect-bulk-line"><input type="checkbox" name="recipients[]" value="<?=$id?>" <?=isset($selected[$id])?'checked':''?> />
              <span><?=prospect_bulk_escape(trim($person['first_name'].' '.$person['family_name']))?><small style="display:block"><?=prospect_bulk_escape($person['mail'])?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>
      <section class="prospect-bulk-card">
        <h3>Rédaction</h3>
        <div class="prospect-bulk-card-scroll" style="display:flex;flex-direction:column;gap:5px">
          <label for="prospect-bulk-subject">Objet</label>
          <input id="prospect-bulk-subject" name="subject" type="text" maxlength="250" value="<?=prospect_bulk_escape($subject)?>" />
          <label for="prospect-bulk-draft-name">Nom du brouillon</label>
          <input id="prospect-bulk-draft-name" name="draft_name" type="text" maxlength="120" value="<?=prospect_bulk_escape($draft_name)?>" placeholder="Ex. Signal perdu – invitation" />
          <div class="prospect-bulk-toolbar" aria-label="Mise en forme">
            <button type="button" data-command="bold"><b>Gras</b></button>
            <button type="button" data-command="italic"><i>Italique</i></button>
            <button type="button" data-command="insertUnorderedList">Liste</button>
            <button type="button" id="prospect-bulk-link">Lien</button>
          </div>
          <div id="prospect-bulk-editor" class="prospect-bulk-editor" contenteditable="true" role="textbox" aria-multiline="true"></div>
        </div>
        <div class="prospect-bulk-actions">
          <div class="prospect-bulk-toolbar">
            <button type="submit" name="draft_action" value="save" id="prospect-bulk-save">Enregistrer le brouillon</button>
            <?php if ($draft !== null): ?><button type="submit" name="draft_action" value="delete" id="prospect-bulk-delete">Supprimer le brouillon</button><?php endif; ?>
          </div>
          <label style="display:block;font-size:.88em;margin:6px 0"><input type="checkbox" name="compliance" value="yes" /> J’ai vérifié la base de contact et le moyen de désinscription.</label>
          <button type="submit" id="prospect-bulk-send">Envoyer aux destinataires sélectionnés</button>
        </div>
      </section>
      <section class="prospect-bulk-card">
        <h3>Aperçu du courriel</h3>
        <p class="prospect-bulk-note">Le rendu se met à jour pendant la rédaction. Aucun envoi depuis l’aperçu.</p>
        <iframe id="prospect-bulk-preview" class="prospect-bulk-preview" sandbox="" title="Aperçu du courrier"></iframe>
        <p class="prospect-bulk-note">Version HTML et texte envoyées séparément à chaque destinataire.</p>
      </section>
    </div>
  </form>
  <div id="prospect-bulk-queue-area" aria-live="off">
  <?php if ($queued_mail_status): ?>
    <details class="prospect-bulk-queue" open>
      <summary>File des courriels de cette école —
        <?=array_sum(array_column($queued_mail_status, 'pending'))?> en attente</summary>
      <p>Adresses encore en attente d'expédition par le worker. La liste se met à jour automatiquement. Une acceptation par Mailgun ne garantit pas la livraison.</p>
      <?php foreach ($queued_mail_status as $queue): ?>
        <details class="prospect-bulk-queue-job" <?=$queue['pending'] ? 'open' : ''?>>
          <summary>
            <?=prospect_bulk_escape($queue['subject'] ?: '(Sans objet)')?>
            (<?=prospect_bulk_escape($queue['kind'])?>) —
            <?=$queue['pending']?> en attente,
            <?=$queue['sent']?> accepté(s),
            <?=$queue['failed']?> échec(s),
            <?=$queue['uncertain']?> indéterminé(s)
          </summary>
          <p>Campagne <?=prospect_bulk_escape($queue['id'])?>
          <?php if ($queue['created']): ?> —
            créée le <?=date('d/m/Y H:i', $queue['created'])?>
          <?php endif; ?>
          <?php if ($queue['history_pending']): ?> —
            <?=$queue['history_pending']?> historique(s) à enregistrer
          <?php endif; ?></p>
          <?php if ($queue['recipients']): ?>
            <ul class="prospect-bulk-queue-addresses">
              <?php foreach ($queue['recipients'] as $mail): ?>
                <li><?=prospect_bulk_escape($mail)?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>Aucun courriel en attente pour cette campagne.</p>
          <?php endif; ?>
        </details>
      <?php endforeach; ?>
    </details>
  <?php else: ?>
    <p>Aucune campagne de courriel dans la file de cette école.</p>
  <?php endif; ?>
  </div>
</div>
<script>
(function(){
 const form=document.getElementById('prospect-bulk-form'), list=document.getElementById('prospect-bulk-list');
 const checks=()=>Array.from(list.querySelectorAll('input[type=checkbox]'));
 const count=()=>document.getElementById('prospect-bulk-count').textContent='('+checks().filter(x=>x.checked).length+' sélectionnés)';
 let last=null;
 list.addEventListener('click',e=>{
   const c=e.target.closest('input[type=checkbox]'); if(!c)return;
   const visible=checks().filter(x=>x.closest('label').style.display!=='none');
   if(e.shiftKey && last && visible.includes(last)){
     const a=visible.indexOf(last),b=visible.indexOf(c);
     visible.slice(Math.min(a,b),Math.max(a,b)+1).forEach(x=>x.checked=c.checked);
   }
   last=c;count();
 });
 document.getElementById('prospect-bulk-all').onclick=()=>{checks().filter(x=>x.closest('label').style.display!=='none').forEach(x=>x.checked=true);count()};
 document.getElementById('prospect-bulk-none').onclick=()=>{checks().forEach(x=>x.checked=false);count()};
 document.getElementById('prospect-bulk-filter').oninput=e=>{
   const q=e.target.value.toLocaleLowerCase();
   checks().forEach(x=>x.closest('label').style.display=x.closest('label').textContent.toLocaleLowerCase().includes(q)?'':'none');
 };
 function targetUrl(school,kind){return '?p=ProspectingMenu&bulk_mail=1&id_school='+encodeURIComponent(school)+'&target_kind='+encodeURIComponent(kind)}
 document.getElementById('prospect-bulk-school').addEventListener('change',e=>{location.href=targetUrl(e.target.value,document.getElementById('prospect-bulk-kind').value)});
 document.getElementById('prospect-bulk-kind').addEventListener('change',e=>{location.href=targetUrl(form.elements.id_school.value,e.target.value)});
 const importer=document.getElementById('prospect-bulk-import');
 if(importer) importer.addEventListener('click',()=>{
   const id=document.getElementById('prospect-bulk-personal').value;
   if(!id || !confirm('Copier ce brouillon personnel vers l’école choisie ?'))return;
   form.elements.personal_draft_id.value=id;
   const button=document.createElement('button');button.type='submit';button.name='draft_action';button.value='import';button.hidden=true;
   form.appendChild(button);button.click();button.remove();
 });
 // Refresh only the queue; preserve the draft, checked recipients and scroll position.
 const queueArea=document.getElementById('prospect-bulk-queue-area');
 let queueUpdating=false;
 async function refreshQueue(){
   if(!queueArea || document.hidden || queueUpdating)return;
   queueUpdating=true;
   try{
     const response=await fetch(location.href,{credentials:'same-origin',cache:'no-store',
       headers:{'X-Requested-With':'XMLHttpRequest'}});
     if(!response.ok)return;
     const doc=new DOMParser().parseFromString(await response.text(),'text/html');
     const updated=doc.getElementById('prospect-bulk-queue-area');
     if(!updated)return;
     const oldDetails=queueArea.querySelectorAll('details');
     const states=new Map();
     oldDetails.forEach((node,i)=>{
       const key=node.classList.contains('prospect-bulk-queue-job')
         ? node.querySelector('p')?.textContent.trim().split(' — ')[0] : 'main';
       states.set(key||String(i),node.open);
     });
     updated.querySelectorAll('details').forEach((node,i)=>{
       const key=node.classList.contains('prospect-bulk-queue-job')
         ? node.querySelector('p')?.textContent.trim().split(' — ')[0] : 'main';
       if(states.has(key||String(i)))node.open=states.get(key||String(i));
     });
     queueArea.replaceChildren(...Array.from(updated.childNodes));
   }catch(_error){/* Transient refresh failures must not affect the editor. */}
   finally{queueUpdating=false;}
 }
 setInterval(refreshQueue,60000);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshQueue()});
 const editor=document.getElementById('prospect-bulk-editor');
 editor.innerHTML=document.getElementById('prospect-bulk-content').value;
 document.querySelectorAll('[data-command]').forEach(button=>button.onclick=()=>{editor.focus();document.execCommand(button.dataset.command,false,null)});
 document.getElementById('prospect-bulk-link').onclick=()=>{const url=prompt('Adresse du lien (https://…)');if(url && /^https?:\/\//i.test(url)){editor.focus();document.execCommand('createLink',false,url)}};
 function preview(){const html=editor.innerHTML;document.getElementById('prospect-bulk-preview').srcdoc='<!doctype html><html><head><meta charset="utf-8"><style>body{font:15px/1.6 Arial,sans-serif;color:#24282b;padding:22px;overflow-wrap:anywhere}</style></head><body>'+html+'</body></html>'}
 let previewQueued=false; editor.addEventListener('input',()=>{if(previewQueued)return;previewQueued=true;requestAnimationFrame(()=>{previewQueued=false;preview()})});preview();count();
 document.getElementById('prospect-bulk-draft-load').onclick=()=>{const id=document.getElementById('prospect-bulk-draft-list').value;if(id)location.href='?p=ProspectingMenu&bulk_mail=1&id_school='+encodeURIComponent(form.elements.id_school.value)+'&target_kind='+encodeURIComponent(form.elements.target_kind.value)+'&draft='+encodeURIComponent(id)};
 form.addEventListener('submit',e=>{
   document.getElementById('prospect-bulk-content').value=editor.innerHTML;
   const action=e.submitter?.value || '';
   if(e.submitter?.name==='draft_action'){
     if(action==='delete' && !confirm('Supprimer définitivement ce brouillon ?'))e.preventDefault();
     return;
   }
   const n=checks().filter(x=>x.checked).length;
   if(n<1){e.preventDefault();alert('Sélectionne au moins un destinataire.');return;}
   document.getElementById('prospect-bulk-content').value=editor.innerHTML;
   if(!confirm('Envoyer réellement '+n+' courriel(s) ?')){e.preventDefault();return;}
   form.elements.confirm.value='yes';
   document.getElementById('prospect-bulk-send').disabled=true;
 });
})();
</script>
