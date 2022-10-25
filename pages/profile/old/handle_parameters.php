<?php

if (!isset($_GET["a"]))
    $_GET["a"] = $User["id"];

if (($user = fetch_user($_GET["a"]))->is_error())
    $user = $User;
else
    $user = $user->value;

$edit_profile = try_get($_GET, "edit_profile", "0");

