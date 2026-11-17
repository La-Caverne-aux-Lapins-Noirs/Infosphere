<?php
// Exercise the real renderer, replacing only GD's drawing/output boundary.
// Run with php -n so the recording functions below can stand in for GD.
if (extension_loaded("gd"))
    throw new RuntimeException("Run this test with php -n tests/profile_stats_activity.php");
require_once (__DIR__."/../tools/utils.php");

const PNG_NO_FILTER = 0;
$ProfileStatsTexts = [];
$ProfileStatsPolygons = [];
function imagecreatetruecolor($w, $h) { return (true); }
function imagesavealpha($image, $save) { return (true); }
function imagecolorallocatealpha($image, $r, $g, $b, $alpha)
{
    return (implode(",", [$r, $g, $b, $alpha]));
}
function imagefill($image, $x, $y, $color) { return (true); }
function imageline($image, $x1, $y1, $x2, $y2, $color) { return (true); }
function imagerectangle($image, $x1, $y1, $x2, $y2, $color) { return (true); }
function imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color) { return (true); }
function imagefilledellipse($image, $x, $y, $w, $h, $color) { return (true); }
function imagesetthickness($image, $width) { return (true); }
function imagepng($image, $file, $quality, $filter) { return (true); }
function imagettfbbox($size, $angle, $font, $text)
{
    return ([0, 0, strlen((string)$text) * $size, 0, 0, -$size, 0, -$size]);
}
function imagettftext($image, $size, $angle, $x, $y, $color, $font, $text)
{
    $GLOBALS["ProfileStatsTexts"][] = (string)$text;
    return ([]);
}
function imagefilledpolygon($image, $points, $color)
{
    $GLOBALS["ProfileStatsPolygons"][] = ["points" => $points, "color" => $color];
    return (true);
}
function profile_stats_expect($actual, $expected, $message)
{
    if ($actual !== $expected)
        throw new RuntimeException($message.": ".var_export($actual, true)." != ".var_export($expected, true));
}

$fixture_day = (int)(date_to_timestamp("2026-10-23") / 86400);
$minute = 1.0 / 60;
$fixture = [
    "work_logs" => [$fixture_day => $minute, $fixture_day + 1 => $minute / 2],
    "distant_logs" => [$fixture_day + 1 => $minute / 2],
    "lock_logs" => [$fixture_day + 2 => $minute],
    "ssh_idle_logs" => [$fixture_day + 2 => $minute],
    "intra_logs" => [$fixture_day => $minute, $fixture_day + 1 => $minute, $fixture_day + 3 => $minute],
    "avg_work_logs" => [$fixture_day => $minute, $fixture_day + 1 => $minute / 2],
    "avg_distant_logs" => [$fixture_day + 1 => $minute / 2],
    "avg_lock_logs" => [$fixture_day + 2 => $minute],
    "avg_ssh_idle_logs" => [$fixture_day + 2 => $minute],
    "avg_intra_logs" => [$fixture_day => $minute, $fixture_day + 1 => $minute, $fixture_day + 3 => $minute],
];
$_GET = ["w" => 1000, "h" => 600, "s" => ($fixture_day - 1) * 86400,
    "e" => ($fixture_day + 5) * 86400, "d" => base64_encode(json_encode($fixture))];
ob_start();
require (__DIR__."/../tools/profile_stats.php");
$output = ob_get_clean();
profile_stats_expect($output, "", "renderer emitted an error");
profile_stats_expect($data["logs"][$fixture_day], $minute, "overlapping Intra doubled daily total");
profile_stats_expect($data["logs"][$fixture_day + 1], $minute, "local plus remote active duration");
profile_stats_expect($data["avg_logs"][$fixture_day], $minute, "overlapping Intra doubled average");
profile_stats_expect($data["logs"][$fixture_day + 3] ?? 0, 0, "Intra-only time counted as measured work");
profile_stats_expect($biggest, $minute * 2, "scale must preserve measured inactive categories");
profile_stats_expect(count(array_filter($ProfileStatsTexts, function ($text) { return ($text === "0:01"); })),
    2, "two one-minute labels must be displayed");
profile_stats_expect(in_array("0:02", $ProfileStatsTexts, true), false, "double minute label remains");
$blue_bars = array_filter($ProfileStatsPolygons, function ($polygon) use ($bluea)
{
    return ($polygon["color"] === $bluea);
});
profile_stats_expect(count($blue_bars), 3, "Intra observations must stay visible");
foreach ($blue_bars as $bar)
{
    profile_stats_expect($bar["points"][1], (int)$zero_y, "Intra bar stacked on measured work");
    if ($bar["points"][4] - $bar["points"][0] >= $w / $len * 0.2)
        throw new RuntimeException("Intra needs its own narrow bar beside the measured timeline");
}
echo "profile_stats_activity: OK (overlap, totals, averages, labels, separate Intra bars)\n";
