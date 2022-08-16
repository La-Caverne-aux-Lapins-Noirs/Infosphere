<?php

function transfert($fields, &$obj, $array)
{
    // Import depuis les fonctions requetes
    if (is_array($array))
    {
	foreach ($fields as $label)
	{
	    if (isset($array[$label]) && $array[$label] != NULL)
	    {
		$obj->$label = $array[$label];
	    }
	}
    }
    else if (is_object($array))
    {
	foreach ($fields as $label)
	{
	    if (isset($array->$label) && $array->$label != NULL && isset($obj->$label))
	    {
		$obj->$label = $array->$label;
	    }
	}
    }
}
