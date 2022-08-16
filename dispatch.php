<?php

if ($Position == "Subscribe" && $User != NULL)
    $Position = "HomeMenu";

if (isset($TopMenu[$Position]))
    $Target = $TopMenu[$Position];
else if (isset($BottomMenu[$Position]))
    $Target = $BottomMenu[$Position];
else if (isset($Unlisted[$Position]))
    $Target = $Unlisted[$Position];
else // 404
    $Target = $TopMenu["HomeMenu"];

if (is_page_authorized($Target, $User))
{
    if (file_exists("$Target/index.php"))
	require_once ("$Target/index.php");
    else
	require_once ("$Target/index.phtml");
}

