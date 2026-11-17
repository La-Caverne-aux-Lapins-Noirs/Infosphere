<?php
// Historique exclusivement sur disque, indexe par identifiants stables.
function evaluation_history_root($activity_id, $team_id)
{
    return __DIR__.'/../dres/evaluations/'.(int)$activity_id.'/'.(int)$team_id;
}

function evaluation_history_save($activity_id, $team_id, $official, $content, $origin)
{
    if ($activity_id <= 0 || $team_id <= 0 || !is_string($content) || $content === '')
        return false;
    $root = evaluation_history_root($activity_id, $team_id);
    $dir = $root.'/history';
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir))
        return false;
    $protected = dirname(dirname($root));
    if (!is_file($protected.'/.htaccess'))
        @file_put_contents($protected.'/.htaccess', "Require all denied\n", LOCK_EX);
    $lock = @fopen($root.'/.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX))
        return false;
    try
    {
        $date = gmdate('Ymd\THis\Z');
        $id = $date.'-'.bin2hex(random_bytes(5));
        $report = evaluation_history_extract_report($content);
        // Les pieces jointes binaires ne sont conservees que dans latest/.
        $metadata = [
            'id' => $id, 'date' => gmdate('c'), 'official' => (bool)$official,
            'origin' => $origin, 'has_report' => $report !== null,
            'archive_sha256' => hash('sha256', $content)
        ];
        $metadata['summary'] = $report !== null ? evaluation_history_model($report) : null;
        if ($metadata['summary'] !== null)
        {
            $metadata['summary']['states'] = evaluation_history_states($metadata['summary']['results']);
            unset($metadata['summary']['results']);
            // Sous verrou : la derniere evaluation complete de cette equipe.
            $previous = evaluation_history_previous_snapshot($dir);
            if ($previous !== null)
            {
                $metadata['comparison'] = evaluation_history_compare(
                    $previous['states'], $metadata['summary']['states']);
                $metadata['previous_id'] = $previous['id'];
            }
        }
        $meta = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($meta === false || file_put_contents($dir.'/'.$id.'.json.tmp', $meta, LOCK_EX) === false)
            return false;
        if ($report !== null && file_put_contents($dir.'/'.$id.'.dab.tmp', $report, LOCK_EX) === false)
        {
            @unlink($dir.'/'.$id.'.json.tmp');
            return false;
        }
        if ($report !== null)
            rename($dir.'/'.$id.'.dab.tmp', $dir.'/'.$id.'.dab');
        rename($dir.'/'.$id.'.json.tmp', $dir.'/'.$id.'.json');
        $latest = $root.'/latest';
        if (!is_dir($latest))
            @mkdir($latest, 0770, true);
        // Remplacement atomique : un seul exemplaire des artefacts transportes.
        if (is_dir($latest) && file_put_contents($latest.'/report.tar.gz.tmp', $content, LOCK_EX) !== false)
        {
            rename($latest.'/report.tar.gz.tmp', $latest.'/report.tar.gz');
            file_put_contents($latest.'/evaluation.json', $meta, LOCK_EX);
        }
        return $id;
    }
    finally { flock($lock, LOCK_UN); fclose($lock); }
}

function evaluation_history_extract_report($content)
{
    $tmp = tempnam(sys_get_temp_dir(), 'evaluation_');
    if (!$tmp) return null;
    file_put_contents($tmp, $content);
    try
    {
        $listing = [];
        $status = 1;
        exec('tar -tzf '.escapeshellarg($tmp).' 2>/dev/null', $listing, $status);
        if ($status !== 0) return null;
        foreach ($listing as $entry)
        {
            // Ne jamais extraire une arborescence depuis une archive distante.
            if (basename($entry) !== 'report.dab' || str_contains($entry, '..'))
                continue;
            $pipe = popen('tar -xOzf '.escapeshellarg($tmp).' -- '.escapeshellarg($entry).' 2>/dev/null', 'r');
            if (!$pipe) return null;
            $report = stream_get_contents($pipe, 2 * 1024 * 1024 + 1);
            $status = pclose($pipe);
            return $status === 0 && strlen($report) <= 2 * 1024 * 1024 ? $report : null;
        }
        return null;
    }
    finally { @unlink($tmp); }
}

