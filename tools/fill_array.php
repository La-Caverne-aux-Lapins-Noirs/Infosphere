<?php

function fill_array(array $data)
{
    foreach($data as $year=>$array)
    {
        $keys = array_keys($array);
        $min = min($keys); $max = 52;
        if(!isset($data[$year+1])){
            $max = max($keys);
        }
        $data[$year] = $data[$year] + array_fill($min,$max-$min+1, 0);
        ksort($data[$year]);
    }
}

