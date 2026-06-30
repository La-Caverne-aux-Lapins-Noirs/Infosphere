<?php
$source = (isset($configuration_panel_data) && is_array($configuration_panel_data) ? $configuration_panel_data : []) + $_POST + $_GET;
$log_filters = configuration_log_filters($source);
$log_page = $log_filters["page"];
$log_size = $log_filters["size"];
$logs = fetch_log($log_page, $log_filters, $log_size);
$log_count = fetch_log_count($log_filters);
$log_type_labels = configuration_log_type_labels();
?>
<section class="configuration-card configuration-logs-card">
    <header class="configuration-card-header">
        <div>
            <h2><?=configuration_html($Dictionnary["Logs"] ?? "Logs"); ?></h2>
            <p class="configuration-field-description">
                Recherche et filtrage des traces, actions utilisateur, opérations critiques et rapports. Les logs de démarrage/arrêt d'Albedo sont volontairement masqués.
            </p>
        </div>
        <div class="configuration-log-count">
            <?=configuration_html($log_count); ?> entrée<?=($log_count > 1 ? "s" : ""); ?>
        </div>
    </header>

    <form method="post" action="api/configuration/0/logs" class="configuration-log-filters" onsubmit="return silent_submit(this, 'configuration-logs-panel');">
        <input type="hidden" name="log_page" value="0" />
        <input type="hidden" name="log_size" value="<?=configuration_html($log_filters["size"]); ?>" />

        <label class="configuration-filter-search">
            Message / URL
            <input type="text" name="log_search" value="<?=configuration_html($log_filters["search"]); ?>" placeholder="texte libre" />
        </label>
        <label class="configuration-filter-user">
            Utilisateur
            <input type="text" name="log_user" value="<?=configuration_html($log_filters["user"]); ?>" placeholder="id ou codename" />
        </label>
        <label class="configuration-filter-type">
            Type
            <select name="log_type">
                <option value="">Tous</option>
                <?php foreach ($log_type_labels as $type_id => $type_label) { ?>
                    <option value="<?=configuration_html($type_id); ?>" <?=$log_filters["type"] !== "" && (string)$log_filters["type"] === (string)$type_id ? "selected" : ""; ?>>
                        <?=configuration_html($type_id." — ".$type_label); ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <label class="configuration-filter-ip">
            IP / hash
            <input type="text" name="log_ip" value="<?=configuration_html($log_filters["ip"]); ?>" placeholder="IP ou hash" />
        </label>
        <label class="configuration-filter-url">
            URL / urlhash
            <input type="text" name="log_url" value="<?=configuration_html($log_filters["url"]); ?>" placeholder="fragment ou hash" />
        </label>
        <label class="configuration-filter-date">
            Depuis
            <input type="datetime-local" name="log_from" value="<?=configuration_html(str_replace(" ", "T", $log_filters["from"])); ?>" />
        </label>
        <label class="configuration-filter-date">
            Jusqu'à
            <input type="datetime-local" name="log_to" value="<?=configuration_html(str_replace(" ", "T", $log_filters["to"])); ?>" />
        </label>
        <div class="configuration-filter-actions">
            <button type="submit">Filtrer</button>
            <button type="button" class="configuration-button" onclick="return configurationResetLogs(this.form);">Reset</button>
        </div>
    </form>

    <div class="configuration-log-pager">
        <?php if ($log_page > 0) { ?>
            <form method="post" action="api/configuration/0/logs" onsubmit="return silent_submit(this, 'configuration-logs-panel');">
                <?php configuration_log_hidden_inputs($log_filters, ["log_page" => max(0, $log_page - 1)]); ?>
                <button type="submit" class="configuration-button">&larr; précédents</button>
            </form>
        <?php } ?>
        <span><?=configuration_html(($log_count ? $log_page * $log_size + 1 : 0)."-".min($log_count, ($log_page + 1) * $log_size)." / ".$log_count); ?></span>
        <?php if (($log_page + 1) * $log_size < $log_count) { ?>
            <form method="post" action="api/configuration/0/logs" onsubmit="return silent_submit(this, 'configuration-logs-panel');">
                <?php configuration_log_hidden_inputs($log_filters, ["log_page" => $log_page + 1]); ?>
                <button type="submit" class="configuration-button">suivants &rarr;</button>
            </form>
        <?php } ?>
    </div>

    <div class="configuration-log-table-wrap">
        <table class="configuration-log-table">
            <colgroup>
                <col class="configuration-log-col-id" />
                <col class="configuration-log-col-date" />
                <col class="configuration-log-col-user" />
                <col class="configuration-log-col-type" />
                <col class="configuration-log-col-message" />
                <col class="configuration-log-col-ip" />
                <col class="configuration-log-col-url" />
            </colgroup>
            <tr>
                <th class="configuration-log-id">#</th>
                <th><?=configuration_html($Dictionnary["Date"] ?? "Date"); ?></th>
                <th><?=configuration_html($Dictionnary["User"] ?? "User"); ?></th>
                <th><?=configuration_html($Dictionnary["Type"] ?? "Type"); ?></th>
                <th><?=configuration_html($Dictionnary["Message"] ?? "Message"); ?></th>
                <th>IP</th>
                <th>URL</th>
            </tr>
            <?php foreach ($logs as $log) { ?>
                <tr>
                    <td class="configuration-log-id"><strong><?=configuration_html($log["id"]); ?></strong></td>
                    <td class="configuration-log-date"><?=configuration_html(human_date($log["date"])); ?></td>
                    <td class="configuration-log-user">
                        <strong>#<?=configuration_html($log["id_user"]); ?></strong>
                        <code><?=configuration_html($log["user"] ?? ""); ?></code>
                    </td>
                    <td class="configuration-log-type">
                        <code><?=configuration_html($log["type"]); ?></code>
                        <span class="configuration-muted"><?=configuration_html($log_type_labels[(string)$log["type"]] ?? ""); ?></span>
                    </td>
                    <td class="configuration-log-message"><?=configuration_html($log["message"]); ?></td>
                    <td class="configuration-log-ip"><code><?=configuration_html($log["ip"]); ?></code></td>
                    <td class="configuration-log-url">
                        <?php if (($log["url"] ?? "") != "") { ?>
                            <details>
                                <summary><?=configuration_html($log["urlhash"]); ?></summary>
                                <code><?=configuration_html($log["url"]); ?></code>
                            </details>
                        <?php } else { ?>
                            <code><?=configuration_html($log["urlhash"] ?? ""); ?></code>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
</section>
