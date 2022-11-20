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
	    "is_teacher",
	    "AddMedal",
	],
	"ressource" => [
	    "is_teacher",
	    "AddRessource",
	]
    ],
    "PUT" => [
	"" => [
	    "is_teacher",
	    "MoveMedal",
	],
	"ressource" => [
	    "is_assistant_for_activity",
	    "GetRessourceDir"
	],
    ],
    "DELETE" => [
	"" => [
	    "is_teacher",
	    "DeleteMedal",
	],
	"ressource" => [
	    "is_teacher",
	    "RemoveRessource",
	],
    ],
];
