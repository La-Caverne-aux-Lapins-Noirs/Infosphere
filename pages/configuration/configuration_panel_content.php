<?php
$source = (isset($configuration_panel_data) && is_array($configuration_panel_data) ? $configuration_panel_data : []) + $_POST + $_GET;
$show_hidden_configuration = (int)($source["show_hidden_configuration"] ?? 0) == 1;
$configuration_rows = configuration_rows();
$visible_configuration = [];
$hidden_configuration = [];
foreach ($configuration_rows as $configuration_row)
{
    if (configuration_row_hidden($configuration_row))
        $hidden_configuration[] = $configuration_row;
    else
        $visible_configuration[] = $configuration_row;
}
$displayed_configuration = $visible_configuration;
if ($show_hidden_configuration)
    $displayed_configuration = array_merge($displayed_configuration, $hidden_configuration);
?>
<section class="configuration-card configuration-configuration-card">
    <h2><?=configuration_html($Dictionnary["Configuration"] ?? "Configuration"); ?></h2>
    <div class="configuration-toolbar configuration-compact-toolbar">
        <span><?=count($visible_configuration); ?> champs</span>
        <?php if (count($hidden_configuration)) { ?>
            <form method="post" action="api/configuration/0/fields" onsubmit="return silent_submit(this, 'configuration-fields-panel');">
                <input type="hidden" name="show_hidden_configuration" value="<?=$show_hidden_configuration ? 0 : 1; ?>" />
                <button type="submit" class="configuration-button">
                    <?=$show_hidden_configuration ? "Masquer protégés" : "+".count($hidden_configuration)." protégés"; ?>
                </button>
            </form>
        <?php } ?>
    </div>
    <div class="configuration-fields">
        <?php foreach ($displayed_configuration as $entry) { ?>
            <?php
            $codename = $entry["codename"];
            $kind = configuration_row_kind($entry);
            $description = configuration_row_description($entry);
            $label = configuration_row_label($entry);
            $value = configuration_value_for_edition($entry);
            $form_id = "configuration_".preg_replace('/[^a-zA-Z0-9_\-]/', '_', $codename);
            $short_value = strlen((string)$value) < 96 && strpos((string)$value, "\n") === false;
            ?>
            <details class="configuration-field configuration-field-<?=$kind; ?>">
                <summary>
                    <span>
                        <strong><?=configuration_html($label); ?></strong>
                        <code><?=configuration_html($codename); ?></code>
                    </span>
                    <span class="configuration-badges">
                        <?php if (isset($entry["id"])) { ?><span class="configuration-badge">#<?=configuration_html($entry["id"]); ?></span><?php } ?>
                        <?php if (configuration_row_secured($entry)) { ?><span class="configuration-badge">protégé</span><?php } ?>
                        <?php if (configuration_row_hidden($entry)) { ?><span class="configuration-badge">masqué</span><?php } ?>
                        <?php if (configuration_row_readonly($entry)) { ?><span class="configuration-badge">lecture seule</span><?php } ?>
                    </span>
                </summary>
                <?php if ($description != "") { ?>
                    <p class="configuration-field-description"><?=configuration_html($description); ?></p>
                <?php } ?>
                <?php if (configuration_row_readonly($entry)) { ?>
                    <pre class="configuration-source"><?=configuration_html(configuration_public_value($entry)); ?></pre>
                <?php } else { ?>
                    <form id="<?=$form_id; ?>" action="api/configuration" method="put" onsubmit="return silent_submit(this);">
                        <?php if (configuration_row_secured($entry)) { ?>
                            <textarea
                                name="<?=configuration_html($codename); ?>"
                                placeholder="Valeur protégée : tape une nouvelle valeur pour la remplacer. Laisser vide conserve la valeur actuelle."
                                oninput="delay_before_submit(1000, this, '<?=$form_id; ?>');"
                            ></textarea>
                        <?php } else if ($short_value) { ?>
                            <input
                                type="text"
                                name="<?=configuration_html($codename); ?>"
                                value="<?=configuration_html($value); ?>"
                                oninput="delay_before_submit(1000, this, '<?=$form_id; ?>');"
                            />
                        <?php } else { ?>
                            <textarea
                                name="<?=configuration_html($codename); ?>"
                                oninput="delay_before_submit(1000, this, '<?=$form_id; ?>');"
                            ><?=configuration_html($value); ?></textarea>
                        <?php } ?>
                        <button type="submit" class="configuration-button">Enregistrer</button>
                    </form>
                <?php } ?>
            </details>
        <?php } ?>
    </div>
</section>
