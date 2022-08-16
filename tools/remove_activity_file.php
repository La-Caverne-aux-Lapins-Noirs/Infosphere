<?php

function remove_activity_file($id, $data)
{
    global $Dictionnary;
    global $Configuration;

    if ($id == -1 || !isset($data["file"]))
	return (false);
    if (!isset($data["language"]))
	$data["language"] = "";
    ($module = new FullActivity)->build($id);

    // On vérifie que le dossier est bien celui de l'activité demandé...
    $normal_dir = $Configuration->ActivitiesDir($module->codename, $data["language"]);
    $data["file"] = str_replace("@", "/", $data["file"]);
    if (strncmp($normal_dir, $data["file"], strlen($normal_dir)) != 0)
	return (false);

    // On déplace à la corbeille en signifiant que ce fichier était rattaché a l'activité $id
    $ext = pathinfo($data["file"], PATHINFO_EXTENSION);
    $new_path = "./dres/trash/activity_".$id."_deleted_".now().".".$ext;
    system("mv '".$data["file"]."' '".$newpath."'");
    return (true);
}
