<?php

function load_configuration($file, $fields = [], $resolve = false) // file OR text
{
    if (!isset($file))
	return (new ErrorResponse("MissingFile"));
    if (pathinfo($file, PATHINFO_EXTENSION) == "csv")
	return (load_csv($file, $fields));
    if ($resolve == false)
	$resolve = "";
    else
	$resolve = " --resolve ";
    if (file_exists($file))
    {
	if (($cnt = file_get_contents($file)) == NULL)
	    return (new ErrorResponse("CannotLoadFile")); // @codeCoverageIgnore
	$tmp = stream_get_meta_data(tmpfile())["uri"];
	if (($v = json_decode($cnt, true)) == NULL)
	{
	    $v = json_decode(
		$out = shell_exec(
		    "cat ".escapeshellarg($file)." | mergeconf $resolve -if .dab -of .json 2> $tmp"
		),
		true
	    );
	}
	if ($v == NULL)
	    return (new ErrorResponse("InvalidFile", $file, file_get_contents($tmp)));
	return (new ValueResponse($v));
    }
    if (($tmp = json_decode($file, true)) != NULL)
	return (new ValueResponse($tmp));
    $v = json_decode(
	$ret = shell_exec(
	    "echo ".escapeshellarg($file)." | mergeconf $resolve -if .dabsic -of .json 2>&1"
	),
	true
    );
    if ($v == NULL)
	return (new ErrorResponse("InvalidConfiguration", $ret));
    return (new ValueResponse($v));
}


