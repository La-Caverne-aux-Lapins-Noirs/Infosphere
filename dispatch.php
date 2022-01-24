<?php

if (!isset($Pages[$Position])
    || !file_exists($Pages[$Position]["File"])
    || !is_page_authorized($Pages[$Position], $User)
) {
    require_once ($Pages["HomeMenu"]["File"]); // @codeCoverageIgnore
} else {
    if ($Position == "Subscribe" && $User != NULL)
	$Position = "HomeMenu"; // @codeCoverageIgnore
    require_once ($Pages[$Position]["File"]);
}

