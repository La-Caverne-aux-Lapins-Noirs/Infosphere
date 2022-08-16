<?php

function get_icon($dir, $codename)
{
    global $Configuration;
    
    return ($Configuration->$dir.$codename."/icon.png");
}

