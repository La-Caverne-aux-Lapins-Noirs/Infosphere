<?php

function save_full_profile($user)
{
    global $Database;

    $data = new FullProfile;
    $data->build($user["id"]);
    $prf = json_encode($data, JSON_UNESCAPED_SLASHES);
    return ;
    $Database->query("
       UPDATE user
          SET full_profile_date = NOW(),
              full_profile = '$prf'
        WHERE id = {$user["id"]}
	");
}
