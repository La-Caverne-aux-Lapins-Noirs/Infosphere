<?php

function fetch_support(
    $id = -1,
    $cat = NULL,
    $by_name = false,
    $asset_by_name = false,
    $load_assets = true
)
{
    if ($cat == NULL)
	$cat = [];
    else
	$cat = ["id_support_category" => $cat];
    if (($supports = fetch_data(
    	"support", $id,
	["name", "description"],
	"codename", $by_name, true, false,
	$cat,
	["chapter ASC"]
    ))->is_error())
        return ($supports);
    if (count($supports = $supports->value) == 0)
	return (new ValueResponse([]));
    foreach ($supports as &$support)
	{
	    $support["type"] = "support";
	    $support["selected"] = true;
	    if ($load_assets)
	    {
		if (($out = fetch_support_asset(-1, $support["id"], $asset_by_name))->is_error())
		    return ($out);
		$support["asset"] = $out->value;
	    }
	    else
		$support["asset"] = [];
	}
    if ($id != -1)
	return (new ValueResponse($supports[array_key_first($supports)]));
    return (new ValueResponse($supports));
}

