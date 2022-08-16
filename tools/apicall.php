<?php

// Sert à remplacer dans les handle_request les codes d'appels aux fonctions
function apicall($METHOD, $module, $DATA, $ID = -1)
{
    extract($GLOBALS);
    $OUTPUT = "handle";
    // Va établir une valeur à request et a LogMsg.
    require_once (__DIR__."/../api/$module.php");
}
