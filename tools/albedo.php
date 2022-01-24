<?php

//// INTERCOM MESSAGE ALERT CLEANUP
$alerts = db_select_all("
  id FROM message_alert WHERE postdate = NOW() - 60 * 60 * 24 * 365
");
$ids = [];
foreach ($alerts as $al)
{
    $ids[] = " id = ".$al["id"];
}
if (count($ids) > 0)
{
    $ids = implode($ids, " OR ");
    $Database->query("REMOVE FROM message_alert WHERE $ids");
    add_log(TRACE, "Albedo have removed old message alerts.", 1);
}


