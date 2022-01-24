<?php
// @codeCoverageIgnoreStart
$fetch = @try_get($_GET, "activity", -1);
$always_unroll = @try_get($_GET, "unroll", false);

if ($fetch == -1) // Racine. On déroule si c'est demandé.
    $unroll_first = $always_unroll;
else // Pas de racine, on déroule.
    $unroll_first = true;

$unique = ($fetch != -1);
if ($Position == "ActivitiesMenu")
    $activity = @fetch_activity_template($fetch, false, try_get($_GET, "deleted", 0));
else
    $activity = @fetch_activity($fetch);
// @codeCoverageIgnoreEnd
