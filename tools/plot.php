<?php

function base64url_decode($data)
{
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

header ("Content-type: image/png");

if (isset($_GET["w"]))
    $w = $_GET["w"];
else
    $w = 800;
if (isset($_GET["h"]))
    $h = $_GET["h"];
else
    $h = 600;
if (isset($_GET["data"]))
{
    $data = base64url_decode($_GET["data"]);
    $data = json_decode($data, true);
    $dates = $data["start_date"];
    $data = $data["data"];
}
else
{
    $data = [];
    $dates = 0;
}


$img = imagecreatetruecolor($w, $h);

$white = imagecolorallocatealpha($img, 255, 255, 255, 0);
$black = imagecolorallocatealpha($img, 0, 0, 0, 0);
$red = imagecolorallocatealpha($img, 255, 0, 0, 0);
$green = imagecolorallocatealpha($img, 0, 255, 0, 0);
$orange = imagecolorallocatealpha($img, 0xFF, 0x45, 0, 0);

$labelsize = 100;
$datasize = 20; // pixel
$nbr_data = 1;
foreach ($data as $d)
{
    if ($nbr_data < count($d["data"]))
	$nbr_data = count($d["data"]);
}
$datawidth = ($w - $labelsize) / $nbr_data;

// Repère
imageline($img, $labelsize, $h / 2, $w, $h / 2, $white);
imageline($img, $labelsize, 0, $labelsize, $h - 1, $white);
for ($i = 0; $i < $nbr_data; ++$i)
{
    $x = $labelsize + $i * $datawidth;
    imageline($img, $x, $h / 2 - 10, $x, $h / 2 + 10, $white);
    for ($xx = -1; $xx <= 0; ++$xx)
    {
	for ($yy = -1; $yy <= 0; ++$yy)
	{
	    if ($i != 0)
		imagettftext($img, 10, 90, $xx + $x + 5, $yy + $h / 2 + 55, $white, __DIR__."/../res/futura.ttf", date("d/m", $dates + $i * 60 * 60 * 24));
	    else
		imagettftext($img, 10, 90, $xx +$x + 20, $yy + $h / 2 + 55, $white, __DIR__."/../res/futura.ttf", date("d/m", $dates + $i * 60 * 60 * 24));
	}
    }
}


$i = 0;
$labelcount = 0;
foreach ($data as $ddd)
{
    $dat = $ddd["data"];
    $col = imagecolorallocatealpha($img, $ddd["color"][0], $ddd["color"][1], $ddd["color"][2], $ddd["color"][3]);
    $acol = imagecolorallocatealpha($img, $ddd["color"][0], $ddd["color"][1], $ddd["color"][2], 100);
    $dir = (int)$ddd["dir"] * -1;
    $max = 0;

    for ($j = 0; isset($dat[$j]); ++$j)
    {
	if (abs($dat[$j]) > $max)
	    $max = abs($dat[$j]);
    }

    $dataheight = $h / ($max * 2 + 4);
    for ($j = -$max; $j <= $max; ++$j)
    {
	$y = $j * $dataheight + $h / 2;
	imageline($img, $labelsize - 3, $y - 1, $labelsize + 3, $y - 1, $col);
	imageline($img, $labelsize - 3, $y, $labelsize + 3, $y, $col);
	imageline($img, $labelsize - 3 + 1, $y, $labelsize + 3, $y + 1, $col);
    }
    for ($xx = -1; $xx <= 0; ++$xx)
    {
	for ($yy = -1; $yy <= 0; ++$yy)
	{
	    imagettftext($img, 10, 0, $labelsize - 20, $i * $dir * $dataheight + $h / 2 + 5, $col, __DIR__."/../res/futura.ttf", sprintf("%+02d", $i));
	    imagettftext($img, 10, 0, 20, ($i + 1) * 20, $col, __DIR__."/../res/futura.ttf", $ddd["label"]);
	}
    }
    $prevx = 0;
    $prevy =  0;
    for ($j = 0; isset($dat[$j]); ++$j)
    {
	$val = $dat[$j] * $dir;
	$x = $j * $datawidth + $labelsize;
	$y = $val * $dataheight + $h / 2;
	if ($y != $h / 2 || $prevy != $h / 2)
	{
	    if ($y != $h / 2)
		imagefilledellipse($img, $x, $y, 10, 10, $acol);
	    if ($j > 0)
		imageline($img, $x, $y, $prevx, $prevy, $col);
	}
	$prevx = $x;
	$prevy = $y;
    }
    $i = $i + 1;
    $labelcount += 0.5;
}

imagepng($img, NULL, 0, PNG_NO_FILTER);
imagedestroy($img);
