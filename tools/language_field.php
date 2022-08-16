<?php

function language_field($ex)
{
    global $Language;

    if (isset($ex[$Language]))
	return ($ex[$Language]);
    if (isset($ex[strtoupper($Language)]))
	return ($ex[strtoupper($Language)]);
    if (@strlen($ex) > 0)
	return ($ex);
    return ($ex[array_key_first($ex)]);
}

