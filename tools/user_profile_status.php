<?php

function user_profile_statuses()
{
    return (["member", "prospect", "enterprise_contact", "jury", "extern"]);
}

function user_profile_status($value, $default = "member")
{
    $value = trim((string)$value);
    if (in_array($value, user_profile_statuses(), true))
        return ($value);
    return ($default);
}

function user_profile_status_is_fake($status)
{
    return (in_array(user_profile_status($status), ["prospect", "enterprise_contact", "jury", "extern"], true));
}

function user_profile_status_label($status)
{
    global $Dictionnary;

    $status = user_profile_status($status);
    $key = "UserProfileStatus".str_replace(" ", "", ucwords(str_replace("_", " ", $status)));
    if (isset($Dictionnary[$key]))
        return ($Dictionnary[$key]);
    return ($status);
}
