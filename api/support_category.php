<?php

function DisplaySupportList($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;
    global $Database;
    
    if (is_admin())
    {
	if (($categories = fetch_support_category($id, true))->is_error())
	    return ($categories);
	$categories = $categories->value;
    }
    else
	$categories = [];
    if ($output == "json")
	return (new ValueResponse([
	    "content" => json_encode($categories, JSON_UNESCAPED_SLASHES)
	]));
    ob_start();
    if ($id == -1)
	foreach ($categories as $category)
	    require ("./pages/support/resume_class.php");
    else
	foreach ($categories["support"] as $category)
	    require ("./pages/support/resume_class.php");

    return (new ValueResponse(["content" => ob_get_clean()]));
}

function DisplayAssetList($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    if (is_admin())
    {
	if (($support = fetch_support($id))->is_error())
	    return ($support);
	$support = $support->value;
    }
    else
	$support = [];
    if ($output == "json")
	return (new ValueResponse([
	    "content" => json_encode($support, JSON_UNESCAPED_SLASHES)
	]));
    ob_start();
    require ("./pages/support/support_menu.php");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function DisplaySupportMenu($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;
    global $Database;

    if (is_admin())
	$categories = fetch_support_category(-1, true)->value;
    else
	$categories = [];
    ob_start();
    require_once ("./pages/support/menu.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddSupportList($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $User;
    
    if (($id <= 0 || $id == "") && isset($data["id_support_category"]))
	if (($id = $data["id_support_category"]) == "")
	    $id = -1;
    if (!isset($data["codename"]))
	bad_request();

    $id = (int)$id;
    if ($id > 0)
    {
	$fields = ["id_support_category" => $id];
	if (db_select_one("* FROM support_category WHERE id = $id") == NULL)
	    not_found();
    }
    else
	$fields = [];

    $fields["id_user"] = $User["id"];
    
    if (($ret = try_insert(
	$id == -1 ? "support_category" : "support",
	$data["codename"],
	$fields,
	"", "",
	["name" => false, "description" => false],
	$data))->is_error()
    )
        return ($ret);
    return (DisplaySupportList($id, $data, "GET", $output, $module));
}

function AddSupportAsset($id, $data, $method, $output, $module)
{
    global $Configuration;
    global $Database;
    global $Dictionnary;
    global $LanguageList;
    global $User;
    global $SUBID;

    if ($id == -1 || $SUBID == -1 || !isset($data["codename"]))
	bad_request();
    if (($ret = resolve_codename("support_asset", $data["codename"]))->is_error())
	if ($ret->label != "BadCodeName")
	    return ($ret);
    if (($id = resolve_codename("support_category", $id))->is_error())
	return ($id);
    $id = $id->value;
    if (($subid = resolve_codename("support", $SUBID))->is_error())
	return ($subid);
    $subid = $subid->value;
    if (($out = db_select_one("
        * FROM support WHERE id = $subid AND id_support_category = $id
    ")) == NULL)
        not_found();

    $fields = [
	"id_support" => $subid,
	"id_user" => $User["id"]
    ];

    $category = db_select_one("codename FROM support_category WHERE id = $id");
    $category = $category["codename"];
    $support = db_select_one("codename FROM support WHERE id = $subid");
    $support = $support["codename"];
    
    $assetlist = [];
    
    foreach ($data as $k => $v)
    {
	foreach ($LanguageList as $lk => $lv)
	{
	    if (!isset($data[$lk."_content"][0]["name"]) ||
		!isset($data[$lk."_content"][0]["content"]))
	    {
		if (isset($data[$lk."_content"]))
		    unset($data[$lk."_content"]);
	        continue ;
	    }
	    $asset_name = $data[$lk."_content"][0]["name"];
	    if (in_array(pathinfo($asset_name, PATHINFO_EXTENSION), [
		"php", "sh", "pl"
	    ]) !== false)
	        forbidden();
	    $assetlist[] = [
		"lng" => $lk,
		"data" => $data[$lk."_content"][0]["content"],
		"name" => $asset_name
	    ];
	}
    }

    foreach ($assetlist as $asset)
    {
	if (($ext = pathinfo($asset["name"], PATHINFO_EXTENSION)) == "gz")
	    $ext = "tar.gz";
	$target = $Configuration->SupportDir(
	    $category, $support, $data["codename"].".$ext", $asset["lng"]
	);
	$data[$asset["lng"]."_content"] = $target;
	$content = base64_decode($asset["data"]);
	if (file_put_contents($target, $content) === false)
	    return (new ErrorResponse("CannotWriteFile", $target));
    }

    $ch = db_select_one("
        chapter
        FROM support_asset
        WHERE id_support = $subid
        ORDER BY chapter DESC
    ");
    if ($ch == NULL)
	$ch = 0;
    else
	$ch = $ch["chapter"] + 1;
    $fields["chapter"] = $ch;

    if (($ret = try_insert(
	"support_asset",
	$data["codename"],
	$fields,
	"", "",
	["name" => false, "content" => false],
	$data))->is_error()
    )
        return ($ret);
   
    return (DisplayAssetList($subid, $data, "GET", $output, $module));
}

function sort_by_chapter($a, $b)
{
    return ($a["chapter"] - $b["chapter"]);
}

function EditSupportAsset($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $SUBSUBID;

    $id_category = (int)$id;
    $id_support = (int)$SUBID;
    $id_asset = (int)$SUBSUBID;
    if ($id_category == -1 || $id_support == -1)
	bad_request();
    if ($id_asset == -1)
    {
	if (($support = fetch_support_category($id_category))->is_error())
	    return ($support);
	if (count($support = $support->value) == 0)
	    not_found();
	$id_target = $id_support;
	$collection = $support["support"];
	$table = "support";
    }
    else
    {
	if (($support = fetch_support($id_support, $id_category))->is_error())
	    return ($support);
	if (count($support = $support->value) == 0)
	    not_found();
	$id_target = $id_asset;
	$collection = $support["asset"];
	$table = "support_asset";
    }
    usort($collection, "sort_by_chapter");
    
    for ($i = 0; isset($collection[$i]); ++$i)
	if ($collection[$i]["id"] == $id_target)
        {
	    $diff = 0;
	    if (isset($data["up"]))
	    {
		if ($i == 0) break ;
		$diff = -1;
		$top_chapter = $collection[$i + $diff]["chapter"];
		$bottom_chapter = $collection[$i]["chapter"];
	    }
	    else if (isset($data["down"]))
	    {
		if (!isset($collection[$i + 1])) break ;
		$diff = +1;
		$top_chapter = $collection[$i + $diff]["chapter"];
		$bottom_chapter = $collection[$i]["chapter"];
	    }
	    else
		bad_request();
	    if ($top_chapter == $bottom_chapter)
		$bottom_chapter -= 1;
	    $sum = db_update_one(
		$table, $collection[$i + $diff]["id"], ["chapter" => $bottom_chapter]
	    );
	    $sum += db_update_one(
		$table, $collection[$i]["id"], ["chapter" => $top_chapter]
	    );
	    if ($sum != 2)
		return (new ErrorResponse("CannotEdit"));
	    if ($id_asset != -1)
		return (DisplayAssetList($id_support, $data, $method, $output, $module));
	    return (DisplaySupportMenu(-1, [], $method, $output, $module));
	}
    return (new ErrorResponse("NothingToBeDone"));
}

function DeleteSupport($id, $data, $method, $output, $module)
{
    global $User;
    global $SUBID;

    if ($id == -1)
	bad_request();
    $id_to_remove = $id_category = abs($id);
    $table = "support_category";

    $id_support = (int)$SUBID;
    if ($id_support != -1)
    {
	$id_to_remove = abs($id_support);
	$table = "support";
    }

    if (($ret = fetch_data($table, $id_to_remove))->is_error())
	return ($ret);
    if (count($ret->value) == 0)
	not_found();
    if ($ret->value[array_key_first($ret->value)]["id_user"] != $User["id"])
	forbidden();
    if (($ret = mark_as_deleted($table, $id_to_remove))->is_error())
	return ($ret);
    return (DisplaySupportList($id_category, $data, "GET", $output, $module));
}

function DeleteSupportAsset($id, $data, $method, $output, $module)
{
    global $LanguageList;
    global $Dictionnary;
    global $Configuration;
    global $SUBID;
    global $SUBSUBID;
    global $User;
    
    $id_category = (int)$id;
    $id_support = (int)$SUBID;
    $id_asset = (int)$SUBSUBID;
    if ($id_asset == -1)
	return (DeleteSupport($id, $data, $method, $output, $module));
    if ($id_category == -1 || $id_support == -1)
	bad_request();
    $id_category = abs($id_category);
    $id_support = abs($id_support);
    $id_asset = abs($id_asset);
    if (($support = fetch_support($id_support, $id_category))->is_error())
	return ($support);
    $support = $support->value;
    if (count($support) == 0)
	not_found();
    if ($support["id_user"] != $User["id"])
	forbidden();
    
    $category = db_select_one("codename FROM support_category WHERE id = $id_category");
    $category = $category["codename"];
    
    foreach ($support["asset"] as $asset)
    {
	if ($asset["id"] == $id_asset)
	{
	    if (($ret = mark_as_deleted(
		"support_asset", $id_asset, "codename", true))->is_error()
	    )
	        return ($ret);
	    $ret = $ret->value;
	    foreach ($LanguageList as $lk => $lv)
	    {
		$ext = pathinfo($asset[$lk."_content"], PATHINFO_EXTENSION);
		if ($ext == "gz")
		    $ext = "tar.gz";
		$from = $Configuration->SupportDir(
		    $category, $support["codename"], $asset["codename"].".$ext", $lk
		);
		$to = $Configuration->SupportDir(
		    $category, $support["codename"], $ret["codename"].".$ext", $lk
		);
		db_update_one("support_asset", $id_asset, [
		    $lk."_content" => $to
		]);
		system("mv $from $to");
	    }
	    return (DisplayAssetList($id_support, $data, "GET", $output, $module));
	}
    }
    not_found();
}

$Tab = [
    "GET" => [
	"" => [
	    "logged_in",
	    "DisplaySupportList",
	],
	"get_menu" => [
	    "logged_in",
	    "DisplaySupportMenu",
	],
    ],
    "POST" => [
	"" => [
	    "is_teacher",
	    "AddSupportList",
	],
	"support" => [
	    "is_teacher",
	    "AddSupportAsset",
	],
    ],
    "PUT" => [
	"support" => [
	    "is_teacher",
	    "EditSupportAsset",
	]
    ],
    "DELETE" => [
	"" => [
	    "is_teacher",
	    "DeleteSupport", // Handle support and category
	],
	"support" => [
	    "is_teacher",
	    "DeleteSupportAsset", // Call DeleteSupport if id_asset is -1
	],
    ]
];

