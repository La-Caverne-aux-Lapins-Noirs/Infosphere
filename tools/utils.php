<?php

function infosphere_timezone_is_valid($timezone)
{
    $timezone = trim((string)$timezone);
    if ($timezone == "")
        return (false);
    try
    {
        new DateTimeZone($timezone);
    }
    catch (Exception $e)
    {
        return (false);
    }
    return (true);
}

function infosphere_timezones_equivalent($left, $right)
{
    if (!infosphere_timezone_is_valid($left) || !infosphere_timezone_is_valid($right))
        return (false);
    if ((string)$left === (string)$right)
        return (true);

    try
    {
        $left_timezone = new DateTimeZone((string)$left);
        $right_timezone = new DateTimeZone((string)$right);
        $year = (int)gmdate("Y");
        foreach ([
            gmmktime(12, 0, 0, 1, 15, $year),
            gmmktime(12, 0, 0, 7, 15, $year),
            gmmktime(12, 0, 0, 1, 15, $year + 1),
            gmmktime(12, 0, 0, 7, 15, $year + 1),
        ] as $timestamp)
        {
            $date = new DateTimeImmutable("@".$timestamp);
            if ($left_timezone->getOffset($date) != $right_timezone->getOffset($date))
                return (false);
        }
    }
    catch (Exception $e)
    {
        return (false);
    }
    return (true);
}

function infosphere_system_timezone_name()
{
    static $timezone = NULL;

    if ($timezone !== NULL)
        return ($timezone);
    $timezone = "";

    $localtime = @realpath("/etc/localtime");
    $prefix = "/usr/share/zoneinfo/";
    if (is_string($localtime) && strpos($localtime, $prefix) === 0)
    {
        $candidate = substr($localtime, strlen($prefix));
        if (infosphere_timezone_is_valid($candidate))
            return ($timezone = $candidate);
    }

    if (is_readable("/etc/timezone"))
    {
        $candidate = trim((string)@file_get_contents("/etc/timezone"));
        if (infosphere_timezone_is_valid($candidate))
            return ($timezone = $candidate);
    }
    return ($timezone);
}

function infosphere_timezone_status()
{
    global $InfospherePHPTimezoneAtStartup;

    $system = infosphere_system_timezone_name();
    $startup = isset($InfospherePHPTimezoneAtStartup)
        ? (string)$InfospherePHPTimezoneAtStartup
        : (string)date_default_timezone_get();
    $configured = trim((string)ini_get("date.timezone"));
    $effective = (string)date_default_timezone_get();
    $expected = $system != "" ? $system : $startup;

    return ([
        "system" => $system,
        "expected" => $expected,
        "configured" => $configured,
        "startup" => $startup,
        "effective" => $effective,
        "ini_file" => (string)(php_ini_loaded_file() ?: ""),
        "startup_matches_system" => $system == "" || infosphere_timezones_equivalent($startup, $system),
        "effective_matches_system" => $system == "" || infosphere_timezones_equivalent($effective, $system),
    ]);
}

// Infosphere stores local wall-clock values as UTC-like timestamps.  Its
// historical now() implementation therefore requires PHP to use the same
// timezone as the host system.  Keep the application operational even when a
// package upgrade resets PHP to UTC, while preserving the startup value so an
// administrator can be warned about the underlying configuration mismatch.
$InfospherePHPTimezoneAtStartup = (string)date_default_timezone_get();
$InfosphereSystemTimezone = infosphere_system_timezone_name();
if ($InfosphereSystemTimezone != ""
    && !infosphere_timezones_equivalent($InfospherePHPTimezoneAtStartup, $InfosphereSystemTimezone))
    @date_default_timezone_set($InfosphereSystemTimezone);

$date0 = "1970-01-01 00:00:00"; // date("Y-m-d H:i:s", 0);
$NoLocalisation = new DateTimeZone("Etc/UTC");

