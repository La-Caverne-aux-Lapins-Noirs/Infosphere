<?php

function new_directory($path)
{
    $dir = $path;

    if ($dir != "" && substr($dir, -1) != "/" && pathinfo($dir, PATHINFO_EXTENSION) != "")
	$dir = dirname($dir);

    if ($dir == "" || $dir == ".")
	return (new Response);

    if (!is_dir($dir))
    {
	if (!mkdir($dir, 0775, true))
	{
	    if (is_admin())
		return (new ErrorResponse("CannotCreateDirectory", $dir));
	    return (new ErrorResponse("CannotCreateDirectory"));
	}
    }

    @chmod($dir, 0775);

    $index = $dir."/index.php";
    if (!file_exists($index))
    {
	if (file_put_contents($index, "<?php http_response_code(404);\n") === false)
	{
	    if (is_admin())
		return (new ErrorResponse("CannotWriteFile", $index));
	    return (new ErrorResponse("CannotWriteFile"));
	}
    }

    @chmod($index, 0755);

    return (new Response);
}
