<?php

require ("activities.php");
require ("sessions.php");

$Tab = [
    "GET" => [
	"" => [
	    "is_teacher",
	    "DisplaySession"
	]
    ],
    "PUT" => [
	"" => [
	    "is_teacher_or_director_for_session",
	    "EditSession",
	],
	"room" => [
	    "is_teacher_or_director_for_session",
	    "SetSessionRoom"
	]
    ],
    "POST" => [
	"" => [
	    "is_teacher", // Ca devrait etre le prof de l'activité...
	    "AddSession"
	]
    ],
    "DELETE" => [
	"" => [
	    "is_teacher_or_director_for_session",
	    "DeleteSession"
	],
	"room" => [
	    "is_teacher_or_director_for_session",
	    "SetSessionRoom"
	]
    ]
];
