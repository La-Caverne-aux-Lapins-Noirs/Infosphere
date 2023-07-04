<?php

function base64url_decode($data)
{
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

if (isset($_GET["w"]))
    $w = $_GET["w"];
else
    $w = 900;
if (isset($_GET["h"]))
    $h = $_GET["h"];
else
    $h = 50;

if (isset($_GET["data"]))
{
    $data = base64url_decode($_GET["data"]);
    $data = json_decode($data, true);
    $percents[3] = $data["Grade"]["A"];
    $percents[2] = $data["Grade"]["B"];
    $percents[1] = $data["Grade"]["C"];
    $percents[0] = $data["Grade"]["D"];
    $bonus = $data["Bonus"];
    $current = $data["Completion"];
    $note = isset($data["Note"]) ? $data["Note"] : [];
    $is_note = isset($data["IsNote"]) ? $data["IsNote"] : false;
}
else
{
    $data = [];
    $percents[3] = 0.85;
    $percents[2] = 0.70;
    $percents[1] = 0.60;
    $percents[0] = 0.50;
    $bonus = 0;
    $current = 0;
    $note = [];
    $is_note = false;
}

$img = imagecreatetruecolor($w, $h);
imagesavealpha($img, true);
$alpha = imagecolorallocatealpha($img, 0, 0, 0, 127);
$white = imagecolorallocatealpha($img, 255, 255, 255, 0);
$gray = imagecolorallocatealpha($img, 128, 128, 128, 0);
$black = imagecolorallocatealpha($img, 0, 0, 0, 0);
$red = imagecolorallocatealpha($img, 255, 0, 0, 0);
$blue = imagecolorallocatealpha($img, 0, 255, 255, 0);
$yellow = imagecolorallocatealpha($img, 0xFF, 0xFF, 0, 0);
$green = imagecolorallocatealpha($img, 0, 255, 0, 0);
$orange = imagecolorallocatealpha($img, 0, 255, 0xA5, 0);
$purple = imagecolorallocatealpha($img, 255, 0, 255, 0);
imagefill($img, 0, 0, $alpha);

$lines = $black;
$background = $white;

// C'EST UNE MATIERE NOTE SUR 20
if ($is_note)
{
    $avg = 0;
    $nbr = 0;
    for ($i = 0; $i <= 20; ++$i)
    {
	$x = (($i + 1) / 22.0) * $w;
	imageline($img, $x, $h * 0.1, $x, $h * 0.7 - 15, $lines);
	imageline($img, $x + 1, $h * 0.1, $x + 1, $h * 0.7 - 15, $lines);
	imagettftext($img, 20, 0, $x - 15, $h * 0.7 + 20, $lines, __DIR__."/../res/futura.ttf", sprintf("%02d", $i));
	if ($i == (int)(100 * $percents[3]))
	{
	    for ($j = -8; $j < -3; ++$j)
		imagettftext($img, 20, 0, $x + $j, $h * 0.7 + 50, $lines, __DIR__."/../res/futura.ttf", "A");
	    imagettftext($img, 20, 0, $x - 6, $h * 0.7 + 50, $green, __DIR__."/../res/futura.ttf", "A");

	}
	if ($i == (int)(100 * $percents[2]))
	{
	    for ($j = -8; $j < -3; ++$j)
		imagettftext($img, 20, 0, $x + $j, $h * 0.7 + 50, $lines, __DIR__."/../res/futura.ttf", "B");
	    imagettftext($img, 20, 0, $x - 6, $h * 0.7 + 50, $blue, __DIR__."/../res/futura.ttf", "B");
	}
	if ($i == (int)(100 * $percents[1]))
	{
	    for ($j = -8; $j < -3; ++$j)
		imagettftext($img, 20, 0, $x + $j, $h * 0.7 + 50, $lines, __DIR__."/../res/futura.ttf", "C");
	    imagettftext($img, 20, 0, $x - 6, $h * 0.7 + 50, $yellow, __DIR__."/../res/futura.ttf", "C");
	}
	if ($i == (int)(100 * $percents[0]))
	{
	    for ($j = -8; $j < -3; ++$j)
		imagettftext($img, 20, 0, $x + $j, $h * 0.7 + 50, $lines, __DIR__."/../res/futura.ttf", "D");
	    imagettftext($img, 20, 0, $x - 6, $h * 0.7 + 50, $orange, __DIR__."/../res/futura.ttf", "D");
	}
	for ($j = 0; isset($note[$i]) && $j < $note[$i]; ++$j)
	{
	    $col = $red;
	    if ($j < $percents[0])
		$col = $red;
	    else if ($j < $percents[1])
		$col = $orange;
	    else if ($j < $percents[2])
		$col = $yellow;
	    else if ($j < $percents[3])
		$col = $blue;
	    else
		$col = $green;
	    $y = ($j + 1) * (($h * 0.6 - $h * 0.1) / ($note[$i] + 1)) + $h * 0.1;
	    // $y = $j * ($h * 0.9 - 15 - $h * 0.1) / ($note[$i] + 1) + $h * 0.1;
	    imagefilledellipse($img, $x, $y, 20, 20, $col);
	    imageellipse($img, $x, $y, 20, 20, $lines);
	    $avg += $i;
	    $nbr += 1;
	}
    }
    if ($nbr == 0)
	$avg = 0;
    else
	$avg = round($avg / $nbr);
    $x = (($avg + 1) / 22.0) * $w;
    $y = $h * 0.9;
    if ($avg >= $percents[3] * 100)
    {
	$letter = "A";
	$color = $green;
    }
    else if ($avg >= $percents[2] * 100)
    {
	$letter = "B";
	$color = $blue;
    }
    else if ($avg >= $percents[1] * 100)
    {
	$letter = "C";
	$color = $yellow;
    }
    else if ($avg >= $percents[0] * 100)
    {
	$letter = "D";
	$color = $orange;
    }
    else if ($nbr != 0)
    {
	$letter = "E";
	$color = $red;
    }

    if ($nbr != 0)
    {
	imageellipse($img, $x - 0, $y - 0, 50, 50, $color);
	imageellipse($img, $x - 1, $y - 0, 50, 50, $color);
	imageellipse($img, $x - 0, $y - 1, 50, 50, $color);
	imageellipse($img, $x - 1, $y - 1, 50, 50, $color);
	imagettftext($img, 30, 0, $x - 9 + 0, $y + 15 - 1, $color, __DIR__."/../res/futura.ttf", $letter);
	imagettftext($img, 30, 0, $x - 9 + 1, $y + 15 + 0, $color, __DIR__."/../res/futura.ttf", $letter);
	imagettftext($img, 30, 0, $x - 9 - 1, $y + 15 + 1, $color, __DIR__."/../res/futura.ttf", $letter);
    }
}
// C'EST UNE MATIERE MEDAILLE A POURCENTAGE TOTAL
else
{
    if ($current < $percents[0])
	$color = $gray;
    else if ($current < $percents[1])
	$color = $green;
    else if ($current < $percents[2])
	$color = $blue;
    else if ($current < $percents[3])
	$color = $red;
    else
	$color = $purple;

    // Les données
    imagefilledrectangle($img, 10, $h * 0.2, $current * ($w - 10), $h * 0.8, $color);

    $pixblack = 0;
    for ($j = $h * 0.2; $j < $h * 0.8; ++$j)
    {
	for ($i = $current * ($w - 10); $i > ($current - $bonus) * ($w - 10); --$i)
	{
	    if ($pixblack % 2 == 0)
		imagesetpixel($img, $i, $j, $black);
	    $pixblack += 1;
	}
    }



    // Ligne allant de gauche a droite
    imageline($img, 10, $h / 2, $w - 10, $h / 2, $lines);

    $letters = ["E", "D", "C", "B", "A"];
    for ($i = 0; $i < 4; ++$i)
    {
	imageline($img,
		  $percents[$i] * $w, 5,
		  $percents[$i] * $w, $h - 5,
		  $lines
	);
	
	// On double le texte
	imagettftext($img, 20, 90,
		     $percents[$i] * $w - 5, $h * 0.5,
		     $lines, __DIR__."/../res/futura.ttf",
		     sprintf("% 3d", (int)($percents[$i] * 100))
	);
	imagettftext($img, 20, 90,
		     $percents[$i] * $w - 6, $h * 0.5,
		     $lines, __DIR__."/../res/futura.ttf",
		     sprintf("% 3d", (int)($percents[$i] * 100))
	);
	
	if ($i == 0)
	    imagettftext($img, 10, 0, ($w - 20) * $percents[0] / 2 + 10, $h - 10, $lines, __DIR__."/../res/futura.ttf", $letters[$i]);
	if ($i < 3)
	    imagettftext($img, 10, 0, ($w - 20) * (($percents[$i + 1] - $percents[$i]) / 2 + $percents[$i]) + 10, $h - 10, $lines, __DIR__."/../res/futura.ttf", $letters[$i + 1]);
	else
	    imagettftext($img, 10, 0, ($w - 20) * ((1.0 - $percents[$i]) / 2 + $percents[$i]) + 10, $h - 10, $lines, __DIR__."/../res/futura.ttf", $letters[$i + 1]);
    }

    for ($x = -2; $x < 3; ++$x)
	for ($y = -2; $y < 3; ++$y)    
	    imagettftext(
		$img, 30, 0, 10 + $x, $y + $h * 0.5 + 10, $background, __DIR__."/../res/futura.ttf",
		sprintf("% 3d%%", (int)($current * 100))
	    );
    for ($z = 0; $z < 3; ++$z)
	imagettftext(
	    $img, 30, 0, 10, $h * 0.5 + 10, $lines, __DIR__."/../res/futura.ttf",
	    sprintf("% 3d%%", (int)($current * 100))
	);
}

if (error_get_last() == NULL)
{
    header ("Content-type: image/png");
    imagepng($img, NULL, 0, PNG_NO_FILTER);
    imagedestroy($img);
}
