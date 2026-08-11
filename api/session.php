<?php

require ("activities.php");
require ("sessions.php");

$Tab = [
    "GET" => [
	"" => [
	    "is_teacher",
	    "DisplaySession"
	],
	"export" => [
	    "is_teacher_or_director_for_session",
	    "ExportSessionDescription"
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
	],
	"jury" => [
	    "is_teacher_or_director_for_session",
	    "SetSessionJury"
	]
    ],
    "POST" => [
	"" => [
	    "is_teacher", // Ca devrait etre le prof de l'activité...
	    "AddSession"
	],
	"import" => [
	    "is_teacher",
	    "ImportSessionDescription"
	],
	"jury" => [
	    "is_teacher_or_director_for_session",
	    "SetSessionJury"
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
	],
	"jury" => [
	    "is_teacher_or_director_for_session",
	    "SetSessionJury"
	]
    ]
];
