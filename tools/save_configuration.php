<?php

function save_configuration($dat, $fmt = "dabsic")
{
    if (($cnf = json_encode($dat, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) == NULL)
	return (false);
    if ($fmt == "dabsic")
	$cnf = shell_exec("echo ".escapeshellarg($cnf)." | mergeconf -if .json -of .dabsic");
    return ($cnf);
}

function save_configuration_file($dat, $file)
{
    if (($dat = save_configuration($dat, "json")) == false)
	return (false);
    return (shell_exec("echo ".escapeshellarg($dat)." | mergeconf -if .json -o $file"));
}

