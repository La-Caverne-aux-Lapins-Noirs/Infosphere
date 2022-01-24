<?php

function clickable($url, $props = "", $no_print = false)
{
    if (!$no_print)
    {
	echo 'onclick="location.href=\''.$url.'\';" ';
	echo 'onauxclick="window.open(\''.$url.'\', \'_blank\');" ';
	echo 'onmouseover="" style="cursor: pointer; '.$props.'"';
    }
}
