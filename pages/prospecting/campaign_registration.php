<?php

require_once (__DIR__."/campaign_tools.php");

function prospecting_campaign_registration_campaigns()
{
    static $campaigns = NULL;

    if ($campaigns === NULL)
        $campaigns = campaign_fetch_all_chronological();
    return ($campaigns);
}

function prospecting_campaign_registration_date($registration_date)
{
    $registration_date = (string)$registration_date;

    if (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2})/', $registration_date, $match))
        return ($match[1]);
    return ("");
}

function prospecting_campaign_registration_current_campaign($date, $campaigns)
{
    if (!campaign_date_is_valid($date))
        return (0);

    foreach ($campaigns as $campaign)
        if ($campaign["start_date"] <= $date && $campaign["end_date"] >= $date)
            return ((int)$campaign["id"]);
    return (0);
}

function prospecting_campaign_registration_editor($prospect, $prefix = "Inscrit le ")
{
    $id = (int)($prospect["id"] ?? 0);
    $registration_date = prospecting_campaign_registration_date($prospect["registration_date"] ?? "");
    $campaigns = prospecting_campaign_registration_campaigns();
    $current_campaign = prospecting_campaign_registration_current_campaign($registration_date, $campaigns);
    $display_date = $registration_date != ""
        ? datex("d/m/Y", $prospect["registration_date"])
        : "—";

    ob_start();
    ?>
    <div class="prospect_registration_widget" data-prospect-id="<?=$id; ?>">
        <button
            type="button"
            class="prospect_registration_toggle"
            title="Changer de campagne"
            aria-label="Changer la campagne du prospect"
        ><?=htmlspecialchars($prefix.$display_date); ?></button>
        <form
            class="prospect_registration_form"
            method="put"
            action="/api/prospect/<?=$id; ?>/campaign"
            onsubmit="return prospecting_campaign_registration_submit(this);"
        >
            <select name="campaign_id" aria-label="Campagne" required>
                <option value="">Campagne…</option>
                <?php foreach ($campaigns as $campaign) {
                    $campaign_id = (int)$campaign["id"];
                    $selected = $campaign_id == $current_campaign ? " selected" : "";
                ?>
                    <option
                        value="<?=$campaign_id; ?>"
                        data-start="<?=htmlspecialchars($campaign["start_date"]); ?>"
                        data-end="<?=htmlspecialchars($campaign["end_date"]); ?>"
                        <?=$selected; ?>
                    ><?=htmlspecialchars($campaign["name"] ?? "Campagne"); ?></option>
                <?php } ?>
            </select>
            <input
                type="date"
                name="registration_date"
                value="<?=htmlspecialchars($registration_date); ?>"
                aria-label="Date d'inscription"
                required
            />
            <button type="submit" class="prospect_registration_confirm" title="Déplacer" aria-label="Déplacer">&#10003;</button>
            <button type="button" class="prospect_registration_cancel" title="Annuler" aria-label="Annuler">&#10007;</button>
        </form>
    </div>
    <?php
    return (ob_get_clean());
}
