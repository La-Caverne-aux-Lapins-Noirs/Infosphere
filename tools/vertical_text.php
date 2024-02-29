<?php

function vertical_text($str)
{
    for ($k = 0; $k < mb_strlen($str); ++$k)
	echo mb_substr($str, $k, 1)."<br />";
}
