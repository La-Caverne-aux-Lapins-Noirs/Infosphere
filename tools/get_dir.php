<?php

// Les variables page, id, type et fbid non utilisés dans la fonction
// sont utilisés dans le template. => Ne pas les retirer!
function get_dir($root, $path, $page, $id, $type, $fbid = "file_browser", $poweruser = false, $language = NULL, $nocd = false, $locked_path = "", $path_browser_can_cd = NULL)
{
    global $Dictionnary;
    global $Configuration;
    global $Language;

    if ($language === NULL)
	$language = $Language;
    if ($path_browser_can_cd === NULL)
	$path_browser_can_cd = !$nocd;
    else
	$path_browser_can_cd = !!$path_browser_can_cd;

    if ($path == "")
	$path = "/";
    $path = resolve_path($path);
    $locked_path = resolve_path($locked_path);
    if ($locked_path != "" &&
	$path != $locked_path &&
	strncmp($path, $locked_path."/", strlen($locked_path) + 1) != 0)
	$path = $locked_path;
    $target = resolve_path($root.$path);

    if (strlen($target) < strlen($root))
    {
	$target = $root;
	$path = "/";
    }
    $target = $target."/";

    if (file_exists($target) == false && $poweruser == false)
	return (get_dir($root, $locked_path == "" ? "/" : $locked_path, $page, $id, $type, $fbid, $poweruser, $language, $nocd, $locked_path, $path_browser_can_cd));
    new_directory($target);

    if ($type !== NULL && $id !== NULL)
	$api = "/api/$page/$id/$type";
    else
	$api = "/api/$page";

    ob_start();
    require ("./tools/template/file_browser.phtml");
    return (ob_get_clean());
}
