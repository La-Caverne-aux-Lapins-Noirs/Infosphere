<?php

function am_i_teacher()
{
    global $User;

    if (!$User)
        return (false);
    return (is_admin() || is_teacher((int)$User["id"]));
}
