<?php

function fetch_class($id = -1, $by_name = false)
{
    if (($class = fetch_data(
	"class", $id, ["name", "description"], "codename", $by_name)
    )->is_error())
	return ($class); // @codeCoverageIgnore
    $class = $class->value;
    foreach ($class as $k => $v)
    {
	$forge = forge_language_fields(["name", "content", "link"], true, true);
	if (($class[$k]["asset"] = fetch_data(
	    "class_asset", -1,
	    ["name", "content", "link"],
	    "", false, true, false,
	    ["id_class" => $v["id"]]
	))->is_error())
	    return ($class[$k]["assets"]);
	$class[$k]["asset"] = $class[$k]["asset"]->value;
    }
    if ($id != -1)
	$class = $class[array_key_first($class)];
    return (new ValueResponse($class));
}

