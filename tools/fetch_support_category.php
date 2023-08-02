<?php

function fetch_support_category(
    $id = -1,
    $by_name = false,
    $support_by_name = false,
    $asset_by_name = false
)
{
    if (($class = fetch_data(
	"support_category", $id, ["name", "description"], "codename", $by_name)
    )->is_error())
        return ($class); // @codeCoverageIgnore
    $categories = $class->value;
    if (count($categories) == 0)
	return (new ValueResponse([]));
    foreach ($categories as &$category)
    {
	$category["type"] = "category";
	$category["selected"] = true;
	if (($category["support"] = fetch_support
	    (-1, $category["id"], $support_by_name, $asset_by_name)
	)->is_error())
	    return ($category["support"]);
	$category["support"] = $category["support"]->value;
    }
    if ($id != -1)
    {
	$categories = $categories[array_key_first($categories)];
	$categories["type"] = "support";
    }
    return (new ValueResponse($categories));
}

