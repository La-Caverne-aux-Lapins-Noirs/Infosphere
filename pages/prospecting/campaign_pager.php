<?php
require_once (__DIR__."/campaign_tools.php");
$prospecting_campaigns = campaign_fetch_all_chronological();
?>
<section class="prospecting_campaign_pager" id="prospecting_campaign_pager">
    <input
        type="button"
        value="◀ Campagne précédente"
        onclick="prospecting_campaign_move(-1);"
    />
    <select id="prospecting_campaign_select" onchange="prospecting_campaign_select(this.value);">
        <?php foreach ($prospecting_campaigns as $campaign) { ?>
            <?php
            $id = (int)$campaign["id"];
            $label = trim($campaign["name"] ?? "");
            if ($label == "")
                $label = "Campagne #$id";
            $label .= " — ".campaign_display_date($campaign["start_date"])." → ".campaign_display_date($campaign["end_date"]);
            ?>
            <option value="<?=$id; ?>"><?=htmlspecialchars($label); ?></option>
        <?php } ?>
    </select>
    <input
        type="button"
        value="Campagne suivante ▶"
        onclick="prospecting_campaign_move(1);"
    />
    <span id="prospecting_campaign_summary" class="prospecting_campaign_summary"></span>
    <div class="prospecting_status_filters" aria-label="Affichage des prospects terminés">
        <label class="prospecting_status_filter prospecting_status_filter_completed" title="Afficher ou masquer les prospects terminés">
            <input
                type="checkbox"
                id="prospecting_show_completed"
                checked
                onchange="prospecting_status_filter_change();"
            />
            Terminés
        </label>
        <label class="prospecting_status_filter prospecting_status_filter_lost" title="Afficher ou masquer les prospects refusés ou perdus">
            <input
                type="checkbox"
                id="prospecting_show_lost"
                checked
                onchange="prospecting_status_filter_change();"
            />
            Refusés / perdus
        </label>
    </div>
</section>

<div id="prospecting_campaign_table" class="prospecting_campaign_table">
    <?php if (count($prospecting_campaigns) == 0) { ?>
        <p>Aucune campagne de prospection n'est définie.</p>
    <?php } else { ?>
        <p>Chargement de la campagne...</p>
    <?php } ?>
</div>

<script>
    window.prospecting_campaigns = <?=json_encode(array_map(function($campaign) {
        return ([
            "id" => (int)$campaign["id"],
            "name" => $campaign["name"],
            "start_date" => $campaign["start_date"],
            "end_date" => $campaign["end_date"],
        ]);
    }, $prospecting_campaigns), JSON_UNESCAPED_SLASHES); ?>;

    prospecting_status_filter_init();
    prospecting_campaign_init();
</script>
