<?php

function same_day($a, $b)
{
    return (date('d/m/Y', $a) == date('d/m/Y', $b));
}

