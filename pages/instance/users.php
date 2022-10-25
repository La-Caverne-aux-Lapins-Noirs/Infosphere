<h4><?=$Dictionnary["RegisteredStudents"]; ?></h4>

<?php
$total_students = $activity->nbr_students;
$total_teams = count($activity->team);
$local_students = 0;
$local_teams = 0;
?>
<?php if (count($activity->session) > 1) { ?>
    <table style="height: 30px; font-size: x-small; text-align: center;">
	<tr>
	    <td><?=$Dictionnary["CrossSessionRegistered"].": ".$total_students; ?></td>
	    <?php if ($activity->min_team_size >= 2) { ?>
		<td><?=$Dictionnary["CrossSessionTeams"].": ".$total_teams; ?></td>
	    <?php } ?>
	    <?php
	    $local_students = $activity->unique_session->nbr_students;
	    $local_teams = count($activity->unique_session->team);
	    ?>
	    <td><?=$Dictionnary["RegisteredUsers"].": ".$local_students; ?></td>
	    <?php if ($activity->min_team_size >= 2) { ?>
		<td><?=$Dictionnary["RegisteredTeams"].": ".$local_teams; ?></td>
	    <?php } ?>
	</tr>
    </table>
<?php } else { ?>
    <table style="height: 30px">
	<tr>
	    <td><?=$Dictionnary["RegisteredUsers"].": ".$total_students; ?></td>
	    <td><?=$Dictionnary["RegisteredTeams"].": ".$total_teams; ?></td>
	</tr>
    </table>
<?php } ?>

<div style="text-align: center; position: relative;">
    <?php
    if (($cteam = $activity->user_team))
	require ("single_team.phtml");
    foreach ($activity->team as $cteam)
    {
	if ($activity->user_team != NULL && $cteam["id"] == $activity->user_team["id"])
	    continue ;
	if ($activity->reference_activity == -1)
	{
	    if ($activity->unique_session != NULL && $cteam["id_session"] != $activity->unique_session->id)
		continue ;
	}
	require ("single_team.phtml");
    }
    ?>
</div>
