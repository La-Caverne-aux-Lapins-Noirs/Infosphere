<?php
if (is_admin())
{
    if ($_POST["action"] == "add_medal")
    {
	$request = edit_medal($_POST["user"], $_POST["medal"], $_POST["activity"]);
	$LogMsg = "MedalAdded";
    }
    else if ($_POST["action"] == "edit_lock")
    {
	if (($res = db_select_one("
       team.id as id, team.closed as closed
       FROM user_team
       LEFT JOIN team ON user_team.id_team = team.id
       WHERE team.id_activity = ".((int)($_POST["activity"]))."
         AND user_team.id_user = ".((int)($_POST["user"]))."
	 ")) == NULL)
	{
	    $request = new ErrorResponse("NotAnId");
	}
	else if ($res["closed"] != NULL)
	    $Database->query("UPDATE team SET closed = NULL WHERE id = ".$res["id"]);
	else
	    $Database->query("UPDATE team SET closed = NOW() WHERE id = ".$res["id"]);
	$LogMsg = "LockEdited";
    }
}

if ($_POST["action"] == "subscribe")
{
    $request = @subscribe_to_instance(@$_POST["module"]);
    $LogMsg = "YouHaveSubscribed";
}
else if ($_POST["action"] == "unsubscribe")
{
    $request = @unsubscribe_from_instance(@$_POST["module"]);
    $LogMsg = "YouHaveUnsubscribed";
}

