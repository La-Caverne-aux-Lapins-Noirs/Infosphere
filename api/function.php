<?php

require ("functions.php");

$Tab = [
    "GET" => [
	"" => [
	    "public",
	    "DisplayFunction",
	],
    ],
    "POST" => [
	"" => [
	    "is_admin",
	    "AddFunction",
	],
	"user" => [
	    "is_admin",
	    "AddFunctionAuthorization",
	],
    ],
    "DELETE" => [
	"" => [
	    "is_admin",
	    "DeleteFunction"
	],
	"user" => [
	    "is_admin",
	    "DeleteFunctionAuthorization",
	]
    ]
];

