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
	    $support["asset_intercom_unread_count"] = function_exists("support_asset_intercom_unread_count_for_support")
		? support_asset_intercom_unread_count_for_support($id)
		: 0;
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


function support_new_message_marker($count = 1, $title = NULL)
{
    $count = (int)$count;
    if ($count <= 0)
        return ;
    if ($title === NULL)
        $title = $count == 1 ? "Nouveau message" : $count." nouveaux messages";
    ?>
    <span
        class="support_new_message_marker"
        title="<?=support_menu_escape($title); ?>"
    >●</span>
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



function support_pedagogical_escape($value)
{
    return (support_menu_escape($value));
}

function support_pedagogical_valid_field($field)
{
    return (in_array($field, ["id_support_category", "id_support", "id_support_asset"]));
}

function support_pedagogical_activity_template_condition($alias = "activity")
{
    return ("$alias.is_template = 1");
}

function support_pedagogical_direct_counts($field, $ids)
{
    if (!support_pedagogical_valid_field($field))
        return ([]);
    $ids = array_values(array_unique(array_filter(array_map("intval", $ids), function ($id) {
        return ($id > 0);
    })));
    if (count($ids) == 0)
        return ([]);
    $idlist = implode(",", $ids);
    $rows = db_select_all("
        activity_support.$field as id,
        COUNT(DISTINCT activity.id) as cnt
        FROM activity_support
        LEFT JOIN activity ON activity.id = activity_support.id_activity
        WHERE activity_support.$field IN ($idlist)
          AND activity.id IS NOT NULL
          AND (activity.deleted IS NULL OR activity.deleted = 0)
          AND ".support_pedagogical_activity_template_condition("activity")."
        GROUP BY activity_support.$field
    ", "id");
    $ret = [];
    foreach ($rows as $id => $row)
        $ret[(int)$id] = (int)$row["cnt"];
    return ($ret);
}

function support_pedagogical_direct_count($field, $id)
{
    $counts = support_pedagogical_direct_counts($field, [(int)$id]);
    return (isset($counts[(int)$id]) ? (int)$counts[(int)$id] : 0);
}

function support_pedagogical_link_rows($field, $id)
{
    global $Language;

    if (!support_pedagogical_valid_field($field))
        return ([]);
    $id = (int)$id;
    if ($id <= 0)
        return ([]);
    $name = $Language."_name";
    return (db_select_all("
        MIN(activity_support.id) as id,
        activity_support.chapter as chapter,
        activity.id as id_activity,
        activity.codename as activity_codename,
        activity.$name as activity_name,
        activity.parent_activity as parent_activity,
        activity.id_template as id_template,
        activity.is_template as is_template
        FROM activity_support
        LEFT JOIN activity ON activity.id = activity_support.id_activity
        WHERE activity_support.$field = $id
          AND activity.id IS NOT NULL
          AND (activity.deleted IS NULL OR activity.deleted = 0)
          AND ".support_pedagogical_activity_template_condition("activity")."
        GROUP BY activity.id,
                 activity_support.chapter,
                 activity.codename,
                 activity.$name,
                 activity.parent_activity,
                 activity.id_template,
                 activity.is_template
        ORDER BY activity.parent_activity ASC,
                 COALESCE(NULLIF(activity.$name, ''), activity.codename) ASC,
                 activity_support.chapter ASC
    "));
}

function support_pedagogical_link_label($row)
{
    $label = trim((string)@$row["activity_name"]);
    if ($label == "")
        $label = trim((string)@$row["activity_codename"]);
    if ($label == "")
        $label = "activity#".(int)@$row["id_activity"];
    return ($label);
}

function support_pedagogical_link_kind($row)
{
    $parent = @$row["parent_activity"];
    if ($parent === NULL || (int)$parent == -1)
        return ("matter");
    return ("activity");
}

function support_pedagogical_asset_has_content($asset)
{
    return (isset($asset["content"]) && trim((string)$asset["content"]) != "");
}

function support_pedagogical_empty_coverage()
{
    return ([
        "content_total" => 0,
        "content_covered" => 0,
        "content_uncovered" => 0,
    ]);
}

function support_pedagogical_make_coverage($total, $covered)
{
    $total = max(0, (int)$total);
    $covered = max(0, min($total, (int)$covered));
    return ([
        "content_total" => $total,
        "content_covered" => $covered,
        "content_uncovered" => max(0, $total - $covered),
    ]);
}

function support_pedagogical_coverage_is_complete($coverage)
{
    return ((int)$coverage["content_total"] <= 0
        || (int)$coverage["content_uncovered"] <= 0);
}

function support_pedagogical_coverage_should_warn($coverage)
{
    return ((int)$coverage["content_total"] > 0
        && (int)$coverage["content_uncovered"] > 0);
}

function support_pedagogical_content_asset_counts_by_support($support_ids)
{
    global $Language;

    $support_ids = array_values(array_unique(array_filter(array_map("intval", $support_ids), function ($id) {
        return ($id > 0);
    })));
    if (count($support_ids) == 0)
        return ([]);
    $idlist = implode(",", $support_ids);
    $content = $Language."_content";
    $rows = db_select_all("
        support_asset.id_support as id_support,
        COUNT(DISTINCT support_asset.id) as content_total,
        COUNT(DISTINCT CASE WHEN activity.id IS NOT NULL THEN support_asset.id END) as direct_asset_linked_count
        FROM support_asset
        LEFT JOIN activity_support
          ON activity_support.id_support_asset = support_asset.id
        LEFT JOIN activity
          ON activity.id = activity_support.id_activity
         AND (activity.deleted IS NULL OR activity.deleted = 0)
         AND ".support_pedagogical_activity_template_condition("activity")."
        WHERE support_asset.deleted IS NULL
          AND support_asset.id_support IN ($idlist)
          AND TRIM(COALESCE(support_asset.$content, '')) != ''
        GROUP BY support_asset.id_support
    ", "id_support");
    $ret = [];
    foreach ($rows as $id => $row)
        $ret[(int)$id] = [
            "content_total" => (int)$row["content_total"],
            "direct_asset_linked_count" => (int)$row["direct_asset_linked_count"],
        ];
    return ($ret);
}

function support_pedagogical_support_content_coverage_from_counts($category_link_count, $support_link_count, $content_total, $direct_asset_linked_count)
{
    $content_total = (int)$content_total;
    if ((int)$category_link_count > 0 || (int)$support_link_count > 0)
        return (support_pedagogical_make_coverage($content_total, $content_total));
    return (support_pedagogical_make_coverage($content_total, $direct_asset_linked_count));
}

function support_pedagogical_support_content_coverage($support)
{
    if (isset($support["content_asset_count"]) && isset($support["content_covered_count"]))
        return (support_pedagogical_make_coverage($support["content_asset_count"], $support["content_covered_count"]));

    $category_count = isset($support["category_pedagogical_link_count"])
        ? (int)$support["category_pedagogical_link_count"]
        : (isset($support["id_support_category"]) ? support_pedagogical_direct_count("id_support_category", $support["id_support_category"]) : 0);
    $support_count = isset($support["direct_pedagogical_link_count"])
        ? (int)$support["direct_pedagogical_link_count"]
        : (isset($support["id"]) ? support_pedagogical_direct_count("id_support", $support["id"]) : 0);

    if (isset($support["asset"]) && is_array($support["asset"]) && count($support["asset"]) > 0)
    {
        $total = 0;
        $covered = 0;
        foreach ($support["asset"] as $asset)
        {
            if (!support_pedagogical_asset_has_content($asset))
                continue ;
            $total += 1;
            $asset_count = isset($asset["direct_pedagogical_link_count"])
                ? (int)$asset["direct_pedagogical_link_count"]
                : (isset($asset["id"]) ? support_pedagogical_direct_count("id_support_asset", $asset["id"]) : 0);
            if ($category_count > 0 || $support_count > 0 || $asset_count > 0)
                $covered += 1;
        }
        return (support_pedagogical_make_coverage($total, $covered));
    }

    $id = isset($support["id"]) ? (int)$support["id"] : -1;
    $counts = support_pedagogical_content_asset_counts_by_support([$id]);
    $total = isset($counts[$id]) ? (int)$counts[$id]["content_total"] : 0;
    $direct_assets = isset($counts[$id]) ? (int)$counts[$id]["direct_asset_linked_count"] : 0;
    return (support_pedagogical_support_content_coverage_from_counts($category_count, $support_count, $total, $direct_assets));
}

function support_pedagogical_category_content_coverage($category)
{
    if (isset($category["content_asset_count"]) && isset($category["content_covered_count"]))
        return (support_pedagogical_make_coverage($category["content_asset_count"], $category["content_covered_count"]));
    if (!isset($category["support"]) || !is_array($category["support"]))
        return (support_pedagogical_empty_coverage());

    $category_count = isset($category["direct_pedagogical_link_count"])
        ? (int)$category["direct_pedagogical_link_count"]
        : (isset($category["id"]) ? support_pedagogical_direct_count("id_support_category", $category["id"]) : 0);
    $total = 0;
    $covered = 0;
    foreach ($category["support"] as $support)
    {
        $support["category_pedagogical_link_count"] = $category_count;
        $coverage = support_pedagogical_support_content_coverage($support);
        $total += (int)$coverage["content_total"];
        $covered += (int)$coverage["content_covered"];
    }
    return (support_pedagogical_make_coverage($total, $covered));
}

function support_pedagogical_asset_coverage($support, $asset)
{
    if (!support_pedagogical_asset_has_content($asset))
        return (support_pedagogical_empty_coverage());
    $category_count = isset($support["category_pedagogical_link_count"])
        ? (int)$support["category_pedagogical_link_count"]
        : (isset($support["id_support_category"]) ? support_pedagogical_direct_count("id_support_category", $support["id_support_category"]) : 0);
    $support_count = isset($support["direct_pedagogical_link_count"])
        ? (int)$support["direct_pedagogical_link_count"]
        : (isset($support["id"]) ? support_pedagogical_direct_count("id_support", $support["id"]) : 0);
    $asset_count = isset($asset["direct_pedagogical_link_count"])
        ? (int)$asset["direct_pedagogical_link_count"]
        : (isset($asset["id"]) ? support_pedagogical_direct_count("id_support_asset", $asset["id"]) : 0);
    return (support_pedagogical_make_coverage(1, ($category_count > 0 || $support_count > 0 || $asset_count > 0) ? 1 : 0));
}

function support_pedagogical_effective_count_for_support($support)
{
    $coverage = support_pedagogical_support_content_coverage($support);
    return ((int)$coverage["content_covered"]);
}

function support_pedagogical_effective_count_for_asset($support, $asset)
{
    $coverage = support_pedagogical_asset_coverage($support, $asset);
    return ((int)$coverage["content_covered"]);
}

function support_pedagogical_warning_marker($linked, $title = "Aucun lien pédagogique")
{
    if (!function_exists("can_edit_supports") || !can_edit_supports())
        return ;
    if ($linked)
        return ;
    ?>
    <span
        class="support_link_warning_marker"
        title="<?=support_pedagogical_escape($title); ?>"
    >⚠</span>
    <?php
}

function support_pedagogical_coverage_warning_marker($coverage, $title = "Contenu pédagogique non couvert")
{
    support_pedagogical_warning_marker(
        support_pedagogical_coverage_is_complete($coverage),
        $title
    );
}

function support_menu_enrich_pedagogical_links(&$categories)
{
    if (!function_exists("can_edit_supports") || !can_edit_supports())
        return ;

    $category_ids = [];
    $support_ids = [];
    foreach ($categories as $category)
    {
        if (isset($category["id"]))
            $category_ids[] = (int)$category["id"];
        if (!isset($category["support"]) || !is_array($category["support"]))
            continue ;
        foreach ($category["support"] as $support)
            if (isset($support["id"]))
                $support_ids[] = (int)$support["id"];
    }
    $category_counts = support_pedagogical_direct_counts("id_support_category", $category_ids);
    $support_counts = support_pedagogical_direct_counts("id_support", $support_ids);
    $asset_counts = support_pedagogical_content_asset_counts_by_support($support_ids);

    foreach ($categories as &$category)
    {
        $cid = isset($category["id"]) ? (int)$category["id"] : -1;
        $category["direct_pedagogical_link_count"] = isset($category_counts[$cid]) ? (int)$category_counts[$cid] : 0;
        $category["content_asset_count"] = 0;
        $category["content_covered_count"] = 0;
        if (!isset($category["support"]) || !is_array($category["support"]))
            continue ;
        foreach ($category["support"] as &$support)
        {
            $sid = isset($support["id"]) ? (int)$support["id"] : -1;
            $support["category_pedagogical_link_count"] = (int)$category["direct_pedagogical_link_count"];
            $support["direct_pedagogical_link_count"] = isset($support_counts[$sid]) ? (int)$support_counts[$sid] : 0;
            $support["content_asset_count"] = isset($asset_counts[$sid]) ? (int)$asset_counts[$sid]["content_total"] : 0;
            $support["direct_asset_pedagogical_content_count"] = isset($asset_counts[$sid]) ? (int)$asset_counts[$sid]["direct_asset_linked_count"] : 0;
            $coverage = support_pedagogical_support_content_coverage_from_counts(
                $support["category_pedagogical_link_count"],
                $support["direct_pedagogical_link_count"],
                $support["content_asset_count"],
                $support["direct_asset_pedagogical_content_count"]
            );
            $support["content_covered_count"] = (int)$coverage["content_covered"];
            $support["content_uncovered_count"] = (int)$coverage["content_uncovered"];
            $support["effective_pedagogical_link_count"] = (int)$coverage["content_covered"];
            $category["content_asset_count"] += (int)$coverage["content_total"];
            $category["content_covered_count"] += (int)$coverage["content_covered"];
        }
        $category["content_uncovered_count"] = max(0, (int)$category["content_asset_count"] - (int)$category["content_covered_count"]);
    }
}

function support_enrich_assets_pedagogical_links(&$support)
{
    if (!function_exists("can_edit_supports") || !can_edit_supports())
        return ;

    $category_count = isset($support["id_support_category"])
        ? support_pedagogical_direct_count("id_support_category", $support["id_support_category"])
        : 0;
    $support_count = isset($support["id"])
        ? support_pedagogical_direct_count("id_support", $support["id"])
        : 0;
    $support["category_pedagogical_link_count"] = $category_count;
    $support["direct_pedagogical_link_count"] = $support_count;

    if (!isset($support["asset"]) || !is_array($support["asset"]))
        return ;
    $asset_ids = [];
    foreach ($support["asset"] as $asset)
        if (isset($asset["id"]))
            $asset_ids[] = (int)$asset["id"];
    $asset_counts = support_pedagogical_direct_counts("id_support_asset", $asset_ids);

    $total = 0;
    $covered = 0;
    foreach ($support["asset"] as &$asset)
    {
        $aid = isset($asset["id"]) ? (int)$asset["id"] : -1;
        $asset["direct_pedagogical_link_count"] = isset($asset_counts[$aid]) ? (int)$asset_counts[$aid] : 0;
        $asset["pedagogical_content_exists"] = support_pedagogical_asset_has_content($asset);
        $asset_coverage = support_pedagogical_asset_coverage($support, $asset);
        $asset["pedagogical_content_covered"] = support_pedagogical_coverage_is_complete($asset_coverage);
        $asset["effective_pedagogical_link_count"] = (int)$asset_coverage["content_covered"];
        if ($asset["pedagogical_content_exists"])
        {
            $total += 1;
            $covered += (int)$asset_coverage["content_covered"];
        }
    }
    $support_coverage = support_pedagogical_make_coverage($total, $covered);
    $support["content_asset_count"] = (int)$support_coverage["content_total"];
    $support["content_covered_count"] = (int)$support_coverage["content_covered"];
    $support["content_uncovered_count"] = (int)$support_coverage["content_uncovered"];
    $support["effective_pedagogical_link_count"] = (int)$support_coverage["content_covered"];
}

function support_pedagogical_descendant_link_rows($kind, $id)
{
    global $Language;

    $id = (int)$id;
    if ($id <= 0)
        return ([]);
    $name = $Language."_name";
    $content = $Language."_content";
    if ($kind == "support_assets")
        $where = "support_asset.id_support = $id";
    else if ($kind == "category_supports")
        $where = "support.id_support_category = $id AND activity_support.id_support IS NOT NULL";
    else if ($kind == "category_assets")
        $where = "support.id_support_category = $id AND activity_support.id_support_asset IS NOT NULL";
    else
        return ([]);
    return (db_select_all("
        MIN(activity_support.id) as id,
        activity_support.chapter as chapter,
        activity.id as id_activity,
        activity.codename as activity_codename,
        activity.$name as activity_name,
        activity.parent_activity as parent_activity,
        activity.id_template as id_template,
        activity.is_template as is_template,
        support.codename as support_codename,
        support.$name as support_name,
        support_asset.codename as support_asset_codename,
        support_asset.$name as support_asset_name
        FROM activity_support
        LEFT JOIN activity ON activity.id = activity_support.id_activity
        LEFT JOIN support ON support.id = activity_support.id_support
          OR support.id = (SELECT sa.id_support FROM support_asset as sa WHERE sa.id = activity_support.id_support_asset LIMIT 1)
        LEFT JOIN support_asset ON support_asset.id = activity_support.id_support_asset
        WHERE $where
          AND activity.id IS NOT NULL
          AND (activity.deleted IS NULL OR activity.deleted = 0)
          AND ".support_pedagogical_activity_template_condition("activity")."
          AND (support.deleted IS NULL OR support.deleted = 0)
          AND (support_asset.id IS NULL OR support_asset.deleted IS NULL)
          AND (activity_support.id_support_asset IS NULL OR TRIM(COALESCE(support_asset.$content, '')) != '')
        GROUP BY activity.id,
                 activity_support.chapter,
                 activity.codename,
                 activity.$name,
                 activity.parent_activity,
                 activity.id_template,
                 activity.is_template,
                 support.codename,
                 support.$name,
                 support_asset.codename,
                 support_asset.$name
        ORDER BY COALESCE(NULLIF(activity.$name, ''), activity.codename) ASC,
                 COALESCE(NULLIF(support.$name, ''), support.codename) ASC,
                 COALESCE(NULLIF(support_asset.$name, ''), support_asset.codename) ASC
    "));
}

function support_pedagogical_render_rows($rows, $source)
{
    if (count($rows) == 0)
        return ;
    foreach ($rows as $row)
    {
        $kind = support_pedagogical_link_kind($row) == "matter" ? "Matière" : "Activité";
        $label = support_pedagogical_link_label($row);
        $chapter = (@$row["chapter"] !== NULL && (int)$row["chapter"] > 0) ? " — chapitre ".(int)$row["chapter"] : "";
        $origin = $source.$chapter;
        $support_label = trim((string)@$row["support_name"]);
        if ($support_label == "")
            $support_label = trim((string)@$row["support_codename"]);
        $asset_label = trim((string)@$row["support_asset_name"]);
        if ($asset_label == "")
            $asset_label = trim((string)@$row["support_asset_codename"]);
        if ($source == "chapitre descendant" && $support_label != "")
            $origin .= " — ".$support_label;
        if (($source == "ressource" || $source == "ressource descendante") && $asset_label != "")
            $origin .= " — ".$asset_label;
        ?>
        <li>
            <span class="support_pedagogical_link_kind"><?=$kind; ?></span>
            <span class="support_pedagogical_link_name" title="<?=support_pedagogical_escape(@$row["activity_codename"]." #".@$row["id_activity"]); ?>">
                <?=support_pedagogical_escape($label); ?>
            </span>
            <span class="support_pedagogical_link_source"><?=support_pedagogical_escape($origin); ?></span>
        </li>
        <?php
    }
}

function support_pedagogical_render_coverage_status($coverage)
{
    if ((int)$coverage["content_total"] <= 0)
    {
        ?>
        <p class="support_pedagogical_coverage support_pedagogical_coverage_empty">
            Aucun contenu publié à couvrir.
        </p>
        <?php
        return ;
    }
    ?>
    <p class="support_pedagogical_coverage">
        Couverture du contenu :
        <b><?=(int)$coverage["content_covered"]; ?>/<?=(int)$coverage["content_total"]; ?></b>
        <?php if ((int)$coverage["content_uncovered"] > 0) { ?>
            — <?=(int)$coverage["content_uncovered"]; ?> contenu(s) non couvert(s)
        <?php } ?>
    </p>
    <?php
}

function support_pedagogical_render_summary($title, $groups, $empty_message = "Aucun lien pédagogique direct", $coverage = NULL)
{
    if (!function_exists("can_edit_supports") || !can_edit_supports())
        return ;
    $total = 0;
    foreach ($groups as $group)
        $total += count($group["rows"]);
    ?>
    <div class="support_pedagogical_box">
        <h4>
            <?=support_pedagogical_escape($title); ?>
            <?php if ($coverage === NULL) { ?>
                <?php support_pedagogical_warning_marker($total > 0, $empty_message); ?>
            <?php } else { ?>
                <?php support_pedagogical_coverage_warning_marker($coverage, $empty_message); ?>
            <?php } ?>
        </h4>
        <?php if ($coverage !== NULL) support_pedagogical_render_coverage_status($coverage); ?>
        <?php if ($total <= 0) { ?>
            <p class="support_pedagogical_empty"><?=support_pedagogical_escape($empty_message); ?></p>
        <?php } else { ?>
            <ul>
                <?php foreach ($groups as $group) support_pedagogical_render_rows($group["rows"], $group["source"]); ?>
            </ul>
        <?php } ?>
    </div>
    <?php
}

function support_pedagogical_category_groups($category)
{
    return ([
        [
            "source" => "catégorie",
            "rows" => support_pedagogical_link_rows("id_support_category", @$category["id"])
        ],
        [
            "source" => "chapitre descendant",
            "rows" => support_pedagogical_descendant_link_rows("category_supports", @$category["id"])
        ],
        [
            "source" => "ressource descendante",
            "rows" => support_pedagogical_descendant_link_rows("category_assets", @$category["id"])
        ]
    ]);
}

function support_pedagogical_support_groups($support)
{
    return ([
        [
            "source" => "via catégorie",
            "rows" => support_pedagogical_link_rows("id_support_category", @$support["id_support_category"])
        ],
        [
            "source" => "chapitre",
            "rows" => support_pedagogical_link_rows("id_support", @$support["id"])
        ],
        [
            "source" => "ressource",
            "rows" => support_pedagogical_descendant_link_rows("support_assets", @$support["id"])
        ]
    ]);
}

function support_pedagogical_asset_groups($support, $asset)
{
    return ([
        [
            "source" => "via catégorie",
            "rows" => support_pedagogical_link_rows("id_support_category", @$support["id_support_category"])
        ],
        [
            "source" => "via chapitre",
            "rows" => support_pedagogical_link_rows("id_support", @$support["id"])
        ],
        [
            "source" => "ressource",
            "rows" => support_pedagogical_link_rows("id_support_asset", @$asset["id"])
        ]
    ]);
}

function support_pedagogical_render_category_panel($category)
{
    if (!function_exists("can_edit_supports") || !can_edit_supports())
        return ;
    support_pedagogical_render_summary(
        "Couverture pédagogique de cette catégorie",
        support_pedagogical_category_groups($category),
        "Cette catégorie contient des ressources publiées qui ne sont couvertes par aucun lien pédagogique.",
        support_pedagogical_category_content_coverage($category)
    );
}

function support_pedagogical_render_support_panel($support)
{
    if (!function_exists("can_edit_supports") || !can_edit_supports())
        return ;
    support_pedagogical_render_summary(
        "Couverture pédagogique du chapitre",
        support_pedagogical_support_groups($support),
        "Ce chapitre contient des ressources publiées qui ne sont couvertes par aucun lien pédagogique.",
        support_pedagogical_support_content_coverage($support)
    );
}

function support_pedagogical_render_asset_panel($support, $asset)
{
    if (!function_exists("can_edit_supports") || !can_edit_supports())
        return ;
    $label = trim((string)@$asset["name"]);
    if ($label == "")
        $label = trim((string)@$asset["codename"]);
    if ($label == "")
        $label = "asset#".(int)@$asset["id"];
    support_pedagogical_render_summary(
        "Couverture de la ressource — ".$label,
        support_pedagogical_asset_groups($support, $asset),
        "Cette ressource publiée n'est couverte par aucun lien pédagogique.",
        support_pedagogical_asset_coverage($support, $asset)
    );
}

?>
