<?php

require_once (__DIR__."/campaign_registration.php");

$campaign = $tab_data["campaign"];
$prospects = campaign_fetch_prospect($campaign);
$stats = campaign_build_stats($prospects);
$id = (int)$campaign["id"];
$name = htmlspecialchars($campaign["name"] ?? "");
$description = htmlspecialchars($campaign["description"] ?? "");
$edit_form_id = "campaign_edit_".$id;
?>

<section class="campaign_card" id="campaign<?=$id; ?>" data-campaign-id="<?=$id; ?>">
    <header class="campaign_panel_header">
        <h2><?=$name; ?></h2>
        <div class="campaign_panel_actions">
            <input
                class="campaign_update_button"
                type="button"
                value="Mettre à jour"
                onclick="silent_submitf(document.getElementById('<?=$edit_form_id; ?>'), {tofill: 'campaign_list', toclear: 'campaign_list', after_success: prospecting_campaign_tabs_after_update});"
            />

            <form
                class="campaign_delete"
                method="delete"
                action="/api/campaign/<?=$id; ?>"
                onsubmit="return silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list', after_success: prospecting_campaign_tabs_after_update});"
            >
                <input
                    class="campaign_delete_button"
                    type="button"
                    value="&#10007;"
                    title="Supprimer la campagne"
                    aria-label="Supprimer la campagne"
                    onclick="confirm('Supprimer cette campagne ?') && silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list', after_success: prospecting_campaign_tabs_after_update});"
                />
            </form>
        </div>
    </header>

    <div class="campaign_overview">
        <div class="campaign_form_block">
            <form
                id="<?=$edit_form_id; ?>"
                class="campaign_edit"
                method="put"
                action="/api/campaign/<?=$id; ?>"
                onsubmit="return silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list', after_success: prospecting_campaign_tabs_after_update});"
            >
                <label>
                    Nom
                    <input type="text" name="name" value="<?=$name; ?>" required />
                </label>
                <label>
                    Début
                    <input type="date" name="start_date" value="<?=htmlspecialchars($campaign["start_date"]); ?>" required />
                </label>
                <label>
                    Fin
                    <input type="date" name="end_date" value="<?=htmlspecialchars($campaign["end_date"]); ?>" required />
                </label>
                <label class="campaign_description">
                    Description
                    <textarea name="description"><?=$description; ?></textarea>
                </label>
            </form>

            <div class="campaign_form_summary">
                <span>Période : <?=campaign_display_date($campaign["start_date"]); ?> — <?=campaign_display_date($campaign["end_date"]); ?></span>
                <?php if (trim($campaign["description"] ?? "") != "") { ?>
                    <span><?=nl2br($description); ?></span>
                <?php } ?>
            </div>
        </div>

        <div class="campaign_stats_panel">
            <h3>Synthèse</h3>
            <div class="campaign_stat_grid">
                <div><strong><?=$stats["total"]; ?></strong><span>Prospects</span></div>
                <div><strong><?=$stats["transformed"]; ?></strong><span>Transformés</span></div>
                <div><strong><?=$stats["lost"]; ?></strong><span>Refusés / perdus</span></div>
                <div><strong><?=$stats["active"]; ?></strong><span>En cours</span></div>
            </div>
        </div>

        <div class="campaign_origins_panel">
            <h3>Origines</h3>
            <div class="campaign_origin_grid">
                <?php foreach ($stats["origin"] as $origin) { ?>
                    <div class="campaign_origin_card">
                        <strong class="campaign_origin_name"><?=htmlspecialchars($origin["origin"]); ?></strong>
                        <dl>
                            <div><dt>Prospects</dt><dd><?=$origin["total"]; ?></dd></div>
                            <div><dt>Transformés</dt><dd><?=$origin["transformed"]; ?></dd></div>
                            <div><dt>Taux</dt><dd><?=campaign_rate($origin["transformed"], $origin["total"]); ?></dd></div>
                            <div><dt>Perdus</dt><dd><?=$origin["lost"]; ?></dd></div>
                            <div><dt>En cours</dt><dd><?=$origin["active"]; ?></dd></div>
                        </dl>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <details class="campaign_prospects">
        <summary>Prospects de la campagne</summary>
        <table class="campaign_table">
            <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Date</th>
                <th>Origine</th>
                <th>Statut</th>
                <th>Mail</th>
                <th>Téléphone</th>
            </tr>
            <?php foreach ($prospects as $prospect) { ?>
                <tr>
                    <td><?=$prospect["id"]; ?></td>
                    <td>
                        <a target="_blank" href="?p=ProfileMenu&amp;a=<?=$prospect["id"]; ?>">
                            <?=htmlspecialchars(trim(($prospect["first_name"] ?? "")." ".($prospect["family_name"] ?? ""))); ?>
                        </a>
                    </td>
                    <td><?=prospecting_campaign_registration_editor($prospect, ""); ?></td>
                    <td><?=htmlspecialchars($prospect["campaign_origin"] ?? "Unknown"); ?></td>
                    <td><?=campaign_status_label($prospect["campaign_status"] ?? "active"); ?></td>
                    <td><?=htmlspecialchars($prospect["mail"] ?? ""); ?></td>
                    <td><?=htmlspecialchars(format_phone_number($prospect["phone"] ?? "")); ?></td>
                </tr>
            <?php } ?>
        </table>
    </details>
</section>
