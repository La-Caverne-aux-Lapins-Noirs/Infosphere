<?php

function requirex($file, $vars = [])
{
    extract($GLOBALS);
    extract($vars);
    ob_start();
    require ($file);
    return (handle_french(ob_get_clean()));
}
