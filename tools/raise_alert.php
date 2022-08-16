<?php

function raise_alert($usr, $msg, $critic = 0)
{
    global $User;
    global $Database;

    $usr = (int)$usr;
    $msg = $Database->real_escape_string($msg);
    $critic = (int)$critic;
    $Database->query("
      INSERT INTO user_alert (id_user, criticality, id_author, message)
      VALUES ($usr, $critic, {$User["id"]}, '$msg')
      ");
    return (new Response);
}

