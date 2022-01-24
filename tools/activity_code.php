<?php

function generate_activity_code($login, $instance_salt, $activity_codename)
{
    return (hash("sha256", $login.$instance_salt.$activity_codename, false));
}

