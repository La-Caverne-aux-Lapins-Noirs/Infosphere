<?php
// Render the actual player template for each permission result; no database.
if (PHP_SAPI !== "cli")
    exit(1);
$CanEditFixture = false;
function can_edit_supports()
{
    return ($GLOBALS["CanEditFixture"]);
}
$support = ["id" => 123];
foreach ([false, true] as $permission)
{
    $CanEditFixture = $permission;
    ob_start();
    require (__DIR__."/../pages/support/screen.php");
    $html = ob_get_clean();
    $expected = 'data-support-can-upload="'.($permission ? "1" : "0").'"';
    if (strpos($html, $expected) === false)
        throw new RuntimeException("Player permission does not match can_edit_supports()");
}
echo "OK: player template follows existing support-edit permissions.\n";
