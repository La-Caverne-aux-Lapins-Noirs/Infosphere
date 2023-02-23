<?php

define("AUTOMATIC_PICKUP", 0);
define("TEACHER_PICKUP", 1);
define("STUDENT_PICKUP", 2);

function pick_up_work(
    $activity,
    $username = NULL,
    $file = NULL,
    $author = STUDENT_PICKUP)
{
    global $Database;
    global $Dictionnary;
    global $Configuration;
    global $User;

    $status_list = ["automatic", "teacher", "student"];
    $status = $status_list[$author];

    // Si aucun utilisateur n'est spécifié, alors c'est nous
    if ($username == NULL)
	$username = $User["codename"];

    // Y a t il un code d'activité?
    if (!isset($activity) || $activity == "")
	return (new ErrorResponse("MissingCodeName"));
    // On récupère l'activité
    if (($id_activity = resolve_codename("activity", $activity))->is_error())
	return ($id_activity);
    $id_activity = $id_activity->value;
    if (($id_user = resolve_codename("user", $username))->is_error())
	return ($id_user);
    $id_user = $id_user->value;

    // On récupère l'équipe de l'utilisateur pour l'activité demandée, ainsi que la date de rendu
    // Et les informations requises pour etablir le ramassage
    if (($matching_work = db_select_one("
      activity.pickup_date as pickup_date,
      activity.codename as codename,
      activity.type as type,
      team.id as id_team,
      activity.id_template as id_template,
      user_team.status as status,
      activity.repository_name as repository_name,
      template.repository_name as template_repository
      FROM user_team
      LEFT JOIN team ON user_team.id_team = team.id
      LEFT JOIN activity ON activity.id = team.id_activity
      LEFT JOIN activity as template ON template.id = activity.id_template AND activity.template_link = 1
      WHERE user_team.id_user = $id_user AND activity.id = $id_activity
    ")) == NULL)
       return (new ErrorResponse("YouAreNotSubscribed"));
    $actname = $matching_work["codename"];
    $id_team = $matching_work["id_team"];
    $pickup_date = date_to_timestamp($matching_work["pickup_date"]);
    $type = $matching_work["type"];
    if (@strlen($matching_work["repository_name"]) != 0)
	$repository_name = $matching_work["repository_name"];
    else
	$repository_name = $matching_work["template_repository"];
    // Pas de mode de rendu indiqué: formulaire seulement
    if (@strlen($repository_name) == 0)
	$format = "formular";
    else
    {
	$format = explode(":", $repository_name);
	if (count($format) <= 1)
	    $format = "nfs";
	else
	{
	    $repository_name = $format[1];
	    $format = $format[0];
	}
    }

    // Si la personne qui rend n'est pas l'administrateur du groupe, on cherche l'administrateur.
    if ($matching_work["status"] != 2)
    {
	if (($admin = db_select_one("
          user.codename, user.id
          FROM user_team
          LEFT JOIN user ON user_team.id_user = user.id
          LEFT JOIN team ON user_team.id_team = team.id
          WHERE user_team.status = 2 AND team.id = {$matching_work["id_team"]}
	  ")) == NULL)
	    return (new ErrorResponse("CannotFetch"));
	$id_user = $admin["id"];
	$username = $admin["codename"];
    }

    // On verifie la date de ramassage...
    $observation = "";
    if ($pickup_date < now() && $author == STUDENT_PICKUP)
    {
	$observation .= $Dictionnary["LateDelivery"];
	$ConclusionMessage .= $Dictionnary["YouAreLate"];
    }

    $username = strtolower($username);
    $url = "./dres/activity/$actname/delivered";
    $filename = "/".str_replace(".", "_", $username."_".microtime(true));
    new_directory($url);

    // C'est un envoi manuel via le systeme de rendu http
    if ($format == "formular")
    {
	if ($file == NULL)
	    return (NULL);
	// Dans ce cas, $file est en fait $_FILE
	if (($ext = pathinfo($file["archive"]["name"], PATHINFO_EXTENSION)) == "gz")
	    $ext = "tar.gz";
	$url .= $filename.".".$ext;
	if (($err = upload_archive($file["archive"]["tmp_name"], $url, 10 * 1024 * 1024)) != "")
	    return (new ErrorResponse($err));
    }
    // C'est un ramassage via la NFS
    else if ($format == "nfs")
    {
	$target = "work/".$repository_name;
	// Le fichier est le "repository_name"
	$cmd = "echo '$username\\n$target\\n'".
	       " | ".
	       "ssh -tt -o StrictHostKeyChecking=no ".
	       $Configuration->Properties["nfs_connector"]
	;
	echo $cmd;
	$file = [];
	exec($cmd, $file, $return_value);
	unset($file[0]); // On retire codename
	unset($file[1]); // On retire target
	$file = implode($file);
	$finalfile = base64_decode($file); // Le fichier a été envoye b64é
	if ($return_value != 0 || $finalfile == false)
	{
	    add_log(TRACE, "Albedo failed at retrieving $actname $username: $file", 1);
	    $observation .= $Dictionnary["FailedToRetrieve"];
	    $finalfile = $Dictionnary["FailedToRetrieve"];
	}
	$file = $finalfile;

	$url .= $filename.".tar.gz";
	if (file_put_contents($url, $file) == false)
	    return (new ErrorResponse("CannotWriteFile"));
    }
    // C'est un ramassage via GIT
    else if ($format == "git")
    {
	// Le fichier est le "repository_name"
	$source = "https://{$Configuration->Properties["forge"]}/$username/$actname.git";
	$url .= $filename.".tar.gz";
	$target = sys_get_temp_dir().$username;
	$out = 0;
	system("git clone $source ".$target." && tar cvzf $url $target && rm -rf $target", $out);
	if ($out != 0)
	{
	    file_put_contents($url, $Dictionnary["FailedToRetrieve"]);
	    $observation .= $Dictionnary["FailedToRetrieve"];
	}
    }
    else
	return (new ErrorResponse("BadFormat"));

    // On enregistre le rendu dans la BDD.
    if ($Database->query("
      INSERT INTO pickedup_work
      (id_team, pickedup_date, repository, status, observation)
      VALUES ($id_team, NOW(), '$url', '$status', '$observation')
     ") == false)
        return (new ErrorResponse("CannotAdd"));

    // On supprime les rendus anciens n'étant pas le vrai rendu. On en garde que 3
    $old = db_select_all("
       *
        FROM pickedup_work
       WHERE id_team = $id_team
         AND status != '".$status_list[AUTOMATIC_PICKUP]."'
         AND status != 'deleted'
    ORDER BY pickedup_date DESC
	 ");
    for ($i = 3; isset($old[$i]); ++$i)
    {
	// Quelques check au cas ou quelqu'un ai trouvé le moyen d'injecter une distinction entre la BDD et le fichier
	// et eviter qu'ils deletent n'importe quoi sur le site.
	if (strstr($old[$i]["repository"], "..") != false ||
	    strncmp($old[$i]["repository"], "./dres/activity/", 16) != 0)
	    continue ; // Au cas ou, pour être sur qu'on est dans le dossier des rendus.
	if (strncmp(substr($old[$i]["repository"], -4), ".zip", 4) != 0 &&
	    strncmp(substr($old[$i]["repository"], -7), ".tar.gz", 7) != 0)
	    continue ; // Et qu'on est bien en train de supprimer un fichier zip ou targz...
	system("rm -rf ".$old[$i]["repository"]); // D'accord, je veux bien supprimer.
	$Database->query("UPDATE pickedup_work SET status = 'deleted' WHERE id = ".$old[$i]["id"]);
    }

    return (new ValueResponse());
}

