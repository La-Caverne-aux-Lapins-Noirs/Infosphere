<?php

function support_progress_percent($value)
{
    if (!is_numeric($value))
	$value = 0;
    $value = (int)round((float)$value);
    if ($value < 0)
	$value = 0;
    if ($value > 100)
	$value = 100;
    return ($value);
}

function support_progress_current_user_id()
{
    global $User;

    if (!$User || !isset($User["id"]))
	return (-1);
    return ((int)$User["id"]);
}

function support_progress_can_current_user_access_asset($id_asset)
{
    $id_user = support_progress_current_user_id();
    $id_asset = (int)$id_asset;

    if ($id_user <= 0 || $id_asset <= 0)
	return (false);
    if (can_edit_supports())
	return (true);
    $categories = fetch_my_support_category(true);
    if ($categories == NULL || $categories->is_error())
	return (false);
    foreach ($categories->value as $category)
    {
	if (empty($category["selected"]) || !isset($category["support"]) || !is_array($category["support"]))
	    continue ;
	foreach ($category["support"] as $support)
	{
	    if (empty($support["selected"]) || !isset($support["asset"]) || !is_array($support["asset"]))
		continue ;
	    foreach ($support["asset"] as $asset)
	    {
		if (empty($asset["selected"]) || !isset($asset["id"]))
		    continue ;
		if ((int)$asset["id"] == $id_asset)
		    return (true);
	    }
	}
    }
    return (false);
}

