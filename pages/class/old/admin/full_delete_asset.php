<?php

function full_delete_asset($id)
{
    global $Database;

    if (($ret = @mark_as_deleted("class_gallery_asset", $id, "", true))->is_error())
	return ($id);
    foreach ($ret as $i => $v)
	if (preg_match("/^[A-Za-z]_content/", $i) == 0)
	    if (!unlink($v))
		return (new ErrorResponse("CannotDeleteFile", $v));
    if ($Database->query("
        DELETE FROM class_gallery_asset WHERE class_gallery_asset.id = ".$ret["id"]
    ) == false)
	return (new ErrorResponse("CannotDelete", $id));
    return (new Response);
}

