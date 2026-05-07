<?php

function edit_secured_text($msg, $cipher = "")
{
    $msg = get_secured_text($msg, $cipher);
    $msg = str_replace("<br />", "\n", $msg);
    return ($msg);
}

