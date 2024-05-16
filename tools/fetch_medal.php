<?php

function fetch_medal($id = -1, $one = false, $hidden = false)
{
    global $Database;
    global $Language;
    global $Configuration;

    if ($id !== -1)
    {
	$tmp = $id;
	if (($id = resolve_codename("medal", $id))->is_error())
	{
	    if ($one)
		return ([]);
	    $id = " AND ( 0 ";
	    $tmp = explode(";", $tmp);
	    foreach ($tmp as $t)
	    {
		if (($t = trim($t)) == "")
		    continue ;
		$t = $Database->real_escape_string($t);
		$id .= " OR tags LIKE \"%$t%\" ";
	    }
	    $id .= " )";
	}
	else
	{
	    $id = $id->value;
	    $id = " AND id = $id ";
	}
    }
    else
	$id = "";
    if ($hidden)
	$hidden = "";
    else
	$hidden = " AND hidden IS NULL ";
    $out = db_select_all("
	*, {$Language}_name as name, {$Language}_description as description
	FROM medal
	WHERE deleted IS NULL $hidden $id
	ORDER BY hidden, tags, codename
    ");
    foreach ($out as &$v)
    {
	$v["icon"] = $Configuration->MedalsDir($v["codename"])."icon.png";
	$v["band"] = $Configuration->MedalsDir($v["codename"])."band.png";
	if (!file_exists($v["band"]))
	    $v["band"] = NULL;
    }
    return ($one ? $out[0] : $out);
}

