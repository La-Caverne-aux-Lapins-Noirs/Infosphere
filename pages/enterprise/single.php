<?php
$enterprise_tabs = [
    "Configuration" => __DIR__."/configuration_tab.php",
    "Contacts" => __DIR__."/contacts_tab.php",
];
?>

<section class="enterprise_admin_page" id="enterprise<?=$enterprise["id"]; ?>_panel">
    <div class="enterprise_admin_header">
        <?php $pic = organization_site_logo_path($enterprise, false, false); if ($pic == '' && !empty($enterprise['id_school'])) $pic = school_site_logo_path(['codename' => $enterprise['school_codename']], false, true); else if ($pic == '') $pic = organization_site_logo_path($enterprise, false, true); ?>
        <?php $pic_version = file_exists($pic) ? filemtime($pic) : 0; ?>
        <div class="enterprise_admin_logo" style="background-image: url('<?=$pic; ?>?<?=$pic_version; ?>');"></div>
        <div class="enterprise_admin_title">
            <h2><?=htmlspecialchars($enterprise["name"] ?: $enterprise["codename"]); ?></h2>
            <p>#<?=$enterprise["id"]; ?> — <?=htmlspecialchars($enterprise["codename"]); ?></p>
            <p><a href="/api/enterprise/<?=$enterprise["id"]; ?>/dabsic" target="_blank">description.dab</a></p>
        </div>
    </div>

    <?php tabpanel(
        $enterprise_tabs,
        "enterprise-admin-".$enterprise["id"],
        "Configuration",
        "enterprise-tab-button",
        "enterprise-tab-content"
    ); ?>
</section>