function evaluation_history_list($activity_id, $team_id)
{
    $dir = evaluation_history_root($activity_id, $team_id).'/history';
    if (!is_dir($dir)) return [];
    $rows = [];
    foreach (glob($dir.'/*.json') ?: [] as $path)
    {
        $row = json_decode(file_get_contents($path), true);
        if (is_array($row) && isset($row['id']) && preg_match('/^[0-9TZ-]+-[a-f0-9]{10}$/', $row['id']))
            $rows[] = $row;
    }
    usort($rows, fn($a, $b) => strcmp($b['id'], $a['id']));
    return $rows;
}

function evaluation_history_report($activity_id, $team_id, $id)
{
    if (!preg_match('/^\d{8}T\d{6}Z-[a-f0-9]{10}$/D', $id)) return null;
    $file = evaluation_history_root($activity_id, $team_id).'/history/'.$id.'.dab';
    return is_file($file) ? file_get_contents($file) : null;
}

// Dabsic utilise des listes de blocs [ ... ] sous {Exercises ...}.
// Ce lecteur ne cherche que les champs semantiques connus ; il respecte les
// chaines echappees et les imbrications, sans evaluer le contenu du fichier.
function evaluation_history_blocks($source)
{
    if (!is_string($source) || strlen($source) > 2 * 1024 * 1024) return [];
    $at = strpos($source, '{Exercises');
    if ($at === false) return [];
    $len = strlen($source);
    $at += strlen('{Exercises');
    $blocks = []; $depth = 0; $start = null; $quote = false; $escape = false;
    for ($i = $at; $i < $len; ++$i)
    {
        $c = $source[$i];
        if ($quote)
        {
            if ($escape) $escape = false;
            elseif ($c === "\\") $escape = true;
            elseif ($c === '"') $quote = false;
            continue;
        }
        if ($c === '"') { $quote = true; continue; }
        if ($c === '[') { if ($depth++ === 0) $start = $i + 1; }
        elseif ($c === ']')
        {
            if ($depth > 0 && --$depth === 0 && $start !== null)
            {
                $blocks[] = substr($source, $start, $i - $start);
                if (count($blocks) >= 5000) break;
                $start = null;
            }
        }
        elseif ($c === '}' && $depth === 0) break;
    }
    return $blocks;
}

function evaluation_history_fields($block)
{
    $fields = [];
    // Les proprietes importantes sont sur une ligne. Les valeurs Dabsic
    // peuvent contenir des virgules et des sequences \n entre guillemets.
    foreach (preg_split('/\r?\n/', $block) as $line)
    {
        if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) continue;
        $values = [];
        if (preg_match_all('~"((?:\\\\.|[^"\\\\])*)"~', $m[2], $parts))
            foreach ($parts[1] as $part) $values[] = stripcslashes($part);
        elseif (trim($m[2]) !== '') $values[] = trim($m[2]);
        $fields[$m[1]] = $values;
    }
    // Dabsic peut aussi representer les medailles comme un sous-bloc :
    // {Medals "medaille1", "medaille2"} (sur plusieurs lignes).
    // Ces valeurs ne passent pas par le lecteur des affectations `Cle = ...`.
    if (preg_match_all('/\{\s*Medals\b([^{}]*)\}/s', $block, $nested))
    {
        foreach ($nested[1] as $content)
        {
            if (preg_match_all('~"((?:\\\\.|[^"\\\\])*)"~', $content, $matches))
                foreach ($matches[1] as $value)
                    $fields['Medals'][] = stripcslashes($value);
        }
    }
    return $fields;
}

function evaluation_history_category($name)
{
    $name = strtolower($name);
    if (str_contains($name, 'compil') || str_contains($name, 'build')) return 'Compilation';
    if (str_contains($name, 'norm') || str_contains($name, 'forbidden') || str_contains($name, 'clean')) return 'Qualité du code';
    if (str_contains($name, 'test')) return 'Tests';
    return 'Fonctionnalités';
}

