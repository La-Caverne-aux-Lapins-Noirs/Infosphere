<?php

function retrieve_authority($teacher)
{
    global $User;

    if (isset($User["profile_status"]) && $User["profile_status"] != "member")
	return (-1);
    if (is_admin())
	return (2);
    foreach ($teacher as $t)
    {
	if (substr($t["codename"], 0, 1) == "#")
	{
	    foreach ($t["user"] as $usr)
	    {
		if ($usr["id"] == $User["id"])
		    return ($usr["authority"]);
	    }
	}
	else if ($t["id"] == $User["id"])
	    return ($t["authority"]);
    }
    return (-1);
}
