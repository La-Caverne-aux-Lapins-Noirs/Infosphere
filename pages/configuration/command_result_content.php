<?php if (isset($result) && $result != "") { ?>
    <section class="configuration-subcard configuration-result">
        <h3><?=configuration_html($Dictionnary["Results"] ?? "Résultat"); ?></h3>
        <?php if (isset($operation) && is_array($operation)) { ?>
            <p class="configuration-muted">Commande : <code><?=configuration_html($operation["name"]); ?></code></p>
        <?php } ?>
        <pre><?=configuration_html($result); ?></pre>
        <?php if (isset($outfile) && $outfile !== NULL) { ?>
            <a class="configuration-button" download="backup.tar.gz" href="data:application/gzip;base64,<?=base64_encode($outfile); ?>">
                <?=configuration_html($Dictionnary["Download"] ?? "Download"); ?>
            </a>
        <?php } ?>
    </section>
<?php } ?>
