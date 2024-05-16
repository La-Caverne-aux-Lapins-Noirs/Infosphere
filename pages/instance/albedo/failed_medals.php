<?php
// Est ce vraiment une bonne idée que de mettre les "médailles échouées"?
// Pas sur, car cela contraste de fait avec les médailles perdues
// du a l'acquisition d'une medaille éliminatoire
return ;

///////////////////////////////////////////
/// On place les médailles non acquises ///
///////////////////////////////////////////

$medz = false;
foreach ($sessions as $sess)
{
    break ; // SABOTAGE////////////////////////////////////////////////////////////////////////////////
    $activity = new FullActivity;
    if ($activity->build($sess["id_activity"], false, false, $sess["id_session"], NULL, NULL) == false)
	continue ;
    foreach ($activity->unique_session->team as $reg)
    {
	foreach ($reg["user"] as $usr)
	{
	    foreach ($activity->medal as $med)
	    {
		if (!($request = edit_medal($usr["id"], "#".$med["codename"], $activity->id))->is_error())
		    $medz = true;
	    }
	}
    }
}

if ($medz)
    add_log(TRACE, "Albedo handled failed medals.", 1);
