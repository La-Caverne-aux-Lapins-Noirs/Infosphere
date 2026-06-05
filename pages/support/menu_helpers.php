<?php

function support_menu_escape($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"));
}

function support_menu_can_display_technical_labels()
{
    return (function_exists("can_edit_supports") && can_edit_supports());
}

function support_menu_mark_empty_supports(&$categories)
{
    global $Language;

    $ids = [];

    foreach ($categories as $category)
    {
	if (!isset($category["support"]) || !is_array($category["support"]))
	    continue ;
	foreach ($category["support"] as $support)
	    if (isset($support["id"]))
		$ids[] = (int)$support["id"];
    }
    $ids = array_values(array_unique(array_filter($ids, function ($id) {
	return ($id > 0);
    })));
    if (count($ids) == 0)
	return ;

    $idlist = implode(",", $ids);
    $counts = db_select_all("
        id_support, COUNT(*) as cnt
        FROM support_asset
        WHERE deleted IS NULL
        AND id_support IN ($idlist)
        GROUP BY id_support
    ", "id_support");

    $unseen = [];
    if (function_exists("support_progress_should_show_student_marks") &&
	support_progress_should_show_student_marks())
    {
	$id_user = support_progress_current_user_id();
	$unseen = db_select_all("
            support_asset.id_support as id_support,
            COUNT(*) as cnt
            FROM support_asset
            LEFT JOIN support_asset_view
              ON support_asset_view.id_support_asset = support_asset.id
             AND support_asset_view.id_user = $id_user
            WHERE support_asset.deleted IS NULL
              AND support_asset.id_support IN ($idlist)
              AND TRIM(COALESCE(support_asset.{$Language}_content, '')) != ''
              AND (support_asset_view.id IS NULL OR support_asset_view.progress <= 0)
            GROUP BY support_asset.id_support
        ", "id_support");
    }

    foreach ($categories as &$category)
    {
	if (!isset($category["support"]) || !is_array($category["support"]))
	    continue ;
	foreach ($category["support"] as &$support)
	{
	    $id = isset($support["id"]) ? (int)$support["id"] : -1;
	    $support["asset_count"] = isset($counts[$id]) ? (int)$counts[$id]["cnt"] : 0;
	    $support["unseen_asset_count"] = isset($unseen[$id]) ? (int)$unseen[$id]["cnt"] : 0;
	}
    }
}

function support_codename_prefix_from_entry($entry)
{
    if (isset($entry["prefix"]))
	return ((string)$entry["prefix"]);
    if (isset($entry["type"]) && $entry["type"] == "category")
	return ("@");
    return ("");
}

function support_prefixed_codename($codename, $prefix = "")
{
    $codename = trim((string)$codename);
    $prefix = (string)$prefix;

    if ($codename == "" || $prefix == "")
	return ($codename);
    if (substr($codename, 0, strlen($prefix)) == $prefix)
	return ($codename);
    return ($prefix.$codename);
}

function support_compact_label($entry, $extra_class = "", $extra_title = "", $codename_prefix = NULL)
{
    $pretty = trim((string)@$entry["name"]);
    $prefix = $codename_prefix === NULL ? support_codename_prefix_from_entry($entry) : $codename_prefix;
    $codename = support_prefixed_codename(@$entry["codename"], $prefix);
    $id = isset($entry["id"]) ? (int)$entry["id"] : -1;
    $technical = trim($codename.(($id != -1) ? " #".$id : ""));
    if ($pretty == "")
	$pretty = $technical;
    $admin_label = support_menu_can_display_technical_labels();
    $title = trim(($admin_label ? $technical : $pretty).(($extra_title != "") ? " — ".$extra_title : ""));
    $class = trim("support_compact_label ".($admin_label ? "support_compact_admin_label " : "").$extra_class);

    ?>
    <span
	class="<?=support_menu_escape($class); ?>"
	title="<?=support_menu_escape($title); ?>"
    >
	<span class="support_compact_pretty"><?=support_menu_escape($pretty); ?></span>
	<?php if ($admin_label) { ?>
	    <span class="support_compact_technical"><?=support_menu_escape($technical); ?></span>
	<?php } ?>
    </span>
    <?php
}

function support_empty_menu_class($support)
{
    if (!isset($support["asset_count"]))
	return ("");
    return (((int)$support["asset_count"] <= 0) ? "support_empty_entry" : "");
}

function support_empty_menu_title($support)
{
    if (support_empty_menu_class($support) == "")
	return ("");
    return ("Aucun contenu associé");
}

function support_asset_empty_menu_class($asset)
{
    if (!isset($asset["content"]))
	return ("");
    return (trim((string)$asset["content"]) == "" ? "support_empty_entry" : "");
}

function support_asset_empty_menu_title($asset)
{
    if (support_asset_empty_menu_class($asset) == "")
	return ("");
    return ("Aucun contenu associé");
}

function support_unseen_marker($count = 1, $id_support = NULL)
{
    if (!function_exists("support_progress_should_show_student_marks") ||
	!support_progress_should_show_student_marks())
	return ;
    $count = (int)$count;
    if ($count <= 0)
	return ;
    ?>
    <span
	class="support_unseen_asset_marker"
	title="<?=$count == 1 ? 'Support non consulté' : $count.' supports non consultés'; ?>"
	<?php if ($id_support !== NULL) { ?>
	    data-support-unseen-parent="<?=(int)$id_support; ?>"
	    data-support-unseen-count="<?=$count; ?>"
	<?php } ?>
    >◆</span>
    <?php
}

function support_copy_codename_button($codename, $prefix = "")
{
    $codename = support_prefixed_codename($codename, $prefix);
    $json = htmlspecialchars(json_encode((string)$codename, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8");
    ?>
    <input
	type="button"
	class="support_admin_button support_copy_codename_button"
	value="C"
	title="Copier le nom de code complet. Shift + clic : ajouter au presse-papier avec ;"
	onclick="return support_copy_codename(event, <?=$json; ?>);"
    />
    <?php
}

?>
