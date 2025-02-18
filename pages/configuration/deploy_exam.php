<?php

$out = hand_request([
    "command" => "deployexam",
    "users" => [["codename" => "dua.lipa", "id" => 87]],
    "repo" => "test/",
    "subject_name" => base64_encode("test_subject.txt"),
    "subject" => base64_encode("Le sujet est bien au complet ! Héhé (Petit test utf-8)"),
    "room" => "sm1"
]);

if ($out === false || $out === NULL)
    echo "Cannot reach the hand";
print_r($out);
