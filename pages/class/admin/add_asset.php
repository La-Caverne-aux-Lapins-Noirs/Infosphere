<?php

function add_asset($id_class, $codename, $chapter, $file, $language)
{
    global $Configuration;
    global $Database;
    global $LanguageList;
    global $Dictionnary;

    if (($mod = resolve_codename("class", $id_class))->is_error())
	return ($mod);
    $id_class = $mod->value;
    if (!is_number($chapter))
	return (new ErrorResponse("InvalidParameter", $chapter));
    if (!($mod = resolve_codename("class_asset", $codename))->is_error())
	return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
    if ($mod->label != "BadCodeName")
	return ($mod);

    $lng = [];
    $txts = [];
    foreach ($LanguageList as $k => $v)
    {
	if (!isset($language[$k."_name"]) || $language[$k."_name"] == "")
	    return (new ErrorResponse("MissingName"));
	$txts[] = "'".$Database->real_escape_string($language[$k."_name"])."'";
	$lng[] = $k."_name";
	if (isset($file[$k."_content"]['tmp_name']) && $file[$k."_content"]["tmp_name"] != "")
	{
	    $ext = pathinfo($file[$k.'_content']['name'], PATHINFO_EXTENSION);
	    if(!is_dir("./dres/gallery_files/".$id_class."/".$k."/"))
	    {
		if(!mkdir("./dres/gallery_files/".$id_class."/".$k."/", 0777, TRUE))
		    return (new ErrorResponse("InvalidDir"));
		system("touch ./dres/gallery_files/".$id_class."/".$k."/index.htm");
	    }
	    $time = time();
	    $file_name = hash("md5", $language[$k."_name"], false);
	    if(!move_uploaded_file($file[$k."_content"]['tmp_name'], "./dres/gallery_files/".$id_class."/".$k."/".$time."_".$file_name.".".$ext))
		return (new ErrorResponse("InvalidFile"));
	    $lng[] = $k."_content";
	    $txts[] = "'./dres/gallery_files/".$id_class."/".$k."/".$time."_".$file_name.".".$ext."'";
	}
	if (isset($language[$k."_link"]))
	{
	    $lng[] = $k."_link";
	    $txts[] = "'".$Database->real_escape_string($language[$k."_link"])."'";
	}
    }
    $forge = "
      INSERT INTO class_asset (id_class, codename, chapter, ".implode(",", $lng).")
       VALUES ('$id_class', '$codename', '$chapter', ".implode(",", $txts).")
      ";
    if ($Database->query($forge) == false)
	return (new ErrorResponse("CannotAdd"));
    $last_id = $Database->insert_id;
    add_log(CREATIVE_OPERATION, "Asset $last_id, $codename for module $id_class");
    return (new ValueResponse($last_id));
}

