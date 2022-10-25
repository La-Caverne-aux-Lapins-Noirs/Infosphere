<?php

$date0 = "1970-01-01 00:00:00"; // date("Y-m-d H:i:s", 0);
$NoLocalisation = new DateTimeZone("Etc/UTC");

function random_name()
{
    return (md5(microtime()));
}

function is_between($val, $min, $max)
{
    $val = (int)$val;
    if ($val < $min)
	return (false);
    if ($val > $max)
	return (false);
    return (true);
}

function try_get($array, $key, $default = "", $id = NULL)
{
    if (!isset($array))
	return ($default);
    if (is_array($array) && isset($array[$key]))
    {
	if ($id != NULL && (!isset($array["id"]) || $array["id"] != $id))
	    return ($default);
	return ($array[$key]);
    }
    if (is_object($array) && isset($array->$key))
    {
	if ($id != NULL && (!isset($array->id) || $array->id != $id))
	    return ($default);
	return ($array->$key);
    }
    return ($default);
}

function is_symbol($str)
{
    if (!isset($str) || is_array($str) || !is_string($str))
	return (false);
    return (preg_match('/^[A-Za-z_][A-Za-z0-9_\-.]*$/', $str) == 1);
}

function is_number($str)
{
    if (!isset($str) || is_array($str) || is_object($str))
	return (false);
    if (is_int($str))
	return (true);
    return (preg_match('/^[+-]?[0-9]+$/', $str) == 1);
}

