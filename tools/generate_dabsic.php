<?php

function snakecase_to_pascalcase($str)
{
    if (is_integer($str))
	return ($str);
    $str = str_replace(["-", " "], "_", $str);
    $out = "";
    foreach (explode("_", $str) as $part)
    {
	if ($part == "")
	    continue ;
	$out .= strtoupper($part[0]).substr($part, 1);
    }
    return ($out);
}

function dabsic_pascalcase_array($data)
{
    $out = [];

    foreach ($data as $key => $value)
    {
	if (is_array($value))
	    $value = dabsic_pascalcase_array($value);
	$out[snakecase_to_pascalcase($key)] = $value;
    }
    return ($out);
}

function generate_dabsic($data, $file)
{
    if ($file == "")
	return (new ErrorResponse("MissingFile"));
    if (!is_array($data))
	return (new ErrorResponse("InvalidParameter", "data"));
    if (($ret = new_directory($file))->is_error())
	return ($ret);

    $data = dabsic_pascalcase_array($data);
    if (($json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) == false)
	return (new ErrorResponse("CannotWriteFile", $file));

    if (($tmp = tempnam(sys_get_temp_dir(), "infosphere_dabsic_").".json") == false)
	return (new ErrorResponse("CannotWriteFile", $file));
    if (file_put_contents($tmp, $json) === false)
    {
	@unlink($tmp);
	return (new ErrorResponse("CannotWriteFile", $file));
    }

    $cmd = "mergeconf -i ".escapeshellarg($tmp)." -o ".escapeshellarg($file)." --resolve 2>&1";
    $msg = shell_exec($cmd);
    @unlink($tmp);

    if (!file_exists($file))
	return (new ErrorResponse("CannotWriteFile", $msg));
    return (new Response);
}

