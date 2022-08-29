<?php

function add_school($codename, $icon, $lng)
{
    global $Configuration;
    
    return (@try_insert(
	"school", $codename, [],
	$icon, $Configuration->SchoolsDir(),
	["name"], $lng
    ));
}

