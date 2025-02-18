<?php

$out = hand_request([
    "command" => "newhome",
    "user" => "dua.lipa",
    "id" => 87
]);

if ($out === false || $out === NULL)
    echo "Cannot reach the hand";
else
    print_r($out);
