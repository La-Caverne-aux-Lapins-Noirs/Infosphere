<?php if (isset($result) && $result != "") { ?>
    <section class="configuration-card configuration-result">
        <h2><?=configuration_html($Dictionnary["Results"] ?? "Results"); ?></h2>
        <pre><?=configuration_html($result); ?></pre>
        <?php if (isset($outfile) && $outfile !== NULL) { ?>
            <a class="configuration-button" download="backup.tar.gz" href="data:application/gzip;base64,<?=base64_encode($outfile); ?>">
                <?=configuration_html($Dictionnary["Download"] ?? "Download"); ?>
            </a>
        <?php } ?>
    </section>
<?php } ?>
