<?php

require_once ("campaign_tools.php");
$campaigns = campaign_fetch_all();

if (count($campaigns) == 0) {
?>
    <p>Aucune campagne de prospection.</p>
<?php
}

foreach ($campaigns as $campaign) {
    $prospects = campaign_fetch_prospect($campaign);
    $stats = campaign_build_stats($prospects);
    $id = (int)$campaign["id"];
    $name = htmlspecialchars($campaign["name"] ?? "");
    $description = htmlspecialchars($campaign["description"] ?? "");
?>
    <section class="campaign_card" id="campaign<?=$id; ?>">
        <form
            class="campaign_edit"
            method="put"
            action="/api/campaign/<?=$id; ?>"
            onsubmit="return silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list'});"
        >
            <h2><?=$name; ?></h2>
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
            <input
                type="button"
                value="Mettre à jour"
                onclick="silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list'});"
            />
        </form>

        <form
            class="campaign_delete"
            method="delete"
            action="/api/campaign/<?=$id; ?>"
            onsubmit="return silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list'});"
        >
            <input
                type="button"
                value="Supprimer"
                onclick="confirm('Supprimer cette campagne ?') && silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list'});"
            />
        </form>

        <p class="campaign_period">
            Période : <?=campaign_display_date($campaign["start_date"]); ?> — <?=campaign_display_date($campaign["end_date"]); ?>
        </p>

        <?php if (trim($campaign["description"] ?? "") != "") { ?>
            <p class="campaign_text"><?=nl2br($description); ?></p>
        <?php } ?>

        <div class="campaign_stat_grid">
            <div><strong><?=$stats["total"]; ?></strong><span>Prospects</span></div>
            <div><strong><?=$stats["transformed"]; ?></strong><span>Transformés</span></div>
            <div><strong><?=$stats["lost"]; ?></strong><span>Refusés / perdus</span></div>
            <div><strong><?=$stats["active"]; ?></strong><span>En cours</span></div>
        </div>

        <h3>Origines</h3>
        <table class="campaign_table">
            <tr>
                <th>Origine</th>
                <th>Prospects</th>
                <th>Transformés</th>
                <th>Transformation</th>
                <th>Refusés / perdus</th>
                <th>En cours</th>
            </tr>
            <?php foreach ($stats["origin"] as $origin) { ?>
                <tr>
                    <td><?=htmlspecialchars($origin["origin"]); ?></td>
                    <td><?=$origin["total"]; ?></td>
                    <td><?=$origin["transformed"]; ?></td>
                    <td><?=campaign_rate($origin["transformed"], $origin["total"]); ?></td>
                    <td><?=$origin["lost"]; ?></td>
                    <td><?=$origin["active"]; ?></td>
                </tr>
            <?php } ?>
        </table>

        <details>
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
                        <td><?=campaign_display_date($prospect["registration_date"]); ?></td>
                        <td><?=htmlspecialchars($prospect["campaign_origin"] ?? "Unknown"); ?></td>
                        <td><?=campaign_status_label($prospect["campaign_status"] ?? "active"); ?></td>
                        <td><?=htmlspecialchars($prospect["mail"] ?? ""); ?></td>
                        <td><?=htmlspecialchars(format_phone_number($prospect["phone"] ?? "")); ?></td>
                    </tr>
                <?php } ?>
            </table>
        </details>
    </section>
<?php } ?>
