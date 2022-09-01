<?php

function fetch_medal($id = -1)
{
    global $Language;
    global $Configuration;
    
    $id = (int)$id;
    if ($id !== -1)
	$id = " AND id = $id ";
    else
	$id = "";
    $out = db_select_all("
	*, {$Language}_name as name, {$Language}_description as description
	FROM medal
	WHERE deleted IS NULL $id
	ORDER BY tags, codename
    ");
    foreach ($out as &$v)
    {
	$v["icon"] = $Configuration->MedalsDir($v["codename"])."icon.png";
    }
    return ($id == "" ? $out : $out[0]);
}
