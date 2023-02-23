<?php

// Taille de l'image
if (isset($_GET["w"]))
    $w = $_GET["w"];
else
    $w = 200;
if (isset($_GET["h"]))
    $h = $_GET["h"];
else
    $h = 200;

// Présence (log) moyenne les 14 derniers jours
if (isset($_GET["p"]))
    $p = $_GET["p"];
else
    $p = -1;

// Présence / Absence les 14 derniers jours
if (isset($_GET["a"]))
    $a = $_GET["a"];
else
    $a = -1;

// Gain de médailles les 14 derniers jours
if (isset($_GET["m"]))
    $m = $_GET["m"];
else
    $m = -1;

$siz = 800;
$siz2 = $siz / 2;

$img = imagecreatetruecolor($siz, $siz);
imagesavealpha($img, true);
$alpha = imagecolorallocatealpha($img, 0, 0, 0, 127);
$black = imagecolorallocatealpha($img, 0, 0, 0, 0);
$clock_inside = imagecolorallocatealpha($img, 200, 200, 200, 0);
$white = imagecolorallocatealpha($img, 255, 255, 255, 0);
$red = imagecolorallocatealpha($img, 255, 0, 0, 0);
$green = imagecolorallocatealpha($img, 0, 255, 0, 0);
$blue = imagecolorallocatealpha($img, 0, 0, 255, 0);

$gradation = [
    imagecolorallocatealpha($img, 255,   0, 0, 0),
    imagecolorallocatealpha($img, 255, 128, 0, 0),
    imagecolorallocatealpha($img, 255, 255, 0, 0),
    imagecolorallocatealpha($img, 128, 255, 0, 0),
    imagecolorallocatealpha($img,   0, 255, 0, 0)
];
imagefill($img, 0, 0, $alpha);

imagefilledellipse($img, $siz2, $siz2, $siz - 20, $siz - 20, $black);
imagefilledellipse($img, $siz2, $siz2, $siz - 60, $siz - 60, $clock_inside);

for ($i = 0; $i < 400; ++$i)
{
    for ($j = 0; $j < 5; ++$j)
    {
	imagearc($img, $siz2, $siz2, ($siz - 100) - $i, ($siz - 100) - $i,
		 200 - 220.0 * (($j + 1.0) / 5.0) + 180,
		 200 - 220.0 * (($j + 0.0) / 5.0) + 180,
		 $gradation[4 - $j]);
    }
    imagearc($img, $siz2, $siz2, ($siz - 100) - $i, ($siz - 100) - $i,
	     200 + 180,
	     200 + 180 + 2,
	     $black);
    imagearc($img, $siz2, $siz2, ($siz - 100) - $i, ($siz - 100) - $i,
	     200 + - 220 + 180,
	     200 + - 220 + 180 + 2,
	     $black);
}
for ($i = 0; $i < 20; ++$i)
{
    imagearc($img, $siz2, $siz2, ($siz - 100) - $i, ($siz - 100) - $i,
	     200 - 220.0 * ((0 + 5.0) / 5.0) + 180,
	     200 - 220.0 * ((0 + 0.0) / 5.0) + 180,
	     $black);
    imagearc($img, $siz2, $siz2, ($siz - 500) + $i, ($siz - 500) + $i,
	     200 - 220.0 * ((0 + 5.0) / 5.0) + 180,
	     200 - 220.0 * ((0 + 0.0) / 5.0) + 180,
	     $black);
}

$offset = -pi() / 128;
$angles = [-pi(), -3 * pi() / 4, -pi() / 2, -pi() / 4, 0];

if (isset($angles[$p]))
    $p = $angles[$p];
else
    $p = pi();
if (isset($angles[$a]))
    $a = $angles[$a];
else
    $a = pi();
if (isset($angles[$m]))
    $m = $angles[$m];
else
    $m = pi();


for ($i = -20; $i < 20; ++$i)
{
    imageline($img, $siz2 + $i, $siz * 0.9,
	      $siz2 + cos($p) * $siz2 * 0.9 + $i,
	      $siz2 + sin($p) * $siz2 * 0.9,
	      $black
    );
    imageline($img, $siz2 + $i, $siz * 0.9,
	      $siz2 + cos($a + $offset) * $siz2 * 0.8 + $i,
	      $siz2 + sin($a + $offset) * $siz2 * 0.8,
	      $black
    );
    imageline($img, $siz2 + $i, $siz * 0.9,
	      $siz2 + cos($m + $offset * 2) * $siz2 * 0.7 + $i,
	      $siz2 + sin($m + $offset * 2) * $siz2 * 0.7,
	      $black
    );
}
for ($i = -15; $i < 15; ++$i)
{
    imageline($img, $siz2 + $i, $siz * 0.9,
	      $siz2 + cos($p) * $siz2 * 0.9 + $i,
	      $siz2 + sin($p) * $siz2 * 0.9,
	      $red
    );
    imageline($img, $siz2 + $i, $siz * 0.9,
	      $siz2 + cos($a + $offset) * $siz2 * 0.8 + $i,
	      $siz2 + sin($a + $offset) * $siz2 * 0.8,
	      $green
    );
    imageline($img, $siz2 + $i, $siz * 0.9,
	      $siz2 + cos($m + $offset * 2) * $siz2 * 0.7 + $i,
	      $siz2 + sin($m + $offset * 2) * $siz2 * 0.7,
	      $blue
    );
}

header ("Content-type: image/png");
imagepng($img, NULL, 0, PNG_NO_FILTER);
imagedestroy($img);
