<?php

function color_from_name($name, $not = [])
{
    $color = [
	"#922B21", "#EC7063",
	"#76448A", "#C39BD3",
	"#1F618D", "#85C1E9",
	"#117864",
	"#196F3D", "#7DCEA0",
	"#9A7D0A",
	"#935116", "#EB984E",
	"#979A9A", "#85929E",
	"#515A5A",
	"#283747"
    ];
    $val = crc32($name);
    $c = ($color[$val % count($color)]);
    if (in_array($c, $not))
	return (color_from_name($name."x", $not));
    return ($c);
}

