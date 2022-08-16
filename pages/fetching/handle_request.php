<?php

if ($ParentConnexion)
{
    $request = new ErrorResponse("ParentsCantModify");
    return ;
}

if ($_POST["action"] == "fetch")
{
    $request = pick_up_work(
	$_POST["code"],
	$User["codename"],
	$_FILES,
	STUDENT_PICKUP,
	base64_decode($Configuration->Properties["nfs_connector_password"])
    );
    if ($request !== NULL)
	$LogMsg = "WorkUploaded";
}


