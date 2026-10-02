<?php
$persoc_deadlist_status = persoc_deadlist_status(true);
$persoc_deadlist_local = $persoc_deadlist_status["local"];
$persoc_deadlist_remote = $persoc_deadlist_status["remote"];
$persoc_deadlist_text = implode("\n", $persoc_deadlist_local);
if (isset($configuration_panel_data["deadlist_input"]))
    $persoc_deadlist_text = (string)$configuration_panel_data["deadlist_input"];
else if ($persoc_deadlist_text != "")
    $persoc_deadlist_text .= "\n";
$persoc_deadlist_capacity = $persoc_deadlist_status["capacity"];
$persoc_deadlist_capacity_label = $persoc_deadlist_capacity == PHP_INT_MAX
    ? "non limitée" : number_format($persoc_deadlist_capacity, 0, ",", " ")." caractères";
?>
<section class="configuration-card configuration-persoc-blacklist-card">
    <div class="configuration-card-header">
        <div>
            <h2>Blacklist Persoc</h2>
            <p class="configuration-muted">
                L'Infosphère conserve la liste ordonnée dans <code>configuration.persoc_deadlist</code>,
                la publie vers Distrans avec <code>set_deadlist</code>, puis les Persoc la récupèrent avec
                <code>get_deadlist</code>.
            </p>
        </div>
        <?php if (!$persoc_deadlist_status["initialized"]) { ?>
            <span class="configuration-persoc-status configuration-persoc-status-warning">Non initialisée</span>
        <?php } else if (!$persoc_deadlist_status["remote_ok"]) { ?>
            <span class="configuration-persoc-status configuration-persoc-status-error">Distrans indisponible</span>
        <?php } else if ($persoc_deadlist_status["synchronized"]) { ?>
            <span class="configuration-persoc-status configuration-persoc-status-ok">Synchronisée</span>
        <?php } else { ?>
            <span class="configuration-persoc-status configuration-persoc-status-warning">À synchroniser</span>
        <?php } ?>
    </div>

    <?php if (!$persoc_deadlist_status["initialized"]) { ?>
        <div class="configuration-alert configuration-persoc-warning">
            La valeur <code>persoc_deadlist</code> n'est pas encore initialisée dans la table
            <code>configuration</code>.
            <?php if ($persoc_deadlist_status["remote_ok"] && count($persoc_deadlist_remote)) { ?>
                Distrans contient déjà <?=count($persoc_deadlist_remote); ?> entrée(s) : importe-les avant
                toute première sauvegarde si tu souhaites les conserver.
            <?php } else { ?>
                La première sauvegarde créera ou initialisera automatiquement cette valeur.
            <?php } ?>
        </div>
    <?php } ?>

    <?php if (!$persoc_deadlist_status["remote_ok"]) { ?>
        <div class="configuration-alert">
            Impossible de lire la blacklist actuellement présente sur Distrans :
            <?=configuration_html($persoc_deadlist_status["remote_error"]); ?>.
            La liste locale reste modifiable et pourra être resynchronisée plus tard.
        </div>
    <?php } else if ($persoc_deadlist_status["initialized"] && !$persoc_deadlist_status["synchronized"]) { ?>
        <div class="configuration-alert configuration-persoc-warning">
            Distrans ne contient pas exactement la liste ordonnée par l'Infosphère.
            Une sauvegarde ou le bouton « Resynchroniser » republiera la liste locale.
        </div>
    <?php } ?>

    <div class="configuration-stat-strip configuration-persoc-stats">
        <div class="configuration-stat">
            <span>Infosphère</span>
            <strong><?=count($persoc_deadlist_local); ?></strong>
            <span>entrée(s)</span>
        </div>
        <div class="configuration-stat">
            <span>Distrans</span>
            <strong><?=$persoc_deadlist_status["remote_ok"] ? count($persoc_deadlist_remote) : "—"; ?></strong>
            <span>entrée(s)</span>
        </div>
        <div class="configuration-stat">
            <span>Stockage</span>
            <strong><?=number_format($persoc_deadlist_status["local_size"], 0, ",", " "); ?></strong>
            <span>/ <?=configuration_html($persoc_deadlist_capacity_label); ?></span>
        </div>
        <div class="configuration-stat">
            <span>Distribution</span>
            <strong>Distrans → Persoc</strong>
            <span>via <code>get_deadlist</code></span>
        </div>
    </div>

    <form
        class="configuration-persoc-blacklist-form"
        method="post"
        action="api/configuration/0/persoc_blacklist_save"
        onsubmit="return silent_submit(this, 'configuration-persoc-blacklist-panel');"
    >
        <label>
            <strong>Sites, hôtes ou adresses IP bloqués</strong>
            <span class="configuration-muted">
                Une entrée par ligne. Les lignes vides et celles commençant par <code>#</code> sont ignorées.
                Les doublons sont supprimés en conservant l'ordre. Les URL complètes ne sont pas acceptées :
                utilise par exemple <code>youtube.com</code>, pas <code>https://youtube.com/</code>.
            </span>
            <textarea name="deadlist" spellcheck="false" rows="18"><?=configuration_html($persoc_deadlist_text); ?></textarea>
        </label>
        <div class="configuration-persoc-actions">
            <button type="submit" class="configuration-button">Enregistrer et synchroniser</button>
        </div>
    </form>

    <div class="configuration-persoc-secondary-actions">
        <?php if ($persoc_deadlist_status["initialized"]) { ?>
            <form
                method="post"
                action="api/configuration/0/persoc_blacklist_sync"
                onsubmit="return silent_submit(this, 'configuration-persoc-blacklist-panel');"
            >
                <button type="submit" class="configuration-button">Resynchroniser vers Distrans</button>
            </form>
        <?php } ?>
        <?php if ($persoc_deadlist_status["remote_ok"] && !$persoc_deadlist_status["synchronized"]) { ?>
            <form
                method="post"
                action="api/configuration/0/persoc_blacklist_import"
                onsubmit="return confirm('Remplacer la liste locale par la blacklist actuellement servie par Distrans ?') && silent_submit(this, 'configuration-persoc-blacklist-panel');"
            >
                <button type="submit" class="configuration-button">Importer depuis Distrans</button>
            </form>
        <?php } ?>
        <form
            method="post"
            action="api/configuration/0/persoc_blacklist"
            onsubmit="return silent_submit(this, 'configuration-persoc-blacklist-panel');"
        >
            <button type="submit" class="configuration-button">Rafraîchir l'état</button>
        </form>
    </div>

    <?php if ($persoc_deadlist_status["remote_ok"] && !$persoc_deadlist_status["synchronized"]) { ?>
        <details class="configuration-persoc-remote-list">
            <summary>Voir la liste actuellement servie par Distrans</summary>
            <pre class="configuration-source"><?=configuration_html(implode("\n", $persoc_deadlist_remote)); ?></pre>
        </details>
    <?php } ?>
</section>
