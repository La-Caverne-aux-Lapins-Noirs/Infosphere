<?php

function export_cycle($id)
{
    $students = db_select_all("
      user.codename, user.id, user_cycle.commentaries
      FROM user_cycle LEFT JOIN user ON user_cycle.id_user = user.id
      WHERE id_cycle = $id
    ");
    $activities = db_select_all("
      codename, id_activity, activity.credit as credit
      FROM activity_cycle LEFT JOIN activity ON activity_cycle.id_activity = activity.id
      WHERE id_cycle = $id AND activity.parent_activity IS NULL AND activity.deleted IS NULN AND activity.hidden = 0
    ");

    $stud = 1;
    $output = array_fill(0, 1 + count($students), []);
    for ($i = 0; $i < count($students) + 1; ++$i)
	$output[$i] = array_fill(0, 2 /* 3 */ + count($activities), "");

    $output[0][0] = "";
    $output[0][1] = "credit";
    // $output[0][2] = "general_comment";

    // On commence par créer la table des labels
    $i = 2; // 3;
    $matter = [];
    foreach ($activities as &$act)
    {
	$act["codename"] = explode("_", $act["codename"])[0];
	$output[0][$i] = $act["codename"]. "(".$act["credit"].")";
	// $output[0][$i + 1] = "comment";
	$matter[$act["codename"]] = $i++; // $i += 2;
    }
    $i = 1; // 3
    foreach ($students as $student)
    {
	$credit = 0;
	$output[$i][0] = $student["codename"];
	$profile = new FullProfile;
	$profile->build($student["id"], ["profile", "laboratory", "teacher"]);
	foreach ($profile->sublayer as $cycle)
	{
	    if ($cycle->id != $id)
		continue ;
	    // On parcoure les matières.
	    foreach ($cycle->sublayer as &$module)
	    {
		if ($module->hidden)
		    continue ;
		$module->codename = explode("_", $module->codename)[0];
		if (!isset($matter[$module->codename]))
		    continue ;
		$index = $matter[$module->codename];
		if ($cycle->done == false)
		{
		    $output[$i][$index] = "?";
		    continue ;
		}
		if ($module->manual_grade !== NULL)
		    $grade = $module->manual_grade;
		else
		    $grade = $module->grade;
		$cred = 0;
		if ($grade > 0)
		{
		    if ($module->manual_credit !== NULL)
			$cred = $module->manual_credit;
		    else
			$cred = $module->acquired_credit;
		}
		if ($module->credit > 0)
		    $output[$i][$index] = ["E", "D", "C", "B", "A"][$grade];
		else
		    $output[$i][$index] = "OK";
		// $output[$i][$index + 1] = $module->commentaries;
		$credit += $cred;
	    }
	}
	$output[$i][1] = $credit;
	$i += 1;
    }
    return (prepare_export($output));
}

