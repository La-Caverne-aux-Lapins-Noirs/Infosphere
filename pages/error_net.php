<?php

$ErrorMsg = "";

if (isset($request) && is_object($request))
{
    if ($request->is_error())
    {
	$ErrorMsg .= strval($request)."<br />";
	$LogMsg = "";
	$request = NULL;
    }
    else
    {
	$request = $request->value;
	$_POST = [];
    }
}

if (isset($fetch) && is_object($fetch))
{
    if ($fetch->is_error())
    {
	$ErrorMsg .= strval($fetch)."<br />";
	$fetch = NULL;
    }
    else
	$fetch = $fetch->value;
}

if (isset($activity) && is_object($activity))
{
    if ($activity->is_error())
    {
	$ErrorMsg .= strval($activity)."<br />";
	$fetch = NULL;
    }
    else
	$activity = $activity->value;
}

if (isset($silent) && $silent)
{
    if ($ErrorMsg != "" && 0)
	header("HTTP/1.0 500 ".strip_tags($ErrorMsg));
    else
    {
	header("HTTP/1.0 200 ".strip_tags($LogMsg));
	if ($export && isset($export_format) && isset($export_data))
	{
	    if (!isset($export_filename))
		$export_filename = "export.$export_format";
	    if ($export_format == "csv")
		export_csv($export_data, $export_filename);
	    else
	    {
		if (($x = save_configuration($export_data, $export_format)) == false)
		    header("HTTP/1.0 500 ".$Dictionnary["CannotExport"]);
		else
		    echo $x;
	    }
	}
    }
    exit; // @codeCoverageIgnore
}
