<?php

$out = hand_request([
    "command" => "save_user",
    "login" => "mathieu.lamarque",
    "first_name" => "Mathieu",
    "last_name" => "Lamarque",
    "mail" => "mathieu.lamarque4@gmail.com",
    "password" => "4Bew[kCs#_z)",
    "db_password" => ".wk7ebMa+,;2",
    "school" => "efrits",
    "dry" => "1"
]);

if ($out === false || $out === NULL)
    echo "Cannot reach the hand";
else
    print_r($out);

