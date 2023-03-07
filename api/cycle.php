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
	"activity" => [
	    "is_director_for_cycle",
	    "SetMatter",
	],
	"user" => [
	    "is_director_for_cycle",
	    "SetUser",
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
	"activity" => [
	    "is_director_for_cycle",
	    "SetMatterProps",
	],
	"laboratory" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"user" => [
	    "is_director_for_cycle",
	    "SetUserProps",
	],
	"school" => [
	    "is_director_for_cycle",
	    "SetSchool",
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
	    "SetMatter",
	],
	"teacher" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"laboratory" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"school" => [
	    "is_director_for_cycle",
	    "SetSchool",
	],
    ],
];
