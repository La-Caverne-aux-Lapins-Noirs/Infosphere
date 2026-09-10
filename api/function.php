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
	    "only_admin",
	    "AddFunction",
	],
	"user" => [
	    "only_admin",
	    "AddFunctionAuthorization",
	],
    ],
    "DELETE" => [
	"" => [
	    "only_admin",
	    "DeleteFunction"
	],
	"user" => [
	    "only_admin",
	    "DeleteFunctionAuthorization",
	]
    ]
];