function evaluation_history_model($source)
{
    $results = []; $context = ''; $occurrences = [];
    foreach (evaluation_history_blocks($source) as $block)
    {
        $f = evaluation_history_fields($block);
        if (isset($f['Moving']))
        {
            $moving = $f['Moving'][0] ?? '';
            $name = strtolower($f['Name'][0] ?? '');
            if (str_contains($name, 'returning') || str_contains($name, 'previous')) $context = '';
            elseif ($moving !== '' && $moving[0] !== '/') $context = $moving;
            continue;
        }
        $name = $f['Name'][0] ?? '';
        $status = $f['Status'][0] ?? 'Unknown';
        if ($name === '') continue;
        $category = evaluation_history_category($name);
        $key = $context.'|'.$category.'|'.$name;
        $occurrences[$key] = ($occurrences[$key] ?? 0) + 1;
        $results[] = [
            'key' => $key.'|'.$occurrences[$key],
            'name' => $name, 'context' => $context,
            'category' => $category, 'status' => $status,
            'grade' => $f['Grade'][0] ?? null,
            'medals' => $f['Medals'] ?? [],
            'undesirable' => $f['UndesirableFound'] ?? [],
            'messages' => array_merge($f['Conclusion'] ?? [], $f['Message'] ?? [])
        ];
    }
    $counts = ['Success' => 0, 'Failure' => 0, 'Critical' => 0, 'Other' => 0];
    foreach ($results as $r)
    {
        $st = $r['status'];
        $counts[isset($counts[$st]) ? $st : 'Other']++;
    }
    $evaluated = $counts['Success'] + $counts['Failure'] + $counts['Critical'];
    return ['results' => $results, 'counts' => $counts,
        'evaluated' => $evaluated,
        'rate' => $evaluated > 0 ? round(100 * $counts['Success'] / $evaluated) : null];
}

// Les identites se fondent sur le chemin, la categorie, le nom et le rang
// d'occurrence. Elles sont propres a une equipe et une activite.
function evaluation_history_states($results)
{
    $states = [];
    foreach ($results as $r)
    {
        $status = $r['status'];
        if (!in_array($status, ['Success', 'Failure', 'Critical'], true)) continue;
        $states[$r['key']] = ['status' => $status,
            'label' => ($r['context'] !== '' ? $r['context'].' · ' : '').$r['name']];
    }
    return $states;
}

function evaluation_history_previous_snapshot($dir)
{
    $files = glob($dir.'/*.json') ?: [];
    rsort($files, SORT_STRING);
    foreach ($files as $file)
    {
        $meta = json_decode(@file_get_contents($file), true);
        if (!is_array($meta) || empty($meta['has_report'])) continue;
        $states = $meta['summary']['states'] ?? null;
        if (!is_array($states))
        {
            $dab = substr($file, 0, -5).'.dab';
            if (!is_file($dab)) continue;
            $source = @file_get_contents($dab);
            if ($source === false) continue;
            $states = evaluation_history_states(evaluation_history_model($source)['results']);
        }
        return ['id' => $meta['id'], 'states' => $states];
    }
    return null;
}

function evaluation_history_compare($before, $after)
{
    $out = ['new_success' => [], 'regressions' => [], 'persistent_failures' => [],
        'new_controls' => [], 'missing_controls' => [], 'unchanged_success' => []];
    foreach ($after as $key => $value)
    {
        $current = $value['status'];
        $label = $value['label'];
        if (!isset($before[$key]))
        {
            $out['new_controls'][] = $label;
            continue;
        }
        $previous = $before[$key]['status'];
        if ($current === 'Success' && $previous !== 'Success')
            $out['new_success'][] = $label;
        elseif ($current !== 'Success' && $previous === 'Success')
            $out['regressions'][] = $label;
        elseif ($current !== 'Success' && $previous !== 'Success')
            $out['persistent_failures'][] = $label;
        elseif ($current === 'Success' && $previous === 'Success')
            $out['unchanged_success'][] = $label;
    }
    foreach ($before as $key => $value)
        if (!isset($after[$key])) $out['missing_controls'][] = $value['label'];
    return $out;
}

function evaluation_history_comparison_html($comparison)
{
    if (!is_array($comparison)) return '';
    $groups = ['new_success' => ['Nouveaux contrôles validés', '#28794e'],
        'regressions' => ['Régressions', '#ae4e42'],
        'persistent_failures' => ['Problèmes persistants', '#ae7950'],
        'new_controls' => ['Nouveaux contrôles (non comparables)', '#667e99'],
        'missing_controls' => ['Contrôles absents (non comparables)', '#667e99']];
    $html = '<section class="evaluation-comparison"><h5>Évolution depuis l’évaluation précédente</h5>';
    foreach ($groups as $key => $display)
    {
        $items = $comparison[$key] ?? [];
        $html .= '<details style="margin:5px 0"><summary style="color:'.$display[1].'">'
            .evaluation_history_escape($display[0]).' : '.count($items).'</summary>';
        if ($items)
        {
            $html .= '<ul>';
            foreach ($items as $label) $html .= '<li>'.evaluation_history_escape($label).'</li>';
            $html .= '</ul>';
        }
        $html .= '</details>';
    }
    return $html.'</section>';
}

