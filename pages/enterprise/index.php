<?php
$id = try_get($_GET, "a", -1);
$unique = $id != -1;
$enterprise = [];
if ($unique)
    $enterprise = fetch_enterprises($id);
$enterprises = $unique ? [] : fetch_enterprises();
?>

<style><?php require (__DIR__."/style.css"); ?></style>

<div class="enterprise_page">
    <h1><?=htmlspecialchars($Dictionnary["EnterprisePartners"] ?? "Organisations / sociétés"); ?></h1>

    <?php if ($unique) { ?>
        <?php if (!is_array($enterprise) || !count($enterprise)) { ?>
            <p><?=htmlspecialchars($Dictionnary["NoEnterprise"] ?? "Aucune entreprise partenaire n'a été trouvée."); ?></p>
        <?php } else { ?>
            <p><a href="?p=EnterpriseMenu">&larr; <?=htmlspecialchars($Dictionnary["Back"] ?? "Retour"); ?></a></p>
            <?php require (__DIR__."/single.php"); ?>
        <?php } ?>
    <?php } else { ?>
        <p class="enterprise_add_notice">Les écoles apparaissent aussi ici pour permettre la configuration de leur société / organisation. Les logos nécessaires aux documents restent configurés sur la page École.</p>
        <div class="enterprise_layout">
            <div id="enterprise_list">
                <?php require (__DIR__."/list.php"); ?>
            </div>
            <aside class="enterprise_panel">
                <?php require (__DIR__."/add.php"); ?>
            </aside>
        </div>
    <?php } ?>
</div>