function date_to_timestamp($s)
{
    global $NoLocalisation;

    if ($s === NULL)
	return ($s);
    if (is_number($s))
    {
	if ($s < 0 && 0) // Retiré, car cela empeche les template de marcher.
	    return (0); // Mais si c'était la, y avait peut etre une raison...
	return ($s);
    }
    try
    {
	if (($ret = new DateTimeImmutable("$s", $NoLocalisation)) != false)
	    return ($ret->getTimestamp());
    }
    catch (Exception $e)
    {}
    if (($ret = date_create_from_format("Y-m-d H:i:s", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    if (($ret = date_create_from_format("Y-m-d\TH:i:s", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    if (($ret = date_create_from_format("Y-m-d H:i", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    if (($ret = date_create_from_format("Y-m-d\TH:i", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    if (($ret = date_create_from_format("Y-m-d", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    if (($ret = date_create_from_format("d/m/Y H:i:s", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    if (($ret = date_create_from_format("d/m/Y\TH:i:s", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    if (($ret = date_create_from_format("d/m/Y H:i", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    if (($ret = date_create_from_format("d/m/Y\TH:i", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    if (($ret = date_create_from_format("d/m/Y", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
    $ret = new DateTimeImmutable("today", $NoLocalisation);
    return ($ret->getTimestamp());
}

function datex($format, $tm)
{
    global $NoLocalisation;

    $dt = new DateTime("now", $NoLocalisation);
    if (is_string($tm))
	$tm = date_to_timestamp($tm);
    $dt->setTimestamp($tm);
    return ($dt->format($format));
}

function now()
{
    global $NoLocalisation;

    $dt = new DateTime("now"); // Avec localisation
    return ($dt->getTimestamp() + $dt->getOffset());
}

function remove_hour($s)
{
    $ret = date_to_timestamp($s);
    $ret /= 60 * 60 * 24;
    $ret = (int)$ret;
    $ret *= 60 * 60 * 24;
    return ($ret);
}

function day_to_timestamp($s)
{
    global $NoLocalisation;

    if (($ret = date_create_from_format("Y-m-d H:i:s", $s, $NoLocalisation)) == false)
	$ret = date_create_from_format("Y-m-d", $s, $NoLocalisation);
    $ret = new DateTimeImmutable("today", $NoLocalisation);
    return ($ret->getTimestamp());
}

function hour_to_timestamp($s)
{
    $e = explode(":", $s);
    if (!isset($e[0]) || $e == false || $e[0] == "")
	return (0);
    if (!isset($e[1]))
	$e[1] = 0;
    if (!isset($e[2]))
	$e[2] = 0;
    return ($e[0] * 60 * 60 + $e[1] * 60 + $e[2]);
}

function extract_day_to_timestamp($s)
{
    if (!is_number($s))
	$s = date_to_timestamp($s);
    $s = (int)($s / (60 * 60 * 24)) * 60 * 60 * 24;
    return ($s);
}

function extract_day($s)
{
    return (datetime_local(extract_day_to_timestamp($s)));
}

function check_date($s)
{
    global $NoLocalisation;

    if (!isset($s))
	return (false);
    if (strchr($s, "-"))
    {
	$x = explode("-", $s);
	if (count($x) != 3)
	    return (false);
	if (($TheT = strpos($x[2], "T")))
	{
	    $x[2] = substr($x[2], 0, $TheT);
	    $s = substr($s, 0, strpos($s, "T"));
	}
	if (checkdate($x[1], $x[2], $x[0]) == false)
	    return (false);
	$format = "Y-m-d";
	$d = DateTime::createFromFormat($format, $s, $NoLocalisation);
    }
    else
    {
	$x = explode("/", $s);
	if (count($x) != 3)
	    return (false);
	if (checkdate($x[1], $x[0], $x[2]) == false)
	    return (false);
	$format = "d/m/Y";
	$d = DateTime::createFromFormat($format, $s, $NoLocalisation);
    }
    return ($d->format($format));
}

function time_to_timestamp($time)
{
    if ($time == NULL)
	return ($time);
    if (is_number($time))
	return ($time);
    $mt = [];
    if (preg_match("/^([0-9]+):([0-9]+):([0-9]+[\.[0-9]+]?)$/", $time, $mt))
	return ((int)$mt[1] * 60 * 60 + (int)$mt[2] * 60 + (float)$mt[3]);
    if (preg_match("/^([0-9]+):([0-9]+)$/", $time, $mt))
	return ((int)$mt[1] * 60 * 60 + (int)$mt[2] * 60);
    if (preg_match("/^([0-9]+)$/", $time, $mt))
	return ((int)$mt[1] * 60 * 60);
    return (0);
}

function american_date($d, $only_day = false, $only_hour = false, $no_seconds = false)
{
    if ($d == NULL)
	return ($d);
    if (!is_number($d))
	$d = date_to_timestamp($d);
    if ($no_seconds)
	$secs = "";
    else
	$secs = ":s";
    if ($only_day)
	return (datex("Y-m-d", $d)); // @codeCoverageIgnore
    if ($only_hour)
	return (datex("H:i$secs", $d)); // @codeCoverageIgnore
    return (datex("Y-m-d H:i$secs", $d)); // @codeCoverageIgnore
}

function datetime_local($d, $only_day = false)
{
    if ($d === NULL)
	return (NULL);
    if ($d === "" || $d == -1)
	return ("");
    if ($only_day)
	return (datex("Y-m-d\T00:00:00", $d));
    return (datex("Y-m-d\TH:i:s", $d)); // @codeCoverageIgnore
}

function db_form_date($d = NULL, $only_day = false)
{
    return (datetime_local(date_to_timestamp($d), $only_day));
}

function european_date($d, $only_day = false, $only_hour = false, $no_seconds = false)
{
    if ($d == NULL)
	return ($d);
    if ($no_seconds)
	$secs = "";
    else
	$secs = ":s";
    if ($only_day)
	return (datex("d/m/Y", $d)); // @codeCoverageIgnore
    if ($only_hour)
	return (datex("H:i$secs", $d)); // @codeCoverageIgnore
    return (datex("d/m/Y H:i$secs", $d)); // @codeCoverageIgnore
}

function human_date($d = NULL, $only_day = false, $only_hour = false, $no_seconds = false)
{
    if ($d === NULL)
	return (human_date(now(), $only_day, $only_hour, $no_seconds));
    if (!is_number($d))
	$d = date_to_timestamp($d);
    // @codeCoverageIgnoreStart
    if (0) // Localisation par IP pour voir si on est aux états unis
	return (american_date($d, $only_day, $only_hour, $no_seconds));
    return (european_date($d, $only_day, $only_hour, $no_seconds));
     // @codeCoverageIgnoreEnd
}

function litteral_date($d, $onlyday = false)
{
    global $Dictionnary;

    if ($onlyday)
	$d = explode(" ", datex("l d F", $d));
    else
	$d = explode(" ", datex("l d F H:i", $d));
    foreach ($d as &$x)
    {
	if (isset($Dictionnary[$x]))
	    $x = $Dictionnary[$x];
    }
    return (implode(" ", $d));
}

function weekday_date($d, $offset = NULL, $label = true)
{
    global $Dictionnary;
    global $one_week;
    global $one_day;
    global $one_hour;
    global $Days;
    global $date0;

    if ($offset == NULL)
	$offset = $date0;
    if ($d == NULL)
	return ("");
    if (!is_number($d))
	$d = date_to_timestamp($d);
    if (($d -= date_to_timestamp($offset)) < 0)
	$d = 0;
    $out = "";
    if ($label)
	$out .= $Dictionnary["Week"].": ";
    $out .= ((int)($d / $one_week) + 1)." ";
    $out .= $Dictionnary[$Days[(int)($d / $one_day) % 7]]." ";
    $out .= datex("H:i", $d);
    return ($out);
}

function weekday_to_timestamp($week, $day, $hour)
{
    global $one_week;
    global $one_day;
    global $date0;

    if ($week == NULL && $day == NULL && $hour == NULL)
	return (NULL);
    if (is_number($week))
	$week -= 1;
    else
	$week = 0;
    if (is_number($day))
	$day -= 1;
    else
	$day = 0;
    $hour = time_to_timestamp($hour);
    return (date_to_timestamp($date0)
	+ $week * $one_week
	+ $day * $one_day
	+ $hour
    );
}

function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data)
{
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

function day_of_week($date)
{
    return (datex("N", $date) - 1);
}

function to_timestamp(&$x)
{
    if ($x == NULL)
	return ;
    if (is_array($x))
    {
	foreach ($x as $y)
	{
	    to_timestamp($y);
	}
    }
    $x = date_to_timestamp($x);
}

function from_timestamp(&$x)
{
    if ($x == NULL)
	return ;
    if (is_array($x))
    {
	foreach ($x as $y)
	{
	    from_timestamp($y);
	}
    }
    $x = db_form_date($x);
}

function first_day_of_week($dt)
{
    global $NoLocalisation;

    if ($dt == 0)
	$dt = 1;
    $dt = db_form_date($dt);
    $d = new DateTime($dt, $NoLocalisation);
    if (datex("l", $d->getTimestamp()) != "Monday")
	$d->modify("last monday");
    $d->modify("first second");
    return ($d->getTimestamp() - 1);
}

function first_second_of_day($dt)
{
    global $NoLocalisation;

    $d = new DateTime(db_form_date($dt), $NoLocalisation);
    $d->modify("first second");
    return ($d->getTimestamp() - 1);
}

function convert_date($post)
{
    if (!isset($post["emergence_date"]))
	$post["emergence_date"] = @weekday_to_timestamp(
	    $post["week_emergence_date"], $post["day_emergence_date"], $post["hour_emergence_date"]
	);

    if (!isset($post["done_date"]))
	$post["done_date"] = @weekday_to_timestamp(
	    $post["week_done_date"], $post["day_done_date"], $post["hour_done_date"]
	);

    if (!isset($post["registration_date"]))
	$post["registration_date"] = @weekday_to_timestamp(
	    $post["week_registration_date"], $post["day_registration_date"], $post["hour_registration_date"]
	);
    if (!isset($post["close_date"]))
	$post["close_date"] = @weekday_to_timestamp(
	    $post["week_close_date"], $post["day_close_date"], $post["hour_close_date"]
	);

    if (!isset($post["subject_appeir_date"]))
	$post["subject_appeir_date"] = @weekday_to_timestamp(
	    $post["week_subject_appeir_date"], $post["day_subject_appeir_date"], $post["hour_subject_appeir_date"]
	);
    if (!isset($post["pickup_date"]))
	$post["pickup_date"] = @weekday_to_timestamp(
	    $post["week_pickup_date"], $post["day_pickup_date"], $post["hour_pickup_date"]
	);
    if (!isset($post["subject_disappeir_date"]))
	$post["subject_disappeir_date"] = @weekday_to_timestamp(
	    $post["week_subject_disappeir_date"], $post["day_subject_disappeir_date"], $post["hour_subject_disappeir_date"]
	);
    if (!@strlen($post["begin_date"]))
    {
	if (isset($post["week_session_date"]))
	    $post["begin_date"] = @weekday_to_timestamp(
		$post["week_session_date"], $post["day_session_date"], $post["hour_begin_date"]
	    );
	else if (isset($post["begin"]))
	    $post["begin_date"] = db_form_date(hour_to_timestamp($post["begin"]) + date_to_timestamp($post["day"]));
    }
    if (!@strlen($post["end_date"]))
    {
	if (isset($post["week_session_date"]))
	    $post["end_date"] = @weekday_to_timestamp(
		$post["week_session_date"], $post["day_session_date"], $post["hour_end_date"]
	    );
	else if (isset($post["end"]))
	    $post["end_date"] = db_form_date(hour_to_timestamp($post["end"]) + date_to_timestamp($post["day"]));
    }
    return ($post);
}


function get_day($d)
{
    if (($day = date('w', date_to_timestamp($d))) == 0) // 0: dimanche
	$day = 6;
    else
	$day -= 1;
    return ($day);
}

function get_week($d)
{
    global $one_week;
    global $one_day;

    $d = date_to_timestamp($d);
    $d -= $one_day * 4;
    return (1 + (int)($d / $one_week));
}

function handle_french($body)
{
    $body = str_replace("é", "&eacute;", $body);
    $body = str_replace("è", "&egrave;", $body);
    $body = str_replace("à", "&agrave;", $body);
    $body = str_replace("ù", "&ugrave;", $body);
    $body = str_replace("ç", "&ccedil;", $body);
    return ($body);
}

function array_to_object($arr)
{
    if (is_object($arr))
	return ($arr);
    $obj = new StdClass;
    foreach ($arr as $k => &$v)
	$obj->$k = &$v;
    return ($obj);
}

function object_to_array($obj)
{
    if (is_array($obj))
	return ($obj);
    $arr = [];
    foreach ($obj as $k => &$v)
	$arr[$k] = &$v;
    return ($arr);
}

