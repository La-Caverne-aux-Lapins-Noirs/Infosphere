<?php

function new_directory($dir)
{
    $dir = explode("/", $dir);
    if ($dir[count($dir) - 1] != "")
	unset($dir[count($dir) - 1]);
    $dir = implode("/", $dir);
    $cmd = "";
    if (system($cmd = "mkdir -p $dir && chmod 775 $dir") === false)
    {
	if (is_admin())
	    return (new ErrorResponse("CannotExecute", $cmd));
	return (new ErrorResponse("CannotExecute"));
    }
    if (system($cmd = "echo '<?php http_response_code(404);' > $dir/index.php") === false)
    {
	if (is_admin())
	    return (new ErrorResponse("CannotExecute", $cmd));
	return (new ErrorResponse("CannotExecute"));
    }
    if (system($cmd = "chmod 755 $dir/index.php") === false)
    {
	if (is_admin())
	    return (new ErrorResponse("CannotExecute", $cmd));
	return (new ErrorResponse("CannotExecute"));
    }
    return (new Response);
}

