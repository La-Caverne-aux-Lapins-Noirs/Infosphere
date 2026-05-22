<?php
$total = disk_total_space('./');
$free = disk_free_space('./');
$used = max(0, $total - $free);
$used_ratio = $total > 0 ? $used / $total : 0;
$free_ratio = $total > 0 ? $free / $total : 1;
$storage_class = $free_ratio <= 0.10 ? "configuration-storage-critical" : ($free_ratio <= 0.20 ? "configuration-storage-warning" : "");
$alerts = retrieve_alerts();
$last_albedo_stop = configuration_last_log_message("Albedo stops.");
$last_hand_ping = distrans_last_running_log(1, defined("TRACE") ? TRACE : "0");
$recent_log_stats = configuration_recent_log_stats(24);
$log_type_labels = configuration_log_type_labels();
?>
<section class="configuration-card configuration-stats-card">
    <header class="configuration-card-header">
        <div>
            <h2>Stats et alertes</h2>
            <p class="configuration-field-description">
                Vue rapide de l'état serveur, des alertes administratives et de l'activité récente.
            </p>
        </div>
    </header>

    <?php if (is_array($alerts) && count($alerts)) { ?>
        <div class="configuration-alert-list">
            <?php foreach ($alerts as $alert_key => $alert_message) { ?>
                <div class="configuration-alert">
                    <strong><?=configuration_html($alert_key); ?></strong>
                    <?=$alert_message; ?>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="configuration-ok-box">
            Aucune alerte administrative remontée actuellement.
        </div>
    <?php } ?>

    <div class="configuration-stats-grid">
        <section class="configuration-subcard configuration-storage-card configuration-wide-subcard <?=$storage_class; ?>">
            <h3><?=configuration_html($Dictionnary["Storage"] ?? "Stockage"); ?></h3>
            <div class="configuration-progress" title="<?=intval($used_ratio * 100); ?>% utilisé">
                <div style="width: <?=intval($used_ratio * 100); ?>%;"></div>
            </div>
            <?php if ($free_ratio <= 0.20) { ?>
                <p class="configuration-storage-alert">
                    Espace disque faible : <?=intval($free_ratio * 100); ?>% restant.
                </p>
            <?php } ?>
            <div class="configuration-stat-strip configuration-storage-details">
                <div class="configuration-stat">
                    <strong><?=configuration_html(configuration_format_bytes($free)); ?></strong><br />
                    <?=configuration_html($Dictionnary["remaining"] ?? "restant"); ?>
                </div>
                <div class="configuration-stat">
                    <strong><?=configuration_html(configuration_format_bytes($used)); ?></strong><br />
                    utilisé
                </div>
                <div class="configuration-stat">
                    <strong><?=configuration_html(configuration_format_bytes($total)); ?></strong><br />
                    total
                </div>
            </div>
        </section>

        <section class="configuration-subcard">
            <h3>Services surveillés</h3>
            <dl class="configuration-definition-list">
                <dt>Dernier arrêt Albedo</dt>
                <dd>
                    <?php if ($last_albedo_stop) { ?>
                        <?=configuration_html(human_date($last_albedo_stop["log_date"])); ?>
                    <?php } else { ?>
                        <span class="configuration-muted">jamais vu</span>
                    <?php } ?>
                </dd>
                <dt>Dernier ping Distrans/Hand</dt>
                <dd>
                    <?php if ($last_hand_ping) { ?>
                        <?=configuration_html(human_date($last_hand_ping["log_date"])); ?>
                    <?php } else { ?>
                        <span class="configuration-muted">jamais vu</span>
                    <?php } ?>
                </dd>
                <dt>PHP</dt>
                <dd><?=configuration_html(PHP_VERSION); ?></dd>
                <dt>Serveur</dt>
                <dd><?=configuration_html($_SERVER["SERVER_SOFTWARE"] ?? php_uname("s")); ?></dd>
            </dl>
        </section>

        <section class="configuration-subcard configuration-wide-subcard">
            <h3>Logs sur 24h</h3>
            <?php if (!count($recent_log_stats)) { ?>
                <p class="configuration-muted">Aucun log sur les dernières 24 heures.</p>
            <?php } else { ?>
                <div class="configuration-stat-strip">
                    <?php foreach ($recent_log_stats as $stat) { ?>
                        <?php $type = (string)$stat["type"]; ?>
                        <div class="configuration-stat">
                            <strong><?=configuration_html($stat["cnt"]); ?></strong><br />
                            <code><?=configuration_html($type); ?></code>
                            <span class="configuration-muted"><?=configuration_html($log_type_labels[$type] ?? ""); ?></span>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
    </div>
</section>
