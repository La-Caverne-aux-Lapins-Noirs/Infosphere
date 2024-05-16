<?php
// On place mon équipe en première
if (($mteam = $cteam = $activity->user_team) != NULL)
{
    echo "<section id='single_team".$cteam["id"]."'>";
    require ("single_team.phtml");
    echo "</section>";
}
foreach ($activity->team as $cteam)
{
    if ($mteam && $cteam["id"] == $mteam["id"])
	continue ;
    if ($activity->reference_activity == -1
	&& $activity->unique_session
	&& $cteam["id_session"] != $activity->unique_session->id)
        continue ;
    echo "<section id='single_team".$cteam["id"]."'>";
    require ("single_team.phtml");
    echo "</section>";
}