function support_progress_record_asset($id_asset, $progress)
{
    global $Database;

    $id_user = support_progress_current_user_id();
    $id_asset = (int)$id_asset;
    $progress = support_progress_percent($progress);
    if ($id_user <= 0 || $id_asset <= 0)
	return (new ErrorResponse("InvalidParameter"));
    if (!support_progress_can_current_user_access_asset($id_asset))
	forbidden();

    $finished = $progress >= 100 ? "NOW()" : "NULL";
    $Database->query("
        INSERT INTO support_asset_view
          (id_user, id_support_asset, progress, first_view, last_view, finished)
        VALUES
          ($id_user, $id_asset, $progress, NOW(), NOW(), $finished)
        ON DUPLICATE KEY UPDATE
          progress = GREATEST(progress, VALUES(progress)),
          last_view = NOW(),
          first_view = COALESCE(first_view, VALUES(first_view)),
          finished = IF(GREATEST(progress, VALUES(progress)) >= 100,
                        COALESCE(finished, NOW()),
                        finished)
    ");
    return (new ValueResponse(["progress" => $progress]));
}

function support_progress_get_for_assets(array $ids, $id_user = NULL)
{
    $ids = array_values(array_unique(array_filter(array_map("intval", $ids), function ($id) {
	return ($id > 0);
    })));
    if (count($ids) == 0)
	return ([]);
    if ($id_user === NULL)
	$id_user = support_progress_current_user_id();
    $id_user = (int)$id_user;
    if ($id_user <= 0)
	return ([]);
    return (db_select_all("
        id_support_asset, progress, first_view, last_view, finished
        FROM support_asset_view
        WHERE id_user = $id_user
        AND id_support_asset IN (".implode(",", $ids).")
    ", "id_support_asset"));
}

function support_progress_asset_content_exists($asset)
{
    $content = trim((string)@$asset["content"]);

    if ($content == "")
	return (false);
    if (isset($asset["content_exists"]))
	return (!!$asset["content_exists"]);
    if (isset($asset["type"]) && in_array($asset["type"], ["img", "frame", "video", "audio", "file"], true))
	return (file_exists($content));
    return (true);
}

function support_progress_apply_to_assets(array &$assets, $id_user = NULL)
{
    $ids = [];
    foreach ($assets as $asset)
	if (isset($asset["id"]))
	    $ids[] = (int)$asset["id"];
    $progress = support_progress_get_for_assets($ids, $id_user);

    foreach ($assets as &$asset)
    {
	$id = isset($asset["id"]) ? (int)$asset["id"] : -1;
	$asset["view_progress"] = isset($progress[$id]) ? (int)$progress[$id]["progress"] : 0;
	$asset["view_first"] = isset($progress[$id]) ? $progress[$id]["first_view"] : NULL;
	$asset["view_last"] = isset($progress[$id]) ? $progress[$id]["last_view"] : NULL;
	$asset["view_finished"] = isset($progress[$id]) ? $progress[$id]["finished"] : NULL;
	$asset["content_exists"] = support_progress_asset_content_exists($asset);
	$asset["unseen"] = $asset["content_exists"] && $asset["view_progress"] <= 0;
    }
}

function support_progress_should_show_student_marks()
{
    return (support_progress_current_user_id() > 0 && !can_edit_supports());
}

function support_progress_count_unseen_for_current_user()
{
    if (!support_progress_should_show_student_marks())
	return (0);
    if (!function_exists("fetch_my_support_category"))
	return (0);

    $categories = fetch_my_support_category(true);
    if ($categories == NULL || $categories->is_error())
	return (0);
    $categories = $categories->value;

    $seen = [];
    $count = 0;
    foreach ($categories as $category)
    {
	if (empty($category["selected"]) || !isset($category["support"]) || !is_array($category["support"]))
	    continue ;
	foreach ($category["support"] as $support)
	{
	    if (empty($support["selected"]) || !isset($support["asset"]) || !is_array($support["asset"]))
		continue ;
	    foreach ($support["asset"] as $asset)
	    {
		if (empty($asset["selected"]) || empty($asset["content_exists"]) || empty($asset["unseen"]))
		    continue ;
		$id = isset($asset["id"]) ? (int)$asset["id"] : -1;
		if ($id <= 0 || isset($seen[$id]))
		    continue ;
		$seen[$id] = true;
		$count += 1;
	    }
	}
    }
    return ($count);
}

function support_progress_can_view_user($id_user)
{
    global $User;

    $id_user = (int)$id_user;
    if ($id_user <= 0 || !$User || !isset($User["id"]))
	return (false);
    if (is_admin())
	return (true);
    $id_viewer = (int)$User["id"];
    return (db_select_one("
        us.id
        FROM user_school as us
        LEFT JOIN user_school as viewer_school
          ON viewer_school.id_school = us.id_school
        WHERE us.id_user = $id_user
          AND us.authority = 'STUDENT'
          AND viewer_school.id_user = $id_viewer
          AND viewer_school.authority IN ('DIRECTOR', 'TEACHER')
    ") != NULL);
}

function support_progress_fetch_user_history($id_user)
{
    global $Language;

    $id_user = (int)$id_user;
    if ($id_user <= 0)
	return ([]);
    return (db_select_all("
        support_asset_view.progress as progress,
        support_asset_view.first_view as first_view,
        support_asset_view.last_view as last_view,
        support_asset_view.finished as finished,
        support_category.codename as category_codename,
        COALESCE(NULLIF(support_category.{$Language}_name, ''), support_category.codename) as category_name,
        support.codename as support_codename,
        COALESCE(NULLIF(support.{$Language}_name, ''), support.codename) as support_name,
        support_asset.id as id_support_asset,
        support_asset.codename as asset_codename,
        COALESCE(NULLIF(support_asset.{$Language}_name, ''), support_asset.codename) as asset_name,
        support_asset.{$Language}_content as asset_content
        FROM support_asset_view
        LEFT JOIN support_asset
          ON support_asset.id = support_asset_view.id_support_asset
        LEFT JOIN support
          ON support.id = support_asset.id_support
        LEFT JOIN support_category
          ON support_category.id = support.id_support_category
        WHERE support_asset_view.id_user = $id_user
          AND support_asset.deleted IS NULL
          AND support.deleted IS NULL
          AND support_category.deleted IS NULL
          AND support_asset_view.progress > 0
        ORDER BY support_asset_view.last_view DESC
    "));
}

?>
