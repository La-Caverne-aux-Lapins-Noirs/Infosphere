<?php

if (isset($_POST["action"]) == "search")
{
    require_once ("search.php");

    $search = search($_POST["codename"]);
}

