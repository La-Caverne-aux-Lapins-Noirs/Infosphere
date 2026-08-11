<?php
if (!isset($albedo) || $albedo != 1)
    return ;
if (!$HandOk)
{
    add_log(WARNING, "Automatic correction synchronization skipped: Distrans is unavailable.", 1, true);
    return ;
}

require_once (__DIR__."/../../tools/correction_catalog.php");
$result = correction_synchronize(1);
if (!($result["ok"] ?? false))
{
    add_log(ERROR, "Automatic correction synchronization failed: ".($result["error"] ?? "unknown error"), 1, true);
    return ;
}
if (!empty($result["up_to_date"]))
    return ;
if (!empty($result["pending"]))
{
    add_log(
        TRACE,
        "Automatic correction synchronization transferred ".count($result["sent"] ?? []).
        " file(s); ".(int)($result["remaining"] ?? 0)." file(s) remain.",
        1,
        true
    );
    return ;
}
if (!empty($result["synchronized"]))
    add_log(TRACE, "Automatic correction synchronization activated the current Infosphere catalogue on Distrans.", 1, true);