function evaluation_history_escape($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// L'ordre de la liste est pedagogique : Moving ouvre/ferme une partie.
// Un controle technique reussi ne devient pas une nouvelle competence.
function evaluation_history_exercises($results)
{
    $groups = [];
    $current = null;
    foreach ($results as $r)
    {
        $context = trim((string)($r['context'] ?? ''));
        $name = trim((string)$r['name']);
        $group = $context !== '' ? $context : $name;
        if (!isset($groups[$group]))
            $groups[$group] = ['name' => $group, 'checks' => []];
        $groups[$group]['checks'][] = $r;
    }
    foreach ($groups as &$group)
    {
        $group['issues'] = [];
        $group['outcome'] = 'Success';
        $group['tests'] = 0;
        foreach ($group['checks'] as $r)
        {
            $technical = evaluation_history_is_precondition($r);
            if (!$technical) $group['tests']++;
            if ($r['status'] === 'Failure' || $r['status'] === 'Critical')
            {
                $group['issues'][] = $r;
                $group['outcome'] = 'Failure';
            }
            elseif ($technical && evaluation_history_has_warning($r))
                $group['issues'][] = $r;
            elseif ($r['status'] !== 'Success' && $group['outcome'] === 'Success')
                $group['outcome'] = 'Unknown';
            // Une reussite technique ne doit pas encombrer le rapport.
        }
        if (!$group['tests'] && !$group['issues']) $group['outcome'] = 'Incomplete';
    }
    unset($group);
    return array_values($groups);
}

function evaluation_history_has_warning($r)
{
    foreach ($r['messages'] as $msg)
        if (preg_match('/\b(warnings?|avertissements?)\b/i', $msg)) return true;
    return false;
}

function evaluation_history_is_precondition($r)
{
    $name = strtolower((string)$r['name']);
    return str_contains($name, 'compil') || str_contains($name, 'build')
        || str_contains($name, 'forbidden') || str_contains($name, 'clean')
        || $name === 'norm' || str_contains($name, 'repository');
}

function evaluation_history_check_label($r)
{
    $name = strtolower((string)$r['name']);
    if (str_contains($name, 'compil') || str_contains($name, 'build'))
        return $r['status'] === 'Success' ? 'Compilation réussie' : 'La construction a échoué';
    if (str_contains($name, 'forbidden'))
    {
        $summary = implode(' ', $r['messages'] ?? []);
        return str_contains(strtolower($summary), 'cannot build temporary library')
            ? 'Vérification des fonctions interdites impossible'
            : 'Contrôle des fonctions interdites';
    }
    if (str_contains($name, 'clean') || str_contains($name, 'repository'))
        return 'Problème dans le dépôt';
    if ($name === 'norm') return 'Problème de norme';
    return (string)$r['name'];
}

function evaluation_history_status_label($status)
{
    return ['Success' => 'Réussi', 'Failure' => 'Échec', 'Critical' => 'Erreur bloquante',
        'Unknown' => 'Non déterminé', 'Incomplete' => 'Non évalué'][$status] ?? 'À vérifier';
}

// Les medailles appartiennent aux controles, pas seulement aux succes.
// On conserve la premiere occurrence de chaque medaille par exercice.
function evaluation_history_exercise_medals($group)
{
    $medals = [];
    foreach ($group['checks'] as $check)
        foreach (($check['medals'] ?? []) as $name)
        {
            $name = trim((string)$name);
            if ($name !== '') $medals[$name] = $name;
        }
    return array_values($medals);
}

function evaluation_history_medals_html($medals, $email = false)
{
    if (!$medals) return '';
    global $Configuration;
    $html = '<div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center"'
        .' aria-label="Médailles obtenues">';
    foreach ($medals as $codename)
    {
        $name = evaluation_history_escape($codename);
        // Les noms Dabsic proviennent d'un rapport : aucune traversee de chemin.
        // La version courriel conserve le nom, sans URL relative inutilisable.
        $icon = null;
        if (!$email && preg_match('/\A[A-Za-z0-9_-]+\z/D', $codename))
        {
            $candidate = $Configuration->MedalsDir($codename).'icon.png';
            if (is_file($candidate)) $icon = $candidate;
        }
        if ($icon !== null)
            $html .= '<img loading="lazy" src="'.evaluation_history_escape($icon).'"'
                .' alt="'. $name .'" title="'. $name .'" width="30" height="30"'
                .' style="object-fit:contain;flex:none" />';
        else
            $html .= '<span title="Médaille : '.$name.'" style="font-size:.85em;'
                .'padding:3px 7px;border-radius:6px;border:1px solid rgba(128,128,128,.3)">'
                .$name.'</span>';
    }
    return $html.'</div>';
}

// Une seule occurrence par diagnostic et par exercice, meme si plusieurs controles
// remontent exactement la meme sortie de compilateur. Conserver le texte original.
function evaluation_history_diagnostics($group)
{
    $diagnostics = [];
    foreach (($group['issues'] ?? []) as $check)
    {
        $label = evaluation_history_check_label($check);
        foreach (($check['messages'] ?? []) as $message)
        {
            $message = trim((string)$message);
            if ($message === '') continue;
            $fingerprint = hash('sha256', str_replace(["\r\n", "\r"], "\n", $message));
            if (!isset($diagnostics[$fingerprint]))
                $diagnostics[$fingerprint] = ['message' => $message, 'labels' => []];
            $diagnostics[$fingerprint]['labels'][$label] = $label;
        }
    }
    return array_values($diagnostics);
}

function evaluation_history_diagnostics_html($group, $email = false)
{
    $diagnostics = evaluation_history_diagnostics($group);
    if (!$diagnostics) return '';
    $labels = [];
    foreach ($diagnostics as $diagnostic)
        foreach ($diagnostic['labels'] as $label) $labels[$label] = $label;
    $label_html = implode(' · ', array_map('evaluation_history_escape', array_values($labels)));
    $number = count($diagnostics);
    $heading = $number === 1 ? '1 diagnostic' : $number.' diagnostics distincts';
    $full = [];
    foreach ($diagnostics as $diagnostic)
        $full[] = implode(', ', $diagnostic['labels'])."\n".$diagnostic['message'];
    $text = implode("\n\n-----\n\n", $full);
    $html = '<div class="evaluation-diagnostics evaluation-step-box is-warning" style="margin-top:9px">'
        .'<div class="evaluation-step-header"><div class="evaluation-step-title">Diagnostics utiles</div>'
        .'<div class="evaluation-step-state">'.$heading.'</div></div>'
        .'<div class="evaluation-step-body"><div style="font-size:.9em;opacity:.9">'.$label_html.'</div>';
    if ($email)
        return $html.'<div style="font-size:.85em;opacity:.8;margin-top:6px">Consulter le détail sur Infosphère.</div></div></div>';
    // Un details natif permet le clic et reste utilisable sans JavaScript.
    // Le survol affiche uniquement un court extrait; le rapport complet reste dans pre.
    $preview = substr($text, 0, 450);
    // Eviter une sequence UTF-8 tronquee au bord de l'apercu.
    $preview = preg_replace('/[\x80-\xBF]*$/', '', $preview);
    if (strlen($text) > 450) $preview .= '…';
    $html .= '<details class="evaluation-diagnostic-details" style="position:relative;margin-top:6px">'
        .'<summary style="display:inline-block;cursor:pointer;position:relative;'
        .'padding:5px 9px;border:1px solid rgba(128,128,128,.35);border-radius:7px;'
        .'background:rgba(255,255,255,.08)" title="Survol : aperçu · Clic : diagnostic complet">'
        .'⚠ Voir / copier'
        .'<span class="evaluation-diagnostic-preview" role="tooltip">'
        .evaluation_history_escape($preview).'</span></summary>'
        .'<div style="margin-top:8px"><button type="button" class="evaluation-diagnostic-copy"'
        .' style="cursor:pointer;padding:5px 9px;border-radius:6px">Copier le diagnostic</button>'
        .'<pre class="evaluation-diagnostic-text" style="white-space:pre-wrap;'
        .'overflow-wrap:anywhere;max-height:340px;overflow:auto;margin-top:8px;'
        .'padding:10px;background:rgba(0,0,0,.18);border-radius:6px">'
        .evaluation_history_escape($text).'</pre></div></details></div>';
    return $html;
}

function evaluation_history_diagnostics_assets()
{
    return <<<'HTML'
<style>
.evaluation-exercise {box-shadow:0 1px 0 rgba(255,255,255,.03) inset, 0 10px 24px rgba(0,0,0,.08)}
.evaluation-exercise + .evaluation-exercise {margin-top:18px!important;}
.evaluation-exercise > .evaluation-exercise-heading {border-bottom:1px solid rgba(255,255,255,.15);}
.evaluation-exercise + .evaluation-exercise {margin-top:22px!important}
.evaluation-exercise-heading {display:flex;justify-content:space-between;align-items:center;
  flex-wrap:wrap;gap:10px;min-height:34px;padding:9px 12px!important;margin:4px -4px 12px;
  border-radius:7px;border:1px solid rgba(255,255,255,.13)!important;
  background:rgba(255,255,255,.095)}
.evaluation-exercise-heading strong {font-size:1.18em!important;line-height:1.3;letter-spacing:.01em}
.evaluation-exercise-toggle > summary {list-style:none;cursor:pointer;user-select:none}
.evaluation-exercise-toggle > summary::-webkit-details-marker {display:none}
.evaluation-exercise-toggle > summary::before {content:'▸';display:inline-block;flex:none;
  color:rgba(255,255,255,.75);font-size:1.2em;transition:transform .15s ease}
.evaluation-exercise-toggle[open] > summary::before {transform:rotate(90deg)}
.evaluation-exercise-toggle > summary:hover {background:rgba(255,255,255,.14)!important}
.evaluation-exercise-toggle > summary:focus-visible {outline:2px solid #9dbeac;outline-offset:2px}
.evaluation-exercise-toggle:not([open]) {padding-bottom:5px!important}
.evaluation-exercise-state {font-size:.93em;font-weight:600;padding:3px 9px;border-radius:6px;
  background:rgba(0,0,0,.2);border:1px solid currentColor}

.evaluation-step-grid {display:grid;grid-template-columns:1fr;gap:8px;margin-top:10px}
.evaluation-step-box {border:1px solid rgba(255,255,255,.12);border-left:4px solid rgba(170,170,170,.55);
  border-radius:10px;background:rgba(255,255,255,.05);padding:8px 10px;overflow-wrap:anywhere}
.evaluation-step-box.is-success {border-left-color:#4baf88;background:rgba(75,175,136,.09)}
.evaluation-step-box.is-failure {border-left-color:#b45345;background:rgba(180,83,69,.08)}
.evaluation-step-box.is-warning {border-left-color:#b08a45;background:rgba(176,138,69,.08)}
.evaluation-step-box.is-neutral {border-left-color:#758099;background:rgba(117,128,153,.08)}
.evaluation-step-header {display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
.evaluation-step-title {font-weight:600}
.evaluation-step-state {font-size:.86em;opacity:.85;white-space:nowrap}
.evaluation-step-body {margin-top:6px;font-size:.95em}
.evaluation-cleanliness-files {margin-top:7px}
.evaluation-cleanliness-files code {display:inline-block;margin:2px 4px 2px 0}
.evaluation-check-medals {margin-top:7px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.evaluation-diagnostic-preview {display:none;position:absolute;z-index:50;bottom:calc(100% + 7px);
  left:0;width:min(390px,75vw);max-height:165px;overflow:auto;white-space:pre-wrap;
  overflow-wrap:anywhere;box-sizing:border-box;padding:10px;border:1px solid #777;
  border-radius:7px;background:#232a28;color:#f5f5f5;box-shadow:0 5px 18px #0008;
  font-weight:normal;font-size:12px;text-align:left;pointer-events:none}
.evaluation-diagnostic-details:not([open]) > summary:hover .evaluation-diagnostic-preview,
.evaluation-diagnostic-details:not([open]) > summary:focus .evaluation-diagnostic-preview {display:block}
@media (hover:none) {.evaluation-diagnostic-preview {display:none!important}}
</style>
<script>
if (!window.evaluationDiagnosticCopyBound) {
  window.evaluationDiagnosticCopyBound = true;
  document.addEventListener('click', async function(event) {
    const button = event.target.closest('.evaluation-diagnostic-copy');
    if (!button) return;
    const pre = button.closest('.evaluation-diagnostic-details')?.querySelector('.evaluation-diagnostic-text');
    if (!pre) return;
    const value = pre.textContent;
    let copied = false;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
        copied = true;
      } else {
        const input = document.createElement('textarea');
        input.value = value;
        input.style.position = 'fixed'; input.style.left = '-9999px';
        document.body.appendChild(input);
        input.select(); copied = document.execCommand('copy'); input.remove();
      }
    } catch (error) { copied = false; }
    button.textContent = copied ? 'Copié !' : 'Copie impossible : sélectionner le texte ci-dessous';
    button.setAttribute('aria-label', button.textContent);
  });
}
</script>
HTML;
}

// Styles volontairement en ligne : les mêmes cartes sont réutilisées dans les courriels.

function evaluation_history_step_tone($check)
{
    if (($check['status'] ?? '') === 'Failure' || ($check['status'] ?? '') === 'Critical')
        return 'failure';
    if (evaluation_history_has_warning($check))
        return 'warning';
    if (($check['status'] ?? '') === 'Success')
        return 'success';
    return 'neutral';
}

function evaluation_history_step_body_html($check)
{
    $parts = [];
    $conclusion = trim((string)($check['conclusion'] ?? ''));
    if ($conclusion !== '')
        $parts[] = '<div>'.nl2br(evaluation_history_escape($conclusion)).'</div>';
    if (!empty($check['undesirable']))
    {
        $files = array_values(array_unique(array_filter(array_map('strval', $check['undesirable']))));
        if ($files)
        {
            $line = '<div class="evaluation-cleanliness-files"><strong>Fichiers indésirables ('
                .count($files).') :</strong> ';
            $chunks = [];
            foreach ($files as $file)
                $chunks[] = '<code style="font-size:.92em;background:rgba(128,128,128,.14);'
                    .'padding:2px 5px;border-radius:4px">'.evaluation_history_escape($file).'</code>';
            $parts[] = $line.implode(' ', $chunks).'</div>';
        }
    }
    return implode('', $parts);
}

function evaluation_history_step_box_html($check, $email = false)
{
    $tone = evaluation_history_step_tone($check);
    $title = evaluation_history_check_label($check);
    $status = evaluation_history_status_label($check['status'] ?? 'Unknown');
    $body = evaluation_history_step_body_html($check);
    $html = '<div class="evaluation-step-box is-'.evaluation_history_escape($tone).'">'
        .'<div class="evaluation-step-header"><div class="evaluation-step-title">'
        .evaluation_history_escape($title).'</div><div class="evaluation-step-state">'
        .evaluation_history_escape($status).'</div></div>';
    if ($body !== '')
        $html .= '<div class="evaluation-step-body">'.$body.'</div>';
    if (!empty($check['medals']))
        $html .= '<div class="evaluation-check-medals"><small style="opacity:.78">Médailles :</small>'
            .evaluation_history_medals_html(array_values(array_unique($check['medals'])), $email).'</div>';
    return $html.'</div>';
}

function evaluation_history_exercise_html($group, $email = false)
{
    $outcome = $group['outcome'];
    $palette = [
        'Success' => ['#39866a', 'rgba(255,255,255,.065)', 'Validé'],
        'Failure' => ['#b45345', 'rgba(255,255,255,.065)', 'À corriger'],
        'Incomplete' => ['#7b8290', 'rgba(255,255,255,.065)', 'Non évalué'],
        'Unknown' => ['#ab8738', 'rgba(255,255,255,.065)', 'À vérifier']
    ];
    [$color, $background, $state] = $palette[$outcome] ?? $palette['Unknown'];
    $label = evaluation_history_escape($group['name']);
    $html = '<'.($email ? 'section' : 'details open').' class="evaluation-exercise evaluation-exercise-'
        .evaluation_history_escape(strtolower($outcome)).($email ? '' : ' evaluation-exercise-toggle')
        .'" style="border:1px solid '
        .'rgba(128,128,128,.22);border-left:5px solid '.$color.';border-radius:9px;background:'
        .$background.';margin:11px 0;padding:11px 14px;overflow-wrap:anywhere">';
    $html .= '<'.($email ? 'header' : 'summary').' class="evaluation-exercise-heading" style="display:flex;'
        .'justify-content:space-between;align-items:center;gap:10px;min-height:34px;'
        .'padding:9px 12px;margin:4px -4px 12px;border-radius:7px;'
        .'border:1px solid rgba(255,255,255,.13);background:rgba(255,255,255,.095)">'
        .'<strong style="font-size:1.18em;line-height:1.3">'.$label.'</strong>'
        .'<span class="evaluation-exercise-state" style="font-weight:600;color:'.$color.';'
        .'padding:3px 9px;border-radius:6px;border:1px solid currentColor">'
        .evaluation_history_escape($state).'</span></'.($email ? 'header' : 'summary').'>';
    $visible_checks = [];
    foreach ($group['checks'] as $check)
    {
        $is_moving = array_key_exists('moving', $check);
        $has_visual_content = !empty($check['medals']) || !empty($check['undesirable'])
            || trim((string)($check['conclusion'] ?? '')) !== ''
            || (($check['status'] ?? '') !== 'Success');
        if ($is_moving || !$has_visual_content) continue;
        $visible_checks[] = $check;
    }
    if ($visible_checks)
    {
        $html .= '<div class="evaluation-step-grid">';
        foreach ($visible_checks as $check)
            $html .= evaluation_history_step_box_html($check, $email);
        $html .= '</div>';
    }
    $html .= evaluation_history_diagnostics_html($group, $email);
    if (!$email)
    {
        $html .= '<details style="margin-top:9px"><summary style="cursor:pointer;opacity:.8">'
            .'Détails techniques ('.count($group['checks']).' contrôles)</summary>'
            .'<ul style="padding-left:22px;margin:7px 0">';
        foreach ($group['checks'] as $r)
        {
            $html .= '<li>'.evaluation_history_escape($r['name']).' — '
                .evaluation_history_escape(evaluation_history_status_label($r['status']));
            if ($r['grade'] !== null) $html .= ' ('.evaluation_history_escape($r['grade']).')';
            $html .= '</li>';
        }
        $html .= '</ul></details>';
    }
    return $html.'</'.($email ? 'section' : 'details').'>';
}

function evaluation_history_render($source)
{
    if ($source === null) return '<p>Rapport détaillé indisponible dans cette archive.</p>';
    $model = evaluation_history_model($source);
    $groups = evaluation_history_exercises($model['results']);
    if (!$groups) return '<p>Rapport conservé, mais aucun exercice reconnu.</p>';
    $valid = count(array_filter($groups, fn($g) => $g['outcome'] === 'Success'));
    $errors = count(array_filter($groups, fn($g) => $g['outcome'] === 'Failure'));
    $html = '<div class="evaluation-metrics" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,220px));gap:10px;margin:10px 0 12px;align-items:start;justify-content:start">';
    foreach (['Parties évaluées' => count($groups), 'Sans anomalie' => $valid,
        'À corriger' => $errors] as $label => $value)
        $html .= '<div style="padding:8px 11px;min-height:0;background:rgba(128,128,128,.10);border:1px solid rgba(128,128,128,.2);border-radius:9px;align-self:start"><small style="display:block;opacity:.85">'
            .evaluation_history_escape($label).'</small><strong style="display:block;font-size:1.9em;line-height:1.05;margin-top:3px">'.$value.'</strong></div>';
    $html .= '</div>';
    foreach ($groups as $group) $html .= evaluation_history_exercise_html($group);
    return evaluation_history_diagnostics_assets().$html;
}

function evaluation_history_email_html($activity_name, $official, $source)
{
    $model = evaluation_history_model($source);
    $groups = evaluation_history_exercises($model['results']);
    $html = '<div style="font-family:Arial,sans-serif;max-width:680px;margin:auto;color:#253045">'
        .'<h2>'.evaluation_history_escape($activity_name).'</h2><p><strong>'
        .($official ? 'Évaluation officielle' : 'Évaluation non officielle').'</strong></p>';
    if (!$groups) return $html.'<p>Rapport indisponible.</p></div>';
    foreach ($groups as $group) $html .= evaluation_history_exercise_html($group, true);
    return $html.'</div>';
}
