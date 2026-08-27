<?php
require_once (__DIR__."/../../tools/quiz_attempt.php");
$questionnaire_attempt_rows = quiz_attempt_list_for_quiz((int)$questionnaire_page_row["id"], 500);
$questionnaire_attempt_contexts = [];
foreach ($questionnaire_attempt_rows as $attempt)
{
    $context = trim((string)($attempt["context_type"] ?? "manual"));
    if ($context === "")
        $context = "manual";
    $questionnaire_attempt_contexts[$context] = true;
}
ksort($questionnaire_attempt_contexts);
?>
<div class="questionnaire-attempts">
    <div class="questionnaire-notice">
        <strong><?=$Dictionnary["QuizAttempts"] ?? "Tentatives"; ?></strong>
        <p><?=$Dictionnary["QuizAttemptsHelp"] ?? "Chaque tentative fige la configuration effective du questionnaire. Elle peut provenir d’une activité, d’une attribution directe ou d’un lien externe."; ?></p>
    </div>

    <section class="questionnaire-distribution">
        <h3><?=$Dictionnary["QuizDistribution"] ?? "Distribuer ce questionnaire"; ?></h3>
        <div class="questionnaire-distribution-grid">
            <form method="post" action="/api/questionnaire/<?=(int)$questionnaire_page_row["id"]; ?>/invite_user" onsubmit="return silent_submitf(this, {after_success: questionnaireInvitationCreated});">
                <h4><?=$Dictionnary["QuizDistributionUser"] ?? "Utilisateur Infosphere"; ?></h4>
                <label><span><?=$Dictionnary["QuizInvitationUser"] ?? "Nom de code ou courriel"; ?></span><input type="text" name="recipient" required /></label>
                <label><span><?=$Dictionnary["QuizInvitationExpires"] ?? "Expiration (jours)"; ?></span><input type="number" min="1" max="365" name="expires_days" value="14" /></label>
                <label class="questionnaire-inline-check"><input type="checkbox" name="send_mail" value="1" /><span><?=$Dictionnary["QuizInvitationSendMail"] ?? "Envoyer le lien par courriel"; ?></span></label>
                <button type="submit" class="questionnaire-primary"><?=$Dictionnary["QuizInvitationCreateForUser"] ?? "Créer l’invitation"; ?></button>
                <small><?=$Dictionnary["QuizDistributionUserHelp"] ?? "Le lien fonctionne même si le compte ne possède pas de mot de passe ; la réponse reste rattachée à son identité Infosphere."; ?></small>
            </form>

            <form method="post" action="/api/questionnaire/<?=(int)$questionnaire_page_row["id"]; ?>/invite_external" onsubmit="return silent_submitf(this, {after_success: questionnaireInvitationCreated});">
                <h4><?=$Dictionnary["QuizDistributionExternal"] ?? "Destinataire externe"; ?></h4>
                <label><span><?=$Dictionnary["Name"] ?? "Nom"; ?></span><input type="text" name="recipient_name" /></label>
                <label><span><?=$Dictionnary["Mail"] ?? "Courriel"; ?></span><input type="email" name="recipient_mail" /></label>
                <label><span><?=$Dictionnary["QuizInvitationExpires"] ?? "Expiration (jours)"; ?></span><input type="number" min="1" max="365" name="expires_days" value="14" /></label>
                <label class="questionnaire-inline-check"><input type="checkbox" name="send_mail" value="1" /><span><?=$Dictionnary["QuizInvitationSendMail"] ?? "Envoyer le lien par courriel"; ?></span></label>
                <button type="submit" class="questionnaire-primary"><?=$Dictionnary["QuizInvitationCreateExternal"] ?? "Créer le lien externe"; ?></button>
            </form>

            <form method="post" action="/api/questionnaire/<?=(int)$questionnaire_page_row["id"]; ?>/attempt" onsubmit="return silent_submitf(this, {after_success: questionnaireOpenAttempt});">
                <h4><?=$Dictionnary["QuizAttemptTest"] ?? "Test responsable"; ?></h4>
                <p><?=$Dictionnary["QuizAttemptTestHelp"] ?? "Crée une vraie tentative sans effet pédagogique afin de vérifier le rendu et la correction."; ?></p>
                <button type="submit" class="questionnaire-secondary">▶ <?=$Dictionnary["QuizAttemptStartSelfTest"] ?? "Démarrer une tentative de test"; ?></button>
            </form>
        </div>
        <div id="questionnaire-invitation-link" class="questionnaire-invitation-link" hidden>
            <strong><?=$Dictionnary["QuizInvitationLink"] ?? "Lien créé"; ?> :</strong>
            <input type="text" readonly />
            <button type="button" class="questionnaire-secondary" onclick="questionnaireCopyInvitation();"><?=$Dictionnary["Copy"] ?? "Copier"; ?></button>
        </div>
    </section>

    <div class="questionnaire-attempt-toolbar">
        <label><span><?=$Dictionnary["Search"] ?? "Recherche"; ?></span><input type="search" id="questionnaire-attempt-search" oninput="questionnaireFilterAttempts();" placeholder="<?=$Dictionnary["QuizAttemptFilterPlaceholder"] ?? "répondant, courriel, #tentative…"; ?>" /></label>
        <label><span><?=$Dictionnary["QuizAttemptContext"] ?? "Contexte"; ?></span><select id="questionnaire-attempt-context" onchange="questionnaireFilterAttempts();"><option value=""><?=$Dictionnary["All"] ?? "Tous"; ?></option><?php foreach (array_keys($questionnaire_attempt_contexts) as $context) { ?><option value="<?=htmlspecialchars($context, ENT_QUOTES); ?>"><?=htmlspecialchars($context, ENT_QUOTES); ?></option><?php } ?></select></label>
        <label><span><?=$Dictionnary["Status"] ?? "État"; ?></span><select id="questionnaire-attempt-status" onchange="questionnaireFilterAttempts();"><option value=""><?=$Dictionnary["All"] ?? "Tous"; ?></option><option value="progress"><?=$Dictionnary["QuizAttemptInProgress"] ?? "En cours"; ?></option><option value="submitted"><?=$Dictionnary["QuizAttemptSubmitted"] ?? "Soumise"; ?></option><option value="expired"><?=$Dictionnary["QuizInvitationExpired"] ?? "Expirée"; ?></option><option value="revoked"><?=$Dictionnary["QuizInvitationRevoked"] ?? "Révoquée"; ?></option></select></label>
    </div>

    <div class="questionnaire-attempt-list" id="questionnaire-attempt-list">
        <?php foreach ($questionnaire_attempt_rows as $attempt) {
            $respondent = trim((string)($attempt["respondent_nickname"] ?? ""));
            if ($respondent == "") $respondent = trim((string)($attempt["respondent_codename"] ?? ""));
            if ($respondent == "") $respondent = trim((string)($attempt["recipient_name"] ?? ""));
            if ($respondent == "") $respondent = trim((string)($attempt["recipient_mail"] ?? ""));
            if ($respondent == "") $respondent = $Dictionnary["QuizAttemptAnonymous"] ?? "Sans compte";
            $submitted = (string)$attempt["status"] === "submitted";
            $invitation_state = quiz_attempt_invitation_state($attempt);
            $external = $invitation_state !== "none";
            $row_status = $submitted ? "submitted" : ($invitation_state === "revoked" ? "revoked" : ($invitation_state === "expired" ? "expired" : "progress"));
            $search = strtolower("#".(int)$attempt["id"]." ".$respondent." ".(string)($attempt["recipient_mail"] ?? "")." ".(string)($attempt["context_type"] ?? ""));
        ?>
            <article class="questionnaire-attempt-row <?=$row_status === "revoked" || $row_status === "expired" ? "revoked" : ""; ?>" data-attempt-context="<?=htmlspecialchars((string)$attempt["context_type"], ENT_QUOTES); ?>" data-attempt-status="<?=$row_status; ?>" data-attempt-search="<?=htmlspecialchars($search, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>">
                <div><strong>#<?=(int)$attempt["id"]; ?></strong><span><?=htmlspecialchars($respondent, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span><small><?=htmlspecialchars((string)$attempt["context_type"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></small></div>
                <div>
                    <span class="questionnaire-attempt-status <?=$row_status; ?>"><?php
                        if ($submitted) echo $Dictionnary["QuizAttemptSubmitted"] ?? "Soumise";
                        else if ($row_status === "revoked") echo $Dictionnary["QuizInvitationRevoked"] ?? "Révoquée";
                        else if ($row_status === "expired") echo $Dictionnary["QuizInvitationExpired"] ?? "Expirée";
                        else echo $Dictionnary["QuizAttemptInProgress"] ?? "En cours";
                    ?></span>
                    <small><?=htmlspecialchars((string)$attempt["started_at"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></small>
                    <?php if ($external && $attempt["expires_at"] !== NULL) { ?><small><?=$Dictionnary["QuizInvitationExpiresAt"] ?? "Expire"; ?> <?=htmlspecialchars((string)$attempt["expires_at"], ENT_QUOTES); ?></small><?php } ?>
                </div>
                <div><?php if ($submitted && $attempt["max_score"] !== NULL && (float)$attempt["max_score"] > 0) { ?><strong><?=htmlspecialchars(rtrim(rtrim(number_format((float)$attempt["score"], 2, ".", ""), "0"), "."), ENT_QUOTES); ?> / <?=htmlspecialchars(rtrim(rtrim(number_format((float)$attempt["max_score"], 2, ".", ""), "0"), "."), ENT_QUOTES); ?></strong><small><?=htmlspecialchars(number_format((float)$attempt["success_percent"], 1, ".", ""), ENT_QUOTES); ?>%</small><?php } else { ?><span><?=$submitted ? ($Dictionnary["QuizAttemptUngraded"] ?? "Non notée") : "—"; ?></span><?php } ?></div>
                <div class="questionnaire-list-actions questionnaire-attempt-actions-cell">
                    <a class="questionnaire-secondary" href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$attempt["id"]; ?>"><?=$Dictionnary["Open"] ?? "Ouvrir"; ?></a>
                    <?php if ($external && !$submitted) { ?>
                        <details class="questionnaire-invitation-refresh">
                            <summary><?=$Dictionnary["QuizInvitationRefresh"] ?? "Renouveler le lien"; ?></summary>
                            <form method="post" action="/api/questionnaire/<?=(int)$questionnaire_page_row["id"]; ?>/refresh_invitation" onsubmit="return silent_submitf(this, {after_success: questionnaireInvitationCreated});">
                                <input type="hidden" name="attempt_id" value="<?=(int)$attempt["id"]; ?>" />
                                <label><span><?=$Dictionnary["QuizInvitationExpires"] ?? "Expiration (jours)"; ?></span><input type="number" min="1" max="365" name="expires_days" value="14" /></label>
                                <?php if (trim((string)($attempt["recipient_mail"] ?? "")) !== "") { ?><label class="questionnaire-inline-check"><input type="checkbox" name="send_mail" value="1" /><span><?=$Dictionnary["QuizInvitationSendMail"] ?? "Envoyer le lien par courriel"; ?></span></label><?php } ?>
                                <button type="submit" class="questionnaire-secondary"><?=$Dictionnary["QuizInvitationRefreshAction"] ?? "Générer un nouveau lien"; ?></button>
                            </form>
                        </details>
                        <?php if ($row_status !== "revoked") { ?><form method="post" action="/api/questionnaire/<?=(int)$questionnaire_page_row["id"]; ?>/revoke_invitation" onsubmit="return silent_submitf(this, {after_success: questionnaireInvitationRevoked});"><input type="hidden" name="attempt_id" value="<?=(int)$attempt["id"]; ?>" /><button type="submit" class="questionnaire-danger-button" onclick="return confirm('<?=addslashes($Dictionnary["QuizInvitationRevokeConfirm"] ?? "Révoquer ce lien ?"); ?>');"><?=$Dictionnary["QuizInvitationRevoke"] ?? "Révoquer"; ?></button></form><?php } ?>
                    <?php } ?>
                </div>
            </article>
        <?php } ?>
        <?php if (!count($questionnaire_attempt_rows)) { ?><div class="questionnaire-notice questionnaire-empty"><?=$Dictionnary["QuizAttemptNone"] ?? "Aucune tentative pour ce questionnaire."; ?></div><?php } ?>
    </div>
</div>
<script>
function questionnaireOpenAttempt(result, msg, content) { if (content) window.location.href = content; }
function questionnaireInvitationCreated(result, msg, content) {
    var box = document.getElementById('questionnaire-invitation-link');
    if (!box || !content) return;
    var input = box.querySelector('input'); input.value = content; box.hidden = false; input.focus(); input.select();
}
function questionnaireInvitationRevoked() { window.location.reload(); }
function questionnaireCopyInvitation() {
    var input = document.querySelector('#questionnaire-invitation-link input');
    if (!input) return; input.select();
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(input.value); else document.execCommand('copy');
}
function questionnaireFilterAttempts() {
    var search = (document.getElementById('questionnaire-attempt-search') || {}).value || '';
    var context = (document.getElementById('questionnaire-attempt-context') || {}).value || '';
    var status = (document.getElementById('questionnaire-attempt-status') || {}).value || '';
    search = search.toLowerCase().trim();
    document.querySelectorAll('#questionnaire-attempt-list .questionnaire-attempt-row').forEach(function(row) {
        var ok = (!search || (row.dataset.attemptSearch || '').indexOf(search) !== -1)
            && (!context || row.dataset.attemptContext === context)
            && (!status || row.dataset.attemptStatus === status);
        row.hidden = !ok;
    });
}
</script>
