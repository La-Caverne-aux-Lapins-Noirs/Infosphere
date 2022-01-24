<?php

function print_datetime($field, $valx, $check_button = true, $classdate = "", $classcheck = "")
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
    echo '<input class="'.$classdate.'" type="datetime-local" name="'.$field.'" id="x'.$field.'" value="'.db_form_date($val).'" '.($val === NULL ? "disabled" : "").' />';
    if ($check_button)
	echo '<input class="'.$classcheck.'" type="checkbox" onclick="enable(\'x'.$field.'\', this.checked);" '.($val == NULL ? "" : "checked").' />';
}
