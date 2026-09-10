<?php

require ("activities.php");
require ("sessions.php");

$Tab = [
    "GET" => [
	"" => [
	    "am_i_teacher",
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
	    "am_i_teacher", // Ca devrait etre le prof de l'activité...
	    "AddSession"
	],
	"import" => [
	    "am_i_teacher",
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
