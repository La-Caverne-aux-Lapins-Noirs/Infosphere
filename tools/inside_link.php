<?php

function inside_link($cat, $id)
{
    global $BaseDir;

    $finalcat = "";
    $cat = explode("_", $cat);
    foreach ($cat as $c)
	$finalcat .= ucfirst($c);

    return ("{$BaseDir}index.php?p={$finalcat}Menu&amp;a=$id");
}

