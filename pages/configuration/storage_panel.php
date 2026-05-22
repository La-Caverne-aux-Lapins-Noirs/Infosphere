<?php
$total = disk_total_space('./');
$free = disk_free_space('./');
$used = max(0, $total - $free);
$used_ratio = $total > 0 ? $used / $total : 0;
$free_ratio = $total > 0 ? $free / $total : 1;
$storage_class = $free_ratio <= 0.10 ? "configuration-storage-critical" : ($free_ratio <= 0.20 ? "configuration-storage-warning" : "");
?>
<section class="configuration-card configuration-storage-card <?=$storage_class; ?>">
    <h2><?=configuration_html($Dictionnary["Storage"] ?? "Storage"); ?></h2>
    <?php if ($free_ratio <= 0.20) { ?>
        <p class="configuration-storage-alert">
            Espace disque faible : <?=intval($free_ratio * 100); ?>% restant.
        </p>
    <?php } ?>
    <div class="configuration-progress" title="<?=intval($used_ratio * 100); ?>%">
        <div style="width: <?=intval($used_ratio * 100); ?>%;"></div>
    </div>
    <div class="configuration-grid" style="margin-top: 12px;">
        <div class="configuration-stat">
            <strong><?=configuration_html(configuration_format_bytes($free)); ?></strong><br />
            <?=configuration_html("restant"); ?>
        </div>
        <div class="configuration-stat">
            <strong><?=configuration_html(configuration_format_bytes($used)); ?></strong><br />
            <?=configuration_html("utilisé"); ?>
        </div>
        <div class="configuration-stat">
            <strong><?=configuration_html(configuration_format_bytes($total)); ?></strong><br />
            <?=configuration_html("total"); ?>
        </div>
    </div>
</section>
