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

// C'EST UNE MATIERE NOTE ET CA FAIT CHIER
if (count($note))
{
    $avg = 0;
    $nbr = 0;
    for ($i = 0; $i <= 20; ++$i)
    {
	$x = (($i + 1) / 22.0) * $w;
	imageline($img, $x, $h * 0.1, $x, $h * 0.9 - 15, $gray);
	imagettftext($img, 10, 0, $x - 4, $h * 0.9, $lines, __DIR__."/../res/futura.ttf", $i);
	for ($j = 0; isset($note[$i]) && $j < $note[$i]; ++$j)
	{
	    $y = $j * ($h * 0.9 - 15 - $h * 0.1) / ($note[$i] + 1) + $h * 0.1;
	    imagefilledellipse($img, $x, $y, 10, 10, $lines);
	    imageellipse($img, $x, $y, 10, 10, $background);
	    $avg += $i;
	    $nbr += 1;
	}
    }
    $avg = round($avg / $nbr);
    $x = (($avg + 1) / 22.0) * $w;
    $y = ($h * 0.9 - $h * 0.1) / 2.0;
    if ($avg >= 18)
    {
	$letter = "A";
	$color = $green;
    }
    else if ($avg >= 15)
    {
	$letter = "B";
	$color = $blue;
    }
    else if ($avg >= 12)
    {
	$letter = "C";
	$color = $yellow;
    }
    else if ($avg >= 10)
    {
	$letter = "D";
	$color = $orange;
    }
    else
    {
	$letter = "E";
	$color = $red;
    }

    imageellipse($img, $x - 0, $y - 0, $h * 0.66, $h * 0.66, $color);
    imageellipse($img, $x - 1, $y - 0, $h * 0.66, $h * 0.66, $color);
    imageellipse($img, $x - 0, $y - 1, $h * 0.66, $h * 0.66, $color);
    imageellipse($img, $x - 1, $y - 1, $h * 0.66, $h * 0.66, $color);
    imagettftext($img, 20, 0, $x - 7 + 0, $y + 10, $color, __DIR__."/../res/futura.ttf", $letter);
    imagettftext($img, 20, 0, $x - 7 + 1, $y + 10, $color, __DIR__."/../res/futura.ttf", $letter);
    imagettftext($img, 20, 0, $x - 7 - 1, $y + 10, $color, __DIR__."/../res/futura.ttf", $letter);
}
// C'EST UNE MATIERE MEDAILLE
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
	imagettftext($img, 10, 90,
		     $percents[$i] * $w - 1, $h * 0.5,
		     $lines, __DIR__."/../res/futura.ttf",
		     sprintf("% 3d", (int)($percents[$i] * 100))
	);
	imagettftext($img, 10, 90,
		     $percents[$i] * $w - 2, $h * 0.5,
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
		$img, 20, 0, 10 + $x, $y + $h * 0.5 + 10, $background, __DIR__."/../res/futura.ttf",
		sprintf("% 3d%%", (int)($current * 100))
	    );
    for ($z = 0; $z < 3; ++$z)
	imagettftext(
	    $img, 20, 0, 10, $h * 0.5 + 10, $lines, __DIR__."/../res/futura.ttf",
	    sprintf("% 3d%%", (int)($current * 100))
	);
}

if (error_get_last() == NULL)
{
    header ("Content-type: image/png");
    imagepng($img, NULL, 0, PNG_NO_FILTER);
    imagedestroy($img);
}
