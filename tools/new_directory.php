<?php

function new_directory($dir)
{
    $dir = explode("/", $dir);
    if ($dir[count($dir) - 1] != "")
	unset($dir[count($dir) - 1]);
    $dir = implode("/", $dir);
    system("mkdir -p $dir ; echo '<?php http_response_code(404);' > {$dir}/index.php ; chmod 755 {$dir}/index.php");
}

