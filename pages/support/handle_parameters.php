<?php
$gparams = false;
if (!isset($_GET["a"]))
    return ;
if (!isset($_GET["b"]))
{
    if ($_GET["p"] == "ClassMenu")
	return ;
    $_GET["b"] = $_GET["a"];
    $_GET["a"] = [
	"SupportAssetMenu" => 0,
	"SupportMenu" => 1,
	"SupportCategoryMenu" => 2,
    ][$_GET["p"]];
}

$gparams = true;
if (($gtype = (int)$_GET["a"]) < 0 || $gtype > 3)
{
    $request = new ErrorResponse("InvalidParameter");
    return ;
}
$gid = (int)$_GET["b"];

if ($gtype != 0)
    return ;

$id_support = db_select_one("
    id_support as id FROM support_asset
    WHERE support_asset.id = $gid
");
if ($id_support != NULL)
{
    $id_support = $id_support["id"];
    if (($request = fetch_support_asset($gid, $id_support))->is_error())
	return ;
    $gasset = $request->value;
}

