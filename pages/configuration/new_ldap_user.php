<?php

$out = hand_request([
    "command" => "newuser",
    "user" => "mathieu.lamarque",
    "first_name" => "Mathieu",
    "last_name" => "Lamarque",
    "mail" => "mathieu.lamarque4@gmail.com",
    "password" => "4Bew[kCs#_z)",
    "bddpassword" => ".wk7ebMa+,;2",
    "school" => "efrits",
    "dry" => "1"
]);

if ($out === false || $out === NULL)
    echo "Cannot reach the hand";
else
    print_r($out);

