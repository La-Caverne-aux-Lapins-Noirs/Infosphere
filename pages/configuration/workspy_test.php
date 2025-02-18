<?php

$out = hand_request([
    "command" => "getcomputerroom",
    "name" => "jemison"
]);

if ($out === false || $out === NULL)
    echo "Cannot reach the hand 1";

print_r($out);
print_r("\n");

$out = hand_request([
    "command" => "getexamstudents"
]);

if ($out === false || $out === NULL)
    echo "Cannot reach the hand 2";

print_r($out);
