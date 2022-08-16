<?php

require ("activities.php");

$Tab = [
    "GET" => [
	"" => [
	    "is_teacher",
	    "DisplayActivity"
	],
    ],
    "POST" => [
	"" => [
	    "is_director",
	    "AddActivity"
	],
	"medal" => [
	    "is_teacher_for_activity",
	    "AddMedal"
	],
	"ressource" => [
	    "is_teacher_for_activity",
	    "AddRessource"
	],
	"subject" => [
	    "is_teacher_for_activity",
	    "SetSubject"
	],
	"wallpaper" => [
	    "is_teacher_for_activity",
	    "AddMood"
	],
	"intro" => [
	    "is_teacher_for_activity",
	    "AddMood"
	],
	"mood" => [
	    "is_teacher_for_activity",
	    "AddMood"
	],
	"instantiate" => [
	    "is_director_for_activity",
	    "Instantiate",
	],
    ],
    "PUT" => [
	"" => [
	    "is_teacher_for_activity",
	    "EditActivity"
	],
	"cycle" => [
	    "is_director_for_activity",
	    "SetActivityLink"
	],
	"teacher" => [
	    "is_director_for_activity",
	    "SetActivityLink"
	],
	"support" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"medal" => [
	    "is_teacher_for_activity",
	    "EditMedal"
	],
	"scale" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"mcq" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"satisfaction" => [
	    "is_teacher_or_director_for_activity",
	    "SetActivityLink"
	],
	"skill" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"software" => [
	    "is_teacher_for_activity",
	    "SetSoftware"
	],
	"ressource" => [
	    "is_assistant_for_activity",
	    "GetRessourceDir"
	],
	"mood" => [
	    "is_assistant_for_activity",
	    "GetMoodDir"
	],
    ],
    "DELETE" => [
	"" => [
	    "is_teacher_for_activity",
	    "DeleteActivity"
	],
	"medal" => [
	    "is_teacher_for_activity",
	    "AddMedal"
	],
	"cycle" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"teacher" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"laboratory" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"support" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"class_asset" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"class" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"activity" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"medal" => [
	    "is_teacher_for_activity",
	    "EditMedal"
	],
	"scale" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"mcq" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"satisfaction" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"skill" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"software" => [
	    "is_teacher_for_activity",
	    "RemoveSoftware"
	],
	"ressource" => [
	    "is_teacher_for_activity",
	    "RemoveRessource"
	]
    ]
];

