<?php
$gparams = false;
if (!isset($_GET["a"]) || !isset($_GET["b"]))
    return ;
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

