<?php

function EditProperty($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    $cnt = 0;
    unset($data["action"]);
    foreach ($data as $k => $v)
    {
	if (($k = resolve_codename("configuration", $k, "codename", true))->is_error())
	    bad_request();
	extract($k->value);
	if ($secured)
	    $v = secure_data($v);
	db_update_one("configuration", $id, ["value" => $v]);
	$cnt += 1;
    }
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
    ]));
}

$Tab = [
    "PUT" => [
	"" => [
	    "is_admin",
	    "EditProperty",
	],
    ],
];
