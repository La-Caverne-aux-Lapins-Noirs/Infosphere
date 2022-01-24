<?php

if (@is_number($_GET["a"]))
{
    if (($category = fetch_category($_GET["a"])) == NULL)
	$ErrorMsg = "InvalidCategoryId";
    $Position = $Position."&a=".$_GET["a"];
}
else
    $category = NULL;
