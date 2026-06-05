<?php

function RecordSupportProgress($id, $data, $method, $output, $module)
{
    if (!isset($data["progress"]))
	$data["progress"] = 100;
    return (support_progress_record_asset($id, $data["progress"]));
}

$Tab = [
    "POST" => [
	"" => [
	    "logged_in",
	    "RecordSupportProgress",
	],
    ],
];

?>