function school_base_url_normalize($url)
{
    $url = trim((string)$url);
    if ($url == "")
        return ("");

    $parts = @parse_url($url);
    if (!is_array($parts) || !isset($parts["scheme"]) || !isset($parts["host"]))
        return (false);
    $scheme = strtolower((string)$parts["scheme"]);
    if ($scheme != "http" && $scheme != "https")
        return (false);
    if (isset($parts["user"]) || isset($parts["pass"]) || isset($parts["query"]) || isset($parts["fragment"]))
        return (false);

    $host = strtolower(rtrim((string)$parts["host"], "."));
    if ($host == "")
        return (false);
    $port = isset($parts["port"]) ? (int)$parts["port"] : NULL;
    if (($scheme == "http" && $port === 80) || ($scheme == "https" && $port === 443))
        $port = NULL;

    $path = isset($parts["path"]) ? preg_replace('#/+#', '/', (string)$parts["path"]) : "";
    if ($path == "/")
        $path = "";
    else if ($path != "")
        $path = "/".trim($path, "/");

    return ($scheme."://".$host.($port !== NULL ? ":".$port : "").$path);
}

function school_base_url_route_key($url)
{
    if (($url = school_base_url_normalize($url)) === false || $url == "")
        return (false);
    $parts = parse_url($url);
    $port = isset($parts["port"]) ? ":".(int)$parts["port"] : "";
    $path = isset($parts["path"]) ? rtrim((string)$parts["path"], "/") : "";
    return (strtolower((string)$parts["host"]).$port.$path);
}

function school_base_url_is_available($url, $except_school = -1)
{
    $key = school_base_url_route_key($url);
    if ($key === false)
        return (false);
    if ($key == "")
        return (true);
    if (!function_exists("db_select_rows") || !in_array("base_url", db_select_rows("school")))
        return (true);

    foreach (db_select_all("id, base_url FROM school WHERE deleted IS NULL AND base_url IS NOT NULL AND TRIM(base_url) != ''") as $school)
    {
        if ((int)$school["id"] == (int)$except_school)
            continue ;
        if (school_base_url_route_key($school["base_url"]) === $key)
            return (false);
    }
    return (true);
}

function get_school_from_url()
{
    static $done = false;
    static $selected = NULL;

    if ($done)
        return ($selected);
    $done = true;
    if (!function_exists("db_select_rows") || !in_array("base_url", db_select_rows("school")))
        return (NULL);

    $host_header = $_SERVER["HTTP_HOST"] ?? ($_SERVER["SERVER_NAME"] ?? "");
    $request = @parse_url("http://".$host_header);
    if (!is_array($request) || !isset($request["host"]))
        return (NULL);
    $request_host = strtolower(rtrim((string)$request["host"], "."));
    $request_port = isset($request["port"]) ? (int)$request["port"] : NULL;
    $request_path = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);
    if (!is_string($request_path) || $request_path == "")
        $request_path = "/";

    $best_length = -1;
    foreach (db_select_all("id, codename, base_url FROM school WHERE deleted IS NULL AND base_url IS NOT NULL AND TRIM(base_url) != ''") as $school)
    {
        $normalized = school_base_url_normalize($school["base_url"]);
        if ($normalized === false || $normalized == "")
            continue ;
        $parts = parse_url($normalized);
        $host = strtolower(rtrim((string)$parts["host"], "."));
        $port = isset($parts["port"]) ? (int)$parts["port"] : NULL;
        if ($host !== $request_host || $port !== $request_port)
            continue ;

        $path = isset($parts["path"]) ? rtrim((string)$parts["path"], "/") : "";
        if ($path != "" && $request_path !== $path && strpos($request_path, $path."/") !== 0)
            continue ;
        if (strlen($path) <= $best_length)
            continue ;
        $best_length = strlen($path);
        $selected = $school;
        $selected["base_url"] = $normalized;
    }
    return ($selected);
}

function get_school_name_from_url()
{
    if (($school = get_school_from_url()) !== NULL)
        return ($school["codename"]);

    $server = $_SERVER["SERVER_NAME"] ?? "";
    $url = explode(".", $server);
    if (!count($url) || $url[0] == "")
        return ("");
    if ($url[0] != "intra") // pour gérer nom_ecole.efrits.fr par exemple.
        return ($url[0]); // au cas ou nom_ecole soit deja pris.
    return (count($url) >= 2 ? $url[count($url) - 2] : $url[0]);
}

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

