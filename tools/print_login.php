<?php

function print_login($usr, $display_codename = false)
{
    if (strlen(@$usr["nickname"]))
	return ($usr["nickname"]." (".$usr["codename"].")");
    return ($usr["codename"]);
}
