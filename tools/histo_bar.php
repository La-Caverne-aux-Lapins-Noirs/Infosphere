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
$nbr_cols = 6;

if (isset($_GET["data"]))
{
    $data = base64url_decode($_GET["data"]);
    $data = json_decode($data, true);
    $percents[4] = $data["Grade"]["E"];
    $percents[3] = $data["Grade"]["A"];
    $percents[2] = $data["Grade"]["B"];
    $percents[1] = $data["Grade"]["C"];
    $percents[0] = $data["Grade"]["D"];
    $score[4] = $data["Validation"]["E"];
    $score[3] = $data["Validation"]["A"];
    $score[2] = $data["Validation"]["B"];
    $score[1] = $data["Validation"]["C"];
    $score[0] = $data["Validation"]["D"];
    $final_grade = 0;
    if ($score[0] >= $percents[0])
    {
	if ($score[1] >= $percents[1])
	{
	    if ($score[2] >= $percents[2])
	    {
		if ($score[3] >= $percents[3])
		    $final_grade = 4;
		else
		    $final_grade = 3;
	    }
	    else
		$final_grade = 2;
	}
	else
	    $final_grade = 1;
	if ($percents[4] != 0 && $score[4] >= $percents[4])
	    $final_grade += 1;
    }
    else
	$final_grade = 0;
}
else
{
    for ($i = 0; $i < 5; ++$i)
    {
	$percents[$i] = 0.75;
	$score[$i] = 0;
    }
    $final_grade = 0;
}

if ($percents[4] == 0)
    $nbr_cols -= 1;

$img = imagecreatetruecolor($w, $h);
imagesavealpha($img, true);
$alpha = imagecolorallocatealpha($img, 0, 0, 0, 127);
$white = imagecolorallocatealpha($img, 255, 255, 255, 0);
$gray = imagecolorallocatealpha($img, 128, 128, 128, 0);
$black = imagecolorallocatealpha($img, 0, 0, 0, 0);
$red = imagecolorallocatealpha($img, 255, 0, 0, 0);
$blue = imagecolorallocatealpha($img, 0, 0, 255, 0);
$yellow = imagecolorallocatealpha($img, 0xFF, 0xFF, 0, 0);
$green = imagecolorallocatealpha($img, 0, 255, 0, 0);
$orange = imagecolorallocatealpha($img, 0, 255, 0xA5, 0);
$pink = imagecolorallocatealpha($img, 0xFF, 0x69, 0xB4, 0);
$purple = imagecolorallocatealpha($img, 255, 0, 255, 0);
imagefill($img, 0, 0, $alpha);

$dead_stack = $gray;
$line_color = $black;

$cols = [$green, $blue, $red, $purple, $pink];

// On passe sur chaque zone
for ($i = 0; $i < $nbr_cols; ++$i)
{
    imageline($img,
	      ($i + 0.5) * $w / $nbr_cols,  $h * 0.1,
	      ($i + 0.5) * $w / $nbr_cols,  $h * 0.7,
	      $line_color);
    if ($percents[4] == 0 && $i > 3)
	break ;
    if ($i > 4)
	break ;
    imageline($img,
	      ($i + 0.5) * $w / $nbr_cols, $h * 0.7 - $h * 0.55 * $percents[$i],
	      ($i + 1.5) * $w / $nbr_cols, $h * 0.7 - $h * 0.55 * $percents[$i],
	      $line_color);
    imagettftext($img, 10, 0,
		 ($i + 0.5) * $w / $nbr_cols + 5, $h * 0.7 - $h * 0.55 * $percents[$i] - 5,
		 $line_color, __DIR__."/../res/futura.ttf",
		 sprintf("%d%%", $percents[$i] * 100)
    );

    if ($i == 4 && $score[4] >= $percents[4])
	$ccol = $cols[$i];
    else if ($i < 4 && $i <= $final_grade && $score[$i] >= $percents[$i])
	$ccol = $cols[$i];
    else
	$ccol = $dead_stack;

    imagefilledrectangle(
	$img,
	($i + 1 - 0.2) * $w / $nbr_cols, $h * 0.7 - $h * 0.55 * $score[$i],
	($i + 1 + 0.2) * $w / $nbr_cols, $h * 0.7,
	$ccol
    );

    imagettftext($img, 15, 0,
		 ($i + 1.0) * $w / $nbr_cols - 10, $h * 0.68,
		 $line_color, __DIR__."/../res/futura.ttf",
		 sprintf("%d%%", $score[$i] * 100)
    );
    $offset = $i == 4 ? 50 : 10;
    imagettftext($img, 15, 0,
		 ($i + 0.5) * $w / $nbr_cols + ($w / 6 - $offset) / 2, $h * 0.81,
		 $ccol, __DIR__."/../res/futura.ttf",
		 ["D", "C", "B", "A", $percents[4] != 0 ? "Bonus" : ""][$i]
    );
}

imagefilledrectangle(
    $img,
    0.5 * $w / $nbr_cols, $h * 0.85,
    0.5 * $w / $nbr_cols + $w * ($final_grade) / $nbr_cols, $h * 0.95,
    $final_grade > 0 ? $cols[$final_grade - 1] : $gray
);

if (error_get_last() == NULL)
{
    header ("Content-type: image/png");
    imagepng($img, NULL, 0, PNG_NO_FILTER);
    imagedestroy($img);
}

