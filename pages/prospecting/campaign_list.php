<?php

require_once ("campaign_tools.php");

// Les campagnes sont affichées dans l'ordre chronologique naturel :
// anciennes campagnes à gauche, campagnes récentes et futures à droite.
$campaigns = campaign_fetch_all_chronological();
$campaign_panels = [];
$campaign_panel_data = [];
$campaign_labels = [];
$today = date("Y-m-d");
$campaign_selected_id = isset($campaign_selected_id) ? (int)$campaign_selected_id : 0;
$default_campaign_id = 0;
$first_future_campaign_id = 0;
$last_campaign_id = 0;

foreach ($campaigns as $campaign)
{
    $id = (int)$campaign["id"];
    $name = trim((string)($campaign["name"] ?? ""));

    if ($name == "")
        $name = "Campagne";

    // Le span masqué rend la clé du tabpanel unique même si deux campagnes
    // portent le même nom, sans exposer l'identifiant dans l'interface.
    $label = htmlspecialchars($name).'<span class="campaign_tab_identity">#'.$id.'</span>';

    $campaign_labels[$id] = $label;
    $campaign_panels[$label] = __DIR__."/campaign_tab.php";
    $campaign_panel_data[] = ["campaign" => $campaign];
    $last_campaign_id = $id;

    if ($campaign["start_date"] <= $today && $campaign["end_date"] >= $today)
        $default_campaign_id = $id;
    else if ($first_future_campaign_id == 0 && $campaign["start_date"] > $today)
        $first_future_campaign_id = $id;
}

if ($campaign_selected_id > 0 && isset($campaign_labels[$campaign_selected_id]))
    $default_campaign_id = $campaign_selected_id;
else if ($default_campaign_id == 0 && $first_future_campaign_id > 0)
    $default_campaign_id = $first_future_campaign_id;
else if ($default_campaign_id == 0)
    $default_campaign_id = $last_campaign_id;

$add_label = '<span class="campaign_tab_add_label">+ Nouvelle campagne</span>';
$campaign_panels[$add_label] = __DIR__."/campaign_add.php";
$campaign_panel_data[] = NULL;
$default_tab = $default_campaign_id > 0 ? $campaign_labels[$default_campaign_id] : $add_label;
?>

<div class="campaign_tabs">
    <?php tabpanel(
        $campaign_panels,
        "prospecting-campaigns",
        $default_tab,
        "campaign_tab_button",
        "campaign_tab_content",
        $campaign_panel_data
    ); ?>
</div>
