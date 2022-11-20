<?php

$meds = db_select_all("
  codename, command FROM medal WHERE command IS NOT NULL
");

foreach ($meds as $m)
{
    if (strstr($m["command"], " band "))
	$out = "band.png";
    else
	$out = "icon.png";
    $ret = shell_exec(
	"DISPLAY=:1 ".
	$m["command"].
	" > ".
	$Configuration->MedalsDir($m["codename"]).
	"/$out"
    );
}

