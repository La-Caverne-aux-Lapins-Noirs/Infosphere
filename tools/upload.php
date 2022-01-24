<?php

define("MINIMUM_PICTURE_SIZE", "-1");
define("EXACT_PICTURE_SIZE", "0");
define("MAXIMUM_PICTURE_SIZE", "1");

function mupload_file($src, $dst)
{
    if (UNIT_TEST)
	return (@rename($src, $dst));
    return (move_uploaded_file($src, $dst)); // @codeCoverageIgnore
}

function is_size_valid($mode, $rules, $uploaded)
{
    if ($mode == MINIMUM_PICTURE_SIZE)
	return ($rules <= $uploaded);
    if ($mode == EXACT_PICTURE_SIZE)
	return ($rules == $uploaded);
    if ($mode == MAXIMUM_PICTURE_SIZE)
	return ($rules >= $uploaded);
    return (false);
}

function upload_pdf($file, $target, $max_size = 1024 * 1024)
{
    if (($size = filesize($file)) > $max_size || $size < 0)
	return ("FileTooBig");
    if (mupload_file($file, $target) == false)
	return ("CannotMoveFile"); // @codeCoverageIgnore
    return ("");
}

function upload_archive($file, $target, $max_size = 1024 * 1024, $cipher = false)
{
    // Actuellement, cipher ne fait rien.

    if ($file == "")
	return ("NoFile");

    if (($size = filesize($file)) > $max_size || $size < 0)
	return ("FileTooBig");

    $zip = new ZipArchive;
    if ($zip->open($file) === true)
    {
	if (mupload_file($file, $target) == false)
	    return ("CannotMoveFile"); // @codeCoverageIgnore
	return ("");
    }

    // Maybe it is a tar.gz
    $res  = NULL;
    system("tar -t -f ".escapeshellarg($file)." > /dev/null", $res);
    if ($res != 0)
	return ("BadFileFormat");
    if (mupload_file($file, $target) == false)
	return ("CannotMoveFile"); // @codeCoverageIgnore
    return ("");
}

function upload_png($file, $target, $pic_size = [-1, -1], $mode = MAXIMUM_PICTURE_SIZE, $resize = false)
{
    $tmp_name = sys_get_temp_dir()."/".str_replace(".", "_", microtime(true)).".png";
    if (mupload_file($file, $tmp_name) == false)
	return (new ErrorResponse("MissingFile", $file));

    if (($img = @imagecreatefromjpeg($tmp_name)) == false)
    {
	if (($img = @imagecreatefrompng($tmp_name)) == false)
	{
	    unlink($tmp_name);
	    return (new ErrorResponse("BadFileFormat"));
	}
    }
    $size = getimagesize($tmp_name);
    unlink($tmp_name);

    if (!is_array($pic_size) || count($pic_size) < 2)
	return (new ErrorResponse("BadUsage"));

    if ($mode == EXACT_PICTURE_SIZE && $resize == true)
    {
	if (($new_img = imagecreatetruecolor($pic_size[0], $pic_size[1])) == false)
	    return (new ErrorResponse("CannotCreateResizePicture")); // @codeCoverageIgnore
	imagecopyresampled($new_img, $img, 0, 0, 0, 0, $pic_size[0], $pic_size[1], $size[0], $size[1]);
	imagedestroy($img);
	$img = $new_img;
    }
    else
    {
	$valid = true;
	if ($pic_size[0] != -1)
	    $valid = $valid && is_size_valid($mode, $pic_size[0], $size[0]);
	if ($pic_size[1] != -1)
	    $valid = $valid && is_size_valid($mode, $pic_size[1], $size[1]);
	if ($valid == false)
	    return (new ErrorResponse("InvalidPictureSize"));
    }

    imagesavealpha($img, true);
    if (imagepng($img, $target) == false)
	return (new ErrorResponse("CannotWritePngFile")); // @codeCoverageIgnore
    imagedestroy($img);
    return (new Response);
}
