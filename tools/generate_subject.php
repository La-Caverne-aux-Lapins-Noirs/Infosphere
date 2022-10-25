<?php

function generate_subject($cnf, $act)
{
    global $User;
    global $Configuration;
    global $Language;

    $language = strtoupper($Language);
    
    // Generer l'instance et l'écrire
    $instance = $Configuration->UsersDir($User["codename"])."/{$act->codename}/instance.dab";
    $team = [];
    $team[] = $act->user_team["leader"]["codename"];
    foreach ($act->user_team as $ut)
	$team[] = $ut["codename"];
    $team = array_unique($team);
    $medal = []; // Médaille déjà acquise dans la liste de celle accessible. Plus tard
    $authorized_function = []; // Fonctions autorisées. On verra plus tard.
    $data = [
	"CodeName" => $act->template_codename,
	"Login" => $team,
	"Token" => $act->code,
	"TeamSize" => [$act->min_team_size, $act->max_team_size],
	"Medal" => $medal,
	"AuthorizedFunction" => $authorized_function,
	"Delivery" => [
	    "Method" => "NFS",
	    "Target" => [],
	    "Date" => $act->pickup_date
	]
    ];

    // Fichier de sorti
    $outfile = $Configuration->UsersDir($User["codename"])."/{$act->codename}/subject.pdf";
    $out = shell_exec(
	"docbuilder ".
	"-c ".$Configuration->SchoolsDir()." ".
	"-a ".$Configuration->ActivitiesDir($act->codename)." ".
	"-i $instance ".
	"-m ".$Configuration->MedalsDir()." ".
	"-o $outfile ".
	"--language $language ".
	"2>&1"
    );
    return (file_get_contents($outfile));
}

