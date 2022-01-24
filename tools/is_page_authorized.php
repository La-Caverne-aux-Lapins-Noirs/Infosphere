<?php

function is_page_authorized($page, $user)
{
    if ($page["Authority"] == OUTSIDE)
	return (true);
    if (isset($user["authority"]) == false)
	return (false);
    return ($page["Authority"] <= $user["authority"]);
}
