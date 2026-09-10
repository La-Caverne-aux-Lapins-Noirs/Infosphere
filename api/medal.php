<?php

require_once ("medals.php");

$Tab = [
    "GET" => [
	"" => [
	    "everybody",
	    "DisplayMedals",
	],
    ],
    "POST" => [
	"" => [
	    "am_i_teacher",
	    "AddMedal",
	],
	"ressource" => [
	    "am_i_teacher",
	    "AddRessource",
	]
    ],
    "PUT" => [
	"" => [
	    "am_i_teacher",
	    "MoveMedal",
	],
	"ressource" => [
	    "is_assistant_for_activity",
	    "GetRessourceDir"
	],
    ],
    "DELETE" => [
	"" => [
	    "am_i_teacher",
	    "DeleteMedal",
	],
	"ressource" => [
	    "am_i_teacher",
	    "RemoveRessource",
	],
    ],
];
