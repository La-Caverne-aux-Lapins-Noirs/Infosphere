<?php $configuration_operations = configuration_operation_list(); ?>
<section class="configuration-card configuration-operations-card">
    <header class="configuration-card-header">
        <div>
            <h2>Commandes usuelles</h2>
            <p class="configuration-field-description">
                Scripts serveur disponibles dans <code>pages/configuration/</code>. Le bouton Voir affiche le code dans la boîte utilitaire ; Exécuter lance la commande après confirmation et écrit son résultat dans cette même boîte.
            </p>
        </div>
    </header>

    <section id="configuration-operation-output" class="configuration-subcard configuration-operation-output">
        <h3>Boîte utilitaire</h3>
        <p class="configuration-muted">
            Sélectionne une commande pour consulter son code ou exécuter une opération.
        </p>
        <?php require (__DIR__."/command_result_content.php"); ?>
    </section>

    <div class="configuration-operation-buttons">
        <?php foreach ($configuration_operations as $operation_id => $operation) { ?>
            <article class="configuration-operation-row">
                <div class="configuration-operation-name" title="<?=configuration_html($operation["name"]); ?>">
                    <strong><?=configuration_html($operation["title"]); ?></strong>
                    <code><?=configuration_html($operation["name"]); ?></code>
                </div>
                <div class="configuration-operation-actions">
                    <button
                        type="button"
                        class="configuration-button"
                        onclick="return configurationShowOperationSource(<?=$operation_id; ?>);"
                    >Voir</button>
                    <form method="post" action="api/configuration/0/operation" onsubmit="return configurationRunOperation(this, <?=configuration_javascript("Exécuter ".$operation["title"]." ?"); ?>);">
                        <input type="hidden" name="operation" value="<?=$operation_id; ?>" />
                        <button type="submit" class="configuration-button configuration-danger-button">Exécuter</button>
                    </form>
                </div>
                <template id="configuration-operation-source-<?=$operation_id; ?>">
                    <section class="configuration-result">
                        <h3>Code : <?=configuration_html($operation["title"]); ?></h3>
                        <p class="configuration-muted">Fichier : <code><?=configuration_html($operation["name"]); ?></code></p>
                        <div class="configuration-source"><?=configuration_operation_source($operation); ?></div>
                    </section>
                </template>
            </article>
        <?php } ?>
    </div>
</section>
