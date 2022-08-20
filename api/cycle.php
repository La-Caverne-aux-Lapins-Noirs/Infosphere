<?php

require ("cycles.php");

$Tab = [
    "GET" => [
	"" => [
	    "everybody",
	    "DisplayCycles",
	],
    ],
    "POST" => [
	"" => [
	    "is_director",
	    "AddCycle",
	],
    ],
    "PUT" => [
	"" => [
	    "is_director_for_cycle",
	    "EditCycle",
	],
	"teacher" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"laboratory" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"user" => [
	    "is_director_for_cycle",
	    "SetUser",
	],
	"activity" => [
	    "is_director_for_cycle",
	    "AddMatter",
	],
    ],
    "DELETE" => [
	"" => [
	    "is_director_for_cycle",
	    "DeleteCycle",
	],
	"user" => [
	    "is_director_for_cycle",
	    "SetUser",
	],	
	"activity" => [
	    "is_director_for_cycle",
	    "RemoveMatter",
	],
	"teacher" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"laboratory" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	]
    ],
];