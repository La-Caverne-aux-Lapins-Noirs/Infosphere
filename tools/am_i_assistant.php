<?php

function am_i_assistant()
{
    global $User;

    if (!$User)
        return (false);
    return (is_admin() || is_assistant((int)$User["id"]));
}
