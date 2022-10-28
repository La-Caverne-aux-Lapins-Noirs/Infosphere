<?php

function get(array $arr, $index, $def = NULL)
{
    if (!is_array($index))
	$index = [$index];
    foreach ($index as $idx)
    {
	if (!isset($arr[$idx]))
	    return ($def);
	$arr = $arr[$idx];
    }
    return ($arr);
}

function ppol($w)
{
    global $nopic;
    global $dbg;

    if ($dbg == false)
	return ;

    $nopic = true;
    echo "B-C<br />";
    echo "|&nbsp;&nbsp;&nbsp;&nbsp;|<br />";
    echo "A-D<br />";
    for ($i = 0; $i < 8; $i += 2)
	echo "X: ".$w[$i]." Y: ".$w[$i + 1]."<br />";
}

// Acceptable ladders are 24, 12, 6 and 3
function place_coords($coords, $ladder)
{
    global $hh;
    global $h;
    global $landmark;
    global $sladder;
    global $bladder;
    global $zerolevel;

    if ($landmark == "b")
	$offset = $h - 50;
    else
	$offset = $h - 50 + $sladder * $hh;
    for ($i = 1; $i < 8; $i += 2)
	$coords[$i] = $offset - $coords[$i] * $hh;
    return ($coords);
    
}

function forge_coords($data, $startday, $index, $ladder, $yoffset = [0, 0])
{
    global $w;
    global $len;

    $quantum = $w / $len;
    $lefti = $index + 1;
    $righti = $index + 2;
    $leftx  = $lefti * $quantum;
    $rightx = $righti * $quantum;

    $lefti += $startday;
    $righti += $startday;

    $lefty = isset($data[$lefti]) ? $data[$lefti] : 0;
    $righty = isset($data[$righti]) ? $data[$righti] : 0;
    
    $coords = [];
    $coords[0] =  $leftx;
    $coords[1] = $yoffset[0];
    $coords[2] =  $leftx;
    $coords[3] = $lefty + $yoffset[0];
    $coords[4] =  $rightx;
    $coords[5] = $righty + $yoffset[1];
    $coords[6] =  $rightx;
    $coords[7] = $yoffset[1];

    $coords[8] = $lefty;
    $coords[9] = $righty;
    
    return (place_coords($coords, $ladder));
}

function draw_area($data, $label, $startday, $index, $ladder, $ca, $c, $yoffset = [0, 0])
{
    global $img;
    global $black;

    if (!isset($data[$label]))
    {
	for ($i = 0; $i < 10; ++$i)
	    $coords[$i] = 0;
	return ($coords);
    }
    $coords = forge_coords(
	$data[$label], $startday, $index, $ladder, $yoffset
    );
    imagefilledpolygon($img, $coords, 4, $ca);
    if ($coords[8] != 0 || $coords[9] != 0)
    {
	imagesetthickness($img, 2);
	imageline($img, $coords[2], $coords[3], $coords[4], $coords[5], $c);
	imagesetthickness($img, 0);

	imagefilledellipse($img, $coords[2], $coords[3], 6, 6, $c);
	imagefilledellipse($img, $coords[2], $coords[3], 3, 3, $black);
	imagefilledellipse($img, $coords[4], $coords[5], 6, 6, $c);
	imagefilledellipse($img, $coords[4], $coords[5], 3, 3, $black);
    }
    return ($coords);
}

function draw_line($data, $label, $startday, $index, $ladder, $c, $ac = NULL, $bad = false, $yoffset = [0, 0])
{
    global $img;
    global $black;

    if ($ac === NULL)
	$ac = $c;
    if (!isset($data[$label]))
    {
	for ($i = 0; $i < 10; ++$i)
	    $coords[$i] = 0;
	return ($coords);
    }
    $coords = forge_coords(
	$data[$label], $startday, $index, $ladder, $yoffset
    );
    if ($coords[8] != 0 || $coords[9] != 0)
    {
	if ($bad)
	{
	    imagesetthickness($img, 6);
	    imageline($img, $coords[2], $coords[3], $coords[4], $coords[5], $ac);
	    imagesetthickness($img, 0);
	}

	imagesetthickness($img, 2);
	imageline($img, $coords[2], $coords[3], $coords[4], $coords[5], $c);
	imagesetthickness($img, 0);

	imagefilledellipse($img, $coords[2], $coords[3], 6, 6, $c);
	imagefilledellipse($img, $coords[2], $coords[3], 3, 3, $black);
	imagefilledellipse($img, $coords[4], $coords[5], 6, 6, $c);
	imagefilledellipse($img, $coords[4], $coords[5], 3, 3, $black);
    }
    return ($coords);
}
