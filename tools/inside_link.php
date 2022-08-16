<?php

function inside_link($cat, $id)
{
    global $BaseDir;

    return ("{$BaseDir}index.php?p=".ucfirst($cat)."Menu&amp;a=$id");
}

