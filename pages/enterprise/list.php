<?php if (!isset($enterprises)) $enterprises = fetch_enterprises(); ?>
<?php if (is_object($enterprises) && $enterprises->is_error()) { ?>
    <?=htmlspecialchars(strval($enterprises)); ?>
<?php } else if (!count($enterprises)) { ?>
    <p><?=htmlspecialchars($Dictionnary["NoEnterprise"] ?? "Aucune organisation n'a été trouvée."); ?></p>
<?php } else { ?>
    <table class="content_table enterprise_table">
        <thead>
            <tr>
                <th>#</th>
                <th>Logo</th>
                <th>Nom de code</th>
                <th>Nom</th>
                <th>Type</th>
                <th>Nom légal</th>
                <th>SIRET</th>
                <th>Contacts</th>
            </tr>
        </thead>
        <tbody>
            <?php $cnt = 0; ?>
            <?php foreach ($enterprises as $enterprise) { ?>
                <?php $URL = "?p=EnterpriseMenu&amp;a=".$enterprise["id"]; ?>
                <tr class="content_<?=$cnt++ % 2 ? "even" : "odd"; ?>" id="enterprise<?=$enterprise["id"]; ?>">
                    <td <?=clickable($URL); ?>><?=$enterprise["id"]; ?></td>
                    <td <?=clickable($URL); ?>>
                        <?php $pic = organization_site_logo_path($enterprise, false, false); if ($pic == '' && !empty($enterprise['id_school'])) $pic = school_site_logo_path(['codename' => $enterprise['school_codename']], false, true); else if ($pic == '') $pic = organization_site_logo_path($enterprise, false, true); ?>
                        <?php $pic_version = file_exists($pic) ? filemtime($pic) : 0; ?>
                        <div class="enterprise_table_logo" style="background-image: url('<?=$pic; ?>?<?=$pic_version; ?>');"></div>
                    </td>
                    <td <?=clickable($URL); ?>><?=htmlspecialchars($enterprise["codename"]); ?></td>
                    <td <?=clickable($URL); ?>><?=htmlspecialchars($enterprise["name"] ?: ($enterprise["fr_name"] ?: $enterprise["codename"])); ?></td>
                    <td>
                        <?=($enterprise["type"] ?? "enterprise") == "school" ? "École" : "Société"; ?>
                        <?php if (!empty($enterprise["id_school"])) { ?>
                            <br /><a href="?p=SchoolMenu&amp;a=<?=$enterprise["id_school"]; ?>">fiche école</a>
                        <?php } ?>
                    </td>
                    <td <?=clickable($URL); ?>><?=htmlspecialchars($enterprise["legal_name"] ?? ""); ?></td>
                    <td <?=clickable($URL); ?>><?=htmlspecialchars(enterprise_siret($enterprise)); ?></td>
                    <td <?=clickable($URL); ?>><?=count($enterprise["contacts"]); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php } ?>
