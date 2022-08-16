<?php

$fetch = try_get($_GET, "a", -1);
$sort = boolval(try_get($_GET, "b", false));
$fetch = @fetch_rooms($fetch, $sort);
