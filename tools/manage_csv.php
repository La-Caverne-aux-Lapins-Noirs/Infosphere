<?php

function read_csv($cnt, $fields = [])
{
    $header = [];
    $data = [];
    $line = explode("\n", $cnt);
    foreach ($line as &$l)
	$l = explode(";", $l);
    $header = $line[0];
    for ($i = 1; isset($line[$i]); ++$i)
    {
	foreach ($header as $j => $v)
	{
	    if (!isset($line[$i][$j]))
		return (new ErrorResponse("IncompleteLine", $i." ".$j));
	    if ($fields == [] || isset($fields[$v]))
		$data[$i - 1][$v] = $line[$i][$j];
	}
    }
    return (new ValueResponse($data));
}

function load_csv($file, $fields = [])
{
    foreach ($fields as $k => $v)
    {
	if (is_number($k))
	{
	    $fields[$v] = true;
	    unset($fields[$k]);
	}
    }
    if (($cnt = file_get_contents($file)) == NULL)
	return (new ErrorResponse("CannotLoadFile"));
    return (read_csv($cnt, $fields));
}

function write_csv($cnf, $raw)
{
    $content = "";
    if ($raw == false)
    {
	$first_line = $cnf[array_key_first($cnf)];
	// Pour créer la ligne de label en haut du CSV
	$content .= implode(";", array_keys($first_line))."\n";
    }
    foreach ($cnf as $v)
    {
	$content .= implode(";", $v)."\n";
    }
    return ($content);
}

function save_csv($file, $cnf)
{
    if (($x = write_csv($cnf))->is_error())
	return ($x);
    if (file_put_contents($file, $x->value) == false)
	return (new ErroResponse("CannotWriteFile", $file));
    return (new Response);
}

function export_csv($cnf, $file = "data", $raw = false)
{
    $conf = write_csv($cnf, $raw);
    if (!UNIT_TEST)
    {
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header("Content-Disposition: attachment; filename=$file.csv");
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: '.strlen($conf));
	echo $conf;
	exit;
    }
    return (new ValueResponse($conf));
}

