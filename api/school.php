<?php

require_once ("schools.php");

$Tab = [
    "GET" => [
	"" => [
	    "is_teacher_or_director",
	    "DisplaySchool"
	]
    ],
    "PUT" => [
	"director" => [
	    "only_admin",
	    "SetDirector"
	],
	"user" => [
	    "is_director_for_school",
	    "SetStudent",
	],
	"cycle" => [
	    "is_director_for_school",
	    "SetCycle",
	]
    ],
    "POST" => [
	"" => [
	    "only_admin",
	    "AddSchool"
	]
    ],
    "DELETE" => [
	"" => [
	    "only_admin",
	    "DeleteSchool"
	],
	"user" => [
	    "is_director_for_school",
	    "SetStudent"
	],
	"director" => [
	    "only_admin",
	    "SetDirector"
	],
	"cycle" => [
	    "is_director_for_school",
	    "SetCycle",
	]
    ]
];

