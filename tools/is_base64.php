<?php

function is_base64($s)
{
    if (!isset($s))
	return (false);
    return ((bool)preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s));
}

