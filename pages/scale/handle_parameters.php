<?php

$fetch = @try_get($_GET, "a", -1);
if (($unique = ($fetch != -1)))
{
    $scale = db_select_one("
      *,
      scale.{$Language}_name as name,
      scale.{$Language}_content as content
      FROM scale
      WHERE id = $fetch
    ");
}
