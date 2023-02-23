<?php

function print_datetime($field, $valx, $check_button = true, $classdate = "", $classcheck = "", $onchange = "")
{
    $val = "";
    if (is_object($valx))
	$val = $valx->$field;
    else if (is_array($valx) && isset($valx[$field]))
	$val = $valx[$field];
    else
	$val = NULL;
    if ($check_button == false && $val == NULL)
	$val = "";
    echo '<input class="'.$classdate.'" type="datetime-local" name="'.$field.'_date" id="x'.$field.'" value="'.db_form_date($val).'" '.($val === NULL ? "disabled" : "").' onchange="'.$onchange.'" />';
    if ($check_button)
	echo '<input class="'.$classcheck.'" type="checkbox" name="'.$field.'_check" onclick="enable(\'x'.$field.'\', this.checked);" '.($val == NULL ? "" : "checked").' />';
}
