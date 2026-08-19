<?php $questionnaire_page_archived_rows = questionnaire_list(true); ?>
<div class="questionnaire-list-heading">
    <h3><?=$Dictionnary["QuestionnaireArchives"] ?? "Archives"; ?></h3>
    <span><?=count($questionnaire_page_archived_rows); ?></span>
</div>
<div class="questionnaire-list questionnaire-archive-list">
    <?php foreach ($questionnaire_page_archived_rows as $row) { ?>
        <article class="questionnaire-list-card is-archived">
            <div>
                <h3><code><?=htmlspecialchars($row["school_codename"]."/".$row["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code></h3>
                <p><?=$Dictionnary["QuestionnaireArchivedAt"] ?? "Archivé"; ?> : <?=htmlspecialchars((string)$row["deleted"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></p>
            </div>
            <div></div>
            <div></div>
            <form method="post" class="questionnaire-list-actions">
                <input type="hidden" name="action" value="restore" />
                <input type="hidden" name="questionnaire_id" value="<?=(int)$row["id"]; ?>" />
                <button type="submit" class="questionnaire-secondary"><?=$Dictionnary["QuestionnaireRestore"] ?? "Restaurer"; ?></button>
            </form>
        </article>
    <?php } ?>
    <?php if (!count($questionnaire_page_archived_rows)) { ?>
        <div class="questionnaire-notice questionnaire-empty"><?=$Dictionnary["QuestionnaireNoArchive"] ?? "Aucun questionnaire archivé."; ?></div>
    <?php } ?>
</div>
