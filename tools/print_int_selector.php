<?php

function print_int_selector($min, $max, $field, $val = "", $class="", $id = "", $attr)
{
    echo "<select name=\"$field\" class=\"$class\" ".($id != "" ? "id=\"$id\"" : "")." $attr>\n";
    for ($i = $min; $i <= $max; ++$i)
    {
	echo "<option value=\"$i\" ".($val == $i ? "selected" : "").">\n";
	echo $i;
	echo "</option>\n";
    }
    echo "</select>\n";
}
