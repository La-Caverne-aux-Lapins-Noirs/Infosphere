<?php
$source = (isset($configuration_panel_data) && is_array($configuration_panel_data) ? $configuration_panel_data : []) + $_POST + $_GET;
$quote_sort = $source["quote_sort"] ?? "id";
$quote_direction = $source["quote_direction"] ?? "asc";
$quote_options = famous_quote_sort_options();
if (!isset($quote_options[$quote_sort]))
    $quote_sort = "id";
if ($quote_direction != "desc")
    $quote_direction = "asc";
$quotes = famous_quote_list($quote_sort, $quote_direction);
?>
<section class="configuration-card configuration-quotes-card">
    <div class="configuration-card-header">
        <div>
            <h2>Citations</h2>
            <p class="configuration-muted">
                <?=count($quotes); ?> citations actives. La rotation quotidienne utilise <code>configuration.last_quote</code> et l'ordre stable des identifiants.
            </p>
        </div>
        <form method="post" action="api/configuration/0/quotes" class="configuration-quote-sort" onsubmit="return silent_submit(this, 'configuration-quotes-panel');">
            <label>
                Tri
                <select name="quote_sort">
                    <?php foreach ($quote_options as $value => $label) { ?>
                        <option value="<?=configuration_html($value); ?>" <?=$quote_sort == $value ? "selected" : ""; ?>><?=configuration_html($label); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                Sens
                <select name="quote_direction">
                    <option value="asc" <?=$quote_direction == "asc" ? "selected" : ""; ?>>croissant</option>
                    <option value="desc" <?=$quote_direction == "desc" ? "selected" : ""; ?>>décroissant</option>
                </select>
            </label>
            <button type="submit" class="configuration-button">Trier</button>
        </form>
    </div>

    <details class="configuration-subcard configuration-quote-add">
        <summary><strong>Ajouter une citation</strong></summary>
        <form method="post" action="api/configuration/0/quote" class="configuration-quote-form" onsubmit="return silent_submit(this, 'configuration-quotes-panel', null, null, null, null, '', false, true);">
            <input type="hidden" name="id" value="0" />
            <input type="hidden" name="quote_sort" value="<?=configuration_html($quote_sort); ?>" />
            <input type="hidden" name="quote_direction" value="<?=configuration_html($quote_direction); ?>" />
            <label>
                Citation FR
                <textarea name="fr"></textarea>
            </label>
            <label>
                Citation EN
                <textarea name="en"></textarea>
            </label>
            <label>
                Auteur/autrice
                <input type="text" name="author" value="" />
            </label>
            <button type="submit" class="configuration-button">Ajouter</button>
        </form>
    </details>

    <div class="configuration-quote-list">
        <?php foreach ($quotes as $quote) { ?>
            <details class="configuration-quote-row">
                <summary>
                    <span class="configuration-quote-summary-main">
                        #<?=configuration_html($quote["id"]); ?>
                        <?=configuration_html(famous_quote_text($quote)); ?>
                    </span>
                    <span class="configuration-badges">
                        <?php if (trim((string)$quote["author"]) != "") { ?>
                            <span class="configuration-badge"><?=configuration_html($quote["author"]); ?></span>
                        <?php } ?>
                        <span class="configuration-badge">note <?=configuration_html($quote["score"]); ?></span>
                        <span class="configuration-badge"><?=configuration_html($quote["vote_count"]); ?> votes</span>
                        <span class="configuration-badge">+<?=configuration_html($quote["up_count"]); ?> / -<?=configuration_html($quote["down_count"]); ?></span>
                    </span>
                </summary>
                <form method="post" action="api/configuration/0/quote" class="configuration-quote-form" onsubmit="return silent_submit(this, 'configuration-quotes-panel');">
                    <input type="hidden" name="id" value="<?=configuration_html($quote["id"]); ?>" />
                    <input type="hidden" name="quote_sort" value="<?=configuration_html($quote_sort); ?>" />
                    <input type="hidden" name="quote_direction" value="<?=configuration_html($quote_direction); ?>" />
                    <label>
                        Citation FR
                        <textarea name="fr"><?=configuration_html($quote["fr"]); ?></textarea>
                    </label>
                    <label>
                        Citation EN
                        <textarea name="en"><?=configuration_html($quote["en"]); ?></textarea>
                    </label>
                    <label>
                        Auteur/autrice
                        <input type="text" name="author" value="<?=configuration_html($quote["author"]); ?>" />
                    </label>
                    <div class="configuration-quote-actions">
                        <button type="submit" class="configuration-button">Enregistrer</button>
                    </div>
                </form>
                <form method="post" action="api/configuration/0/quote_delete" class="configuration-quote-delete" onsubmit="return confirm('Retirer cette citation ?') && silent_submit(this, 'configuration-quotes-panel');">
                    <input type="hidden" name="quote" value="<?=configuration_html($quote["id"]); ?>" />
                    <input type="hidden" name="quote_sort" value="<?=configuration_html($quote_sort); ?>" />
                    <input type="hidden" name="quote_direction" value="<?=configuration_html($quote_direction); ?>" />
                    <button type="submit" class="configuration-button configuration-danger-button">Retirer</button>
                </form>
            </details>
        <?php } ?>
        <?php if (!count($quotes)) { ?>
            <p class="configuration-muted">Aucune citation active.</p>
        <?php } ?>
    </div>
</section>
