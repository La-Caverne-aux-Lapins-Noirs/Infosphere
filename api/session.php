<?php

require ("activities.php");
require ("sessions.php");

$Tab = [
    "GET" => [
	"" => [
	    "can_view_session",
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
	"sign_in_sheet" => [
	    "is_teacher_or_director_for_session",
	    "GenerateSessionSignInSheet"
	],
	"" => [
	    "am_i_member",
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
