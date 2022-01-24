<?php

function new_directory($dir)
{
    // Peut servir a établir un .htaccess également...
    //
    system("mkdir -p $dir ; touch {$dir}/index.htm");
}

