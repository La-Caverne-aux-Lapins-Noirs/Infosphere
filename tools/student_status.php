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

// Présence
if (isset($_GET["p"]))
    $p = $_GET["p"];
else
    $p = -1;
// Réussite
if (isset($_GET["r"]))
    $r = $_GET["r"];
else
    $r = -1;


$img = imagecreatetruecolor(200, 200);
$black = imagecolorallocatealpha($img, 0, 0, 0, 0);

imagerectangle($img, 0, 0, $w, $h, $black);

header ("Content-type: image/png");
imagepng($img, NULL, 0, PNG_NO_FILTER);
imagedestroy($img);
