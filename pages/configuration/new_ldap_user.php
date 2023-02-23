<?php

$out = hand_request([
    "command" => "newuser",
    "user" => "jason.brillante",
    "first_name" => "Jason",
    "last_name" => "Brillante",
    "mail" => "jason.brillante@gmail.com",
    "password" => "the_test_password",
    "school" => "efrits",
    //"dry" => "1"
]);

if ($out === false || $out === NULL)
    echo "Cannot reach the hand";
else
    print_r($out);