function is_symbol($str, $pattern = false)
{
    if (!isset($str) || is_array($str) || !is_string($str))
	return (false);
    if ($pattern)
	return (preg_match('/^[A-Za-z_%][A-Za-z0-9_\-.%]*$/', $str) == 1);
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
    if (($ret = date_create_from_format("Y-m-d H:i:s.u", $s, $NoLocalisation)) != false)
	return ($ret->getTimestamp());
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

function datex($format, $tm = NULL)
{
    global $NoLocalisation;

    if ($tm === NULL)
	$tm = now();
    $dt = new DateTime("now", $NoLocalisation);
    if (is_string($tm))
	$tm = date_to_timestamp($tm);
    $dt->setTimestamp($tm);
    return ($dt->format($format));
}

function virtual_now_admin_mode_enabled()
{
    global $User;

    if (isset($User) && is_array($User) && isset($User["admin_mode"]) && $User["admin_mode"])
	return (true);
    if (!isset($_COOKIE["admin_mode"]))
	return (false);
    $value = strtolower(trim((string)$_COOKIE["admin_mode"]));
    return ($value != "" && $value != "0" && $value != "false" && $value != "off");
}

function virtual_now_original_user_is_admin()
{
    global $OriginalUser;
    global $User;

    $usr = NULL;
    if (isset($OriginalUser) && is_array($OriginalUser))
	$usr = $OriginalUser;
    else if (isset($User) && is_array($User))
	$usr = $User;
    if ($usr == NULL)
	return (false);
    if (isset($usr["id"]) && (int)$usr["id"] == 1)
	return (true);
    if (!isset($usr["authority"]))
	return (false);
    if (defined("ADMINISTRATOR"))
	return ((int)$usr["authority"] >= ADMINISTRATOR);
    return ((int)$usr["authority"] >= 1);
}

function virtual_now_can_control()
{
    return (virtual_now_original_user_is_admin() && virtual_now_admin_mode_enabled());
}

function virtual_now_timestamp()
{
    if (!virtual_now_can_control())
	return (NULL);
    if (!isset($_COOKIE["virtual_now"]) || trim((string)$_COOKIE["virtual_now"]) == "")
	return (NULL);
    $value = trim((string)$_COOKIE["virtual_now"]);
    if (!is_number($value))
	$value = date_to_timestamp($value);
    $value = (int)$value;
    if ($value <= 0)
	return (NULL);
    return ($value);
}

function virtual_now_datetime_local_value()
{
    $tm = virtual_now_timestamp();

    if ($tm === NULL)
	$tm = now(true);
    return (datex("Y-m-d\TH:i", $tm));
}

function sql_reference_timestamp()
{
    return (virtual_now_timestamp());
}

function now($real = false)
{
    global $NoLocalisation;

    if (!$real && ($virtual = virtual_now_timestamp()) !== NULL)
	return ($virtual);
    $dt = new DateTime("now"); // Avec localisation
    return ($dt->getTimestamp() + $dt->getOffset());
}

function dbnow()
{
    return (db_form_date(now()));
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

function handle_french($body, $encode = true)
{
    if ($encode)
    {
	$body = str_replace("é", "&eacute;", $body);
	$body = str_replace("è", "&egrave;", $body);
	$body = str_replace("ê", "&ecirc;", $body);
	$body = str_replace("ë", "&euml;", $body);
	$body = str_replace("à", "&agrave;", $body);
	$body = str_replace("ù", "&ugrave;", $body);
	$body = str_replace("ç", "&ccedil;", $body);
	$body = str_replace("ï", "&iuml;", $body);
	$body = str_replace("î", "&icirc;", $body);
	return ($body);
    }
    $body = str_replace("é", "e", $body);
    $body = str_replace("è", "e", $body);
    $body = str_replace("ê", "e", $body);
    $body = str_replace("ë", "e", $body);
    $body = str_replace("à", "a", $body);
    $body = str_replace("ù", "u", $body);
    $body = str_replace("ç", "c", $body);
    $body = str_replace("ï", "i", $body);
    $body = str_replace("î", "i", $body);
    return ($body);
}

function xcount($value)
{
    return (is_countable($value) ? count($value) : 0);
}

/*
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
*/
