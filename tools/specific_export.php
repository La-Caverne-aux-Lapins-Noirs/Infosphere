<?php

function export($name, $conf, $format = "dabsic")
{
    $conf = save_configuration($conf, $format);
    $format = $format == "dabsic" ? ".dab" : ".json";
    if (!UNIT_TEST)
    { // @codeCoverageIgnoreStart
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header("Content-Disposition: attachment; filename=$name$format");
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: '.strlen($conf));
	echo $conf;
	exit;
    } // @codeCoverageIgnoreEnd
    return (new ValueResponse($conf));
}

function export_command($name, $command)
{
    $out = popen($command, "r");
    $msg = "";
    while (!feof($out))
	$msg .= fread($out, 4096);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=$name");
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: '.strlen($msg));
    echo $msg;
    exit;
}

function export_data($name, $data)
{
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=$name");
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: '.strlen($data));
    echo $data;
    exit;
}
