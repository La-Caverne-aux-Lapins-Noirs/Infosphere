<?php

require ("activities.php");

$Tab = [
    "GET" => [
	"" => [
	    "am_i_director,am_i_cycle_director,is_teacher",
	    "DisplayActivity"
	],
	"admin" => [
	    "is_teacher",
	    "DisplayActivityAdmin"
	],
    ],
    "POST" => [
	"" => [
	    "am_i_director,am_i_cycle_director",
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
	"icon" => [
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
	"duplicate" => [
	    "is_teacher",
	    "DuplicateActivity",
	],
    ],
    "PUT" => [
	"" => [
	    "is_teacher_for_activity",
	    "EditActivity"
	],
	"template_link" => [
	    "is_teacher_for_activity",
	    "EditTemplateLink"
	],
	"reset_template_link" => [
	    "is_teacher_for_activity",
	    "ResetTemplateLink"
	],
	"move" => [
	    "is_teacher_for_activity",
	    "MoveActivity",
	],
	"cycle" => [
	    "is_director_for_activity",
	    "SetActivityLink"
	],
	"teacher" => [
	    "am_i_director,am_i_cycle_director",
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
	"registration" => [
	    "everybody", // Autorisation trop complexe pour etre ici.
	    "SetActivityRegistration",
	],
	"pickup" => [
	    "is_teacher_for_activity",
	    "PickupActivity",
	],
    ],
    "DELETE" => [
	"" => [
	    "am_i_director,am_i_cycle_director",
	    "DeleteActivity"
	],
	"subject" => [
	    "is_teacher_for_activity",
	    "SetSubject"
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
	    "am_i_director,am_i_cycle_director",
	    "SetActivityLink"
	],
	"laboratory" => [
	    "is_director_for_activity",
	    "SetActivityLink"
	],
	"support" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"support_asset" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"support_category" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"activity" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
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
	],
	"registration" => [
	    "everybody", // Autorisation trop complexe pour etre ici.
	    "SetActivityRegistration",
	],
    ]
];

