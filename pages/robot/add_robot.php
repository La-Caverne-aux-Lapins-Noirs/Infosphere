<?php

function add_robot($codename, $version, $file)
{
    global $Database;
    global $Dictionnary;

    if (!isset($codename))
	return ("MissingChapter");

    if (!isset($codename))
	return ("MissingName");
    if (!isset($file))
	return ("MissingFile");
    $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
    if(!is_dir("./dres/robot/"))
    {
	if(!mkdir("./dres/robot/", 0777, TRUE))
	    return ("InvalidDir");
	system("touch ./dres/robot/index.htm");
    }
    $time = time();
    $link = "./dres/robot/".$time."_".$Database->real_escape_string($codename).".".$ext."";
    if(!move_uploaded_file($file["tmp_name"], $link))
	return ("InvalidFile");
    $forge = "
      INSERT INTO robot (codename, version, file)
      VALUES ('$codename', '$version', '$link')
       ";
if ($Database->query($forge) == false)
	return ("CannotAdd");
add_log(CREATIVE_OPERATION, "Robot $id");
return ("");
}

