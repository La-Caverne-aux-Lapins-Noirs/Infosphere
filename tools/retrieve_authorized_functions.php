<?php

function rerieve_authorized_functions($id)
{
    $user = get_full_profile($id, [], true);
    return ($user->functions);
}

