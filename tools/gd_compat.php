<?php

/*
** GD expects integer pixel coordinates. PHP historically converted floats
** implicitly, but PHP 8.1 deprecated lossy float-to-int coercions.
**
** Keep the old rendering semantics explicitly at the GD boundary: casting to
** int truncates toward zero, which is what the implicit conversion used to do.
*/

function infosphere_gd_int($value)
{
    return ((int)$value);
}

function infosphere_gd_points($points)
{
    foreach ($points as $index => $value)
        $points[$index] = infosphere_gd_int($value);
    return ($points);
}

function infosphere_imageline($image, $x1, $y1, $x2, $y2, $color)
{
    return (imageline(
        $image,
        infosphere_gd_int($x1),
        infosphere_gd_int($y1),
        infosphere_gd_int($x2),
        infosphere_gd_int($y2),
        $color
    ));
}

function infosphere_imagerectangle($image, $x1, $y1, $x2, $y2, $color)
{
    return (imagerectangle(
        $image,
        infosphere_gd_int($x1),
        infosphere_gd_int($y1),
        infosphere_gd_int($x2),
        infosphere_gd_int($y2),
        $color
    ));
}

function infosphere_imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color)
{
    return (imagefilledrectangle(
        $image,
        infosphere_gd_int($x1),
        infosphere_gd_int($y1),
        infosphere_gd_int($x2),
        infosphere_gd_int($y2),
        $color
    ));
}

function infosphere_imageellipse($image, $cx, $cy, $width, $height, $color)
{
    return (imageellipse(
        $image,
        infosphere_gd_int($cx),
        infosphere_gd_int($cy),
        infosphere_gd_int($width),
        infosphere_gd_int($height),
        $color
    ));
}

function infosphere_imagefilledellipse($image, $cx, $cy, $width, $height, $color)
{
    return (imagefilledellipse(
        $image,
        infosphere_gd_int($cx),
        infosphere_gd_int($cy),
        infosphere_gd_int($width),
        infosphere_gd_int($height),
        $color
    ));
}

function infosphere_imagesetpixel($image, $x, $y, $color)
{
    return (imagesetpixel(
        $image,
        infosphere_gd_int($x),
        infosphere_gd_int($y),
        $color
    ));
}

function infosphere_imagettftext(
    $image,
    $size,
    $angle,
    $x,
    $y,
    $color,
    $font_filename,
    $text
)
{
    return (imagettftext(
        $image,
        $size,
        $angle,
        infosphere_gd_int($x),
        infosphere_gd_int($y),
        $color,
        $font_filename,
        $text
    ));
}

function infosphere_imagefilledpolygon($image, $points, $color)
{
    // PHP 8 signature: imagefilledpolygon($image, $points, $color).
    return (imagefilledpolygon(
        $image,
        infosphere_gd_points($points),
        $color
    ));
}
