<?php

if ($_POST["action"] == "execute_query")
{
    $ope = (int)$_POST["operation"];
    if ($ope >= 0 && $ope < count($Operations))
    {
	ob_start();
	$outfile = NULL;
	require ($Operations[$ope]);
	$result = ob_get_clean();
    }
}

