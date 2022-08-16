<?php

function get_full_profile($user, $blist = [], $recalculate = true)
{
    // recalculate n'est pas utilisé actuellement...
    $data = new FullProfile;
    $data->build($user["id"], $blist);
    return ($data);
}
