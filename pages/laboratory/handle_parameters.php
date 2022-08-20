<?php

$fetch = @try_get($_GET, "a", -1);
$unique = ($fetch != -1);
$sort = boolval(@try_get($_GET, "b", false));
$fetch = @fetch_laboratory($fetch, $sort);

