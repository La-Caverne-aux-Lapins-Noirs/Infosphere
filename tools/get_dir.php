<?php

// Les variables page, id, type et fbid non utilisés dans la fonction
// sont utilisés dans le template. => Ne pas les retirer!
function get_dir($root, $path, $page, $id, $type, $fbid = "file_browser", $poweruser = false, $language = NULL)
{
    global $Dictionnary;
    global $Configuration;
    global $Language;

    if ($language === NULL)
	$language = $Language;

    $path = resolve_path($path);
    $target = resolve_path($root.$path);

    if (strlen($target) < strlen($root))
    {
	$target = $root;
	$path = "/";
    }
    $target = $target."/";

    if (file_exists($target) == false && $poweruser == false)
	return (get_dir($root, "/", $page, $id, $type, $fbid, $poweruser, $language));
    new_directory($target);

    if ($type !== NULL && $id !== NULL)
	$api = "/api/$page/$id/$type";
    else
	$api = "/api/$page";

    ob_start();
    require ("./tools/template/file_browser.phtml");
    return (ob_get_clean());
}
