<?php

function famous_quote_html($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}

function famous_quote_nullable($value)
{
    $value = trim((string)$value);
    if ($value == "")
	return (NULL);
    return ($value);
}

function famous_quote_sql_value($value, $nullable = true)
{
    global $Database;

    if ($nullable && $value === NULL)
	return ("NULL");
    return ("'".$Database->real_escape_string((string)$value)."'");
}

function famous_quote_text($quote, $language = NULL)
{
    global $Language;

    if ($language === NULL)
	$language = $Language;
    if (isset($quote[$language]) && trim((string)$quote[$language]) != "")
	return ((string)$quote[$language]);
    if ($language != "fr" && isset($quote["fr"]) && trim((string)$quote["fr"]) != "")
	return ((string)$quote["fr"]);
    if ($language != "en" && isset($quote["en"]) && trim((string)$quote["en"]) != "")
	return ((string)$quote["en"]);
    return ("");
}

function famous_quote_format($quote, $language = NULL)
{
    $text = famous_quote_text($quote, $language);
    $author = trim((string)($quote["author"] ?? ""));

    if ($text == "")
	return ("");
    if ($author == "")
	return (famous_quote_html($text));
    return (
	"<span class=\"famous_quote_with_author\" title=\"".
	famous_quote_html($author).
	"\">".
	famous_quote_html($text).
	"</span>"
    );
}

function famous_quote_parse_last_quote($value)
{
    $value = explode(";", trim((string)$value), 2);
    if (count($value) != 2)
	return ([datex("Y-m-d"), 0]);
    if (!preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $value[0]))
	$value[0] = datex("Y-m-d");
    else
    {
	$date = explode("-", $value[0]);
	if (!checkdate((int)$date[1], (int)$date[2], (int)$date[0]))
	    $value[0] = datex("Y-m-d");
    }
    if (!is_number($value[1]))
	$value[1] = 0;
    return ([$value[0], (int)$value[1]]);
}

function famous_quote_daily_id()
{
    global $Configuration;
    global $QueryCacheOne;

    $query = "id, value FROM configuration WHERE codename = 'last_quote'";
    $today = datex("Y-m-d");
    if (($quote = db_select_one($query)) == NULL)
	return (0);
    list($last_date, $id) = famous_quote_parse_last_quote($quote["value"]);
    if (strcmp($last_date, $today) < 0)
    {
	$id += 1;
	$quote["value"] = $today.";".$id;
	db_update_one("configuration", $quote["id"], ["value" => $quote["value"]]);
	$QueryCacheOne[$query] = $quote;
	if (isset($Configuration))
	    $Configuration->Properties["last_quote"] = $quote["value"];
    }
    return ($id);
}

function famous_quote_active_rows()
{
    return (db_select_all(
	"q.id, q.fr, q.en, q.author, q.deleted, ".
	"COALESCE(SUM(uq.vote), 0) AS score, ".
	"COALESCE(SUM(CASE WHEN uq.vote != 0 THEN 1 ELSE 0 END), 0) AS vote_count, ".
	"COALESCE(SUM(CASE WHEN uq.vote > 0 THEN 1 ELSE 0 END), 0) AS up_count, ".
	"COALESCE(SUM(CASE WHEN uq.vote < 0 THEN 1 ELSE 0 END), 0) AS down_count ".
	"FROM `quote` AS q ".
	"LEFT JOIN user_quote AS uq ON uq.id_quote = q.id ".
	"WHERE q.deleted IS NULL ".
	"AND ((q.fr IS NOT NULL AND q.fr != '') OR (q.en IS NOT NULL AND q.en != '')) ".
	"GROUP BY q.id, q.fr, q.en, q.author, q.deleted ".
	"ORDER BY q.id ASC"
    ));
}

function famous_quote_current($id = -1)
{
    $quotes = famous_quote_active_rows();

    if (count($quotes) == 0)
	return (NULL);
    if ($id == -1)
	$id = famous_quote_daily_id();
    $id = (int)$id;
    if ($id < 0)
	$id = 0;
    return ($quotes[$id % count($quotes)]);
}

function famous_quote($id = -1)
{
    $quote = famous_quote_current($id);

    if ($quote == NULL)
	return ("");
    return (famous_quote_format($quote));
}

function famous_quote_order_clause($sort = "id", $direction = "asc")
{
    $orders = [
	"id" => "q.id",
	"score" => "score",
	"votes" => "vote_count",
	"author" => "q.author",
	"fr" => "q.fr",
	"en" => "q.en",
    ];

    if (!isset($orders[$sort]))
	$sort = "id";
    $direction = strtolower((string)$direction);
    if ($direction != "desc")
	$direction = "asc";
    return ($orders[$sort]." ".strtoupper($direction).", q.id ASC");
}

function famous_quote_sort_options()
{
    return ([
	"id" => "Identifiant",
	"score" => "Note",
	"votes" => "Nombre de votes",
	"author" => "Auteur/autrice",
	"fr" => "Citation FR",
	"en" => "Citation EN",
    ]);
}

function famous_quote_list($sort = "id", $direction = "asc")
{
    $order = famous_quote_order_clause($sort, $direction);

    return (db_select_all(
	"q.id, q.fr, q.en, q.author, q.deleted, ".
	"COALESCE(SUM(uq.vote), 0) AS score, ".
	"COALESCE(SUM(CASE WHEN uq.vote != 0 THEN 1 ELSE 0 END), 0) AS vote_count, ".
	"COALESCE(SUM(CASE WHEN uq.vote > 0 THEN 1 ELSE 0 END), 0) AS up_count, ".
	"COALESCE(SUM(CASE WHEN uq.vote < 0 THEN 1 ELSE 0 END), 0) AS down_count ".
	"FROM `quote` AS q ".
	"LEFT JOIN user_quote AS uq ON uq.id_quote = q.id ".
	"WHERE q.deleted IS NULL ".
	"GROUP BY q.id, q.fr, q.en, q.author, q.deleted ".
	"ORDER BY ".$order
    ));
}

function famous_quote_get($id)
{
    $id = (int)$id;
    if ($id <= 0)
	return (NULL);
    return (db_select_one("* FROM `quote` WHERE id = ".$id." AND deleted IS NULL"));
}

function famous_quote_save($data)
{
    global $Database;

    $id = (int)($data["id"] ?? 0);
    $fields = [
	"fr" => famous_quote_nullable($data["fr"] ?? ""),
	"en" => famous_quote_nullable($data["en"] ?? ""),
	"author" => trim((string)($data["author"] ?? "")),
    ];

    if ($fields["fr"] === NULL && $fields["en"] === NULL)
	return (["ok" => false, "msg" => "Citation vide."]);
    if ($id > 0)
    {
	db_update_one("quote", $id, $fields);
	return (["ok" => true, "msg" => "Citation modifiée.", "id" => $id]);
    }
    $query =
	"INSERT INTO `quote` (fr, en, author) VALUES (".
	famous_quote_sql_value($fields["fr"]).", ".
	famous_quote_sql_value($fields["en"]).", ".
	famous_quote_sql_value($fields["author"], false).
	")";
    if ($Database->query($query) == false)
	return (["ok" => false, "msg" => "Impossible d'ajouter la citation."]);
    return (["ok" => true, "msg" => "Citation ajoutée.", "id" => $Database->insert_id]);
}

function famous_quote_delete($id)
{
    $id = (int)$id;

    if ($id <= 0)
	return (["ok" => false, "msg" => "Citation invalide."]);
    db_update_one("quote", $id, ["deleted" => dbnow()]);
    return (["ok" => true, "msg" => "Citation retirée.", "id" => $id]);
}

function famous_quote_vote_summary($id_quote)
{
    $id_quote = (int)$id_quote;
    if ($id_quote <= 0)
	return (["score" => 0, "vote_count" => 0, "up_count" => 0, "down_count" => 0]);
    $row = db_select_one(
	"COALESCE(SUM(vote), 0) AS score, ".
	"COALESCE(SUM(CASE WHEN vote != 0 THEN 1 ELSE 0 END), 0) AS vote_count, ".
	"COALESCE(SUM(CASE WHEN vote > 0 THEN 1 ELSE 0 END), 0) AS up_count, ".
	"COALESCE(SUM(CASE WHEN vote < 0 THEN 1 ELSE 0 END), 0) AS down_count ".
	"FROM user_quote WHERE id_quote = ".$id_quote
    );
    if ($row == NULL)
	return (["score" => 0, "vote_count" => 0, "up_count" => 0, "down_count" => 0]);
    return ($row);
}

function famous_quote_get_user_vote($id_quote, $id_user = NULL)
{
    global $User;

    $id_quote = (int)$id_quote;
    if ($id_user === NULL && isset($User["id"]))
	$id_user = (int)$User["id"];
    $id_user = (int)$id_user;
    if ($id_quote <= 0 || $id_user <= 0)
	return (0);
    $vote = db_select_one(
	"vote FROM user_quote ".
	"WHERE id_user = ".$id_user." AND id_quote = ".$id_quote." ".
	"ORDER BY id DESC"
    );
    if ($vote == NULL)
	return (0);
    return ((int)$vote["vote"]);
}

function famous_quote_set_user_vote($id_quote, $vote, $id_user = NULL, $toggle = true)
{
    global $Database;
    global $User;

    $id_quote = (int)$id_quote;
    if ($id_user === NULL && isset($User["id"]))
	$id_user = (int)$User["id"];
    $id_user = (int)$id_user;
    $vote = (int)$vote;
    if ($vote < -1)
	$vote = -1;
    if ($vote > 1)
	$vote = 1;
    if ($id_quote <= 0 || $id_user <= 0)
	return (["ok" => false, "vote" => 0]);
    if (famous_quote_get($id_quote) == NULL)
	return (["ok" => false, "vote" => 0]);
    $existing = db_select_one(
	"id, vote FROM user_quote ".
	"WHERE id_user = ".$id_user." AND id_quote = ".$id_quote." ".
	"ORDER BY id DESC"
    );
    if ($existing != NULL)
    {
	if ($toggle && (int)$existing["vote"] == $vote)
	    $vote = 0;
	return ([
	    "ok" => db_update_one("user_quote", $existing["id"], ["vote" => $vote]),
	    "vote" => $vote
	]);
    }
    return ([
	"ok" => $Database->query(
	    "INSERT INTO user_quote (id_user, id_quote, vote) VALUES (".
	    $id_user.", ".$id_quote.", ".$vote.")"
	) != false,
	"vote" => $vote
    ]);
}

function famous_quote_user_vote($id_quote, $vote, $id_user = NULL)
{
    $result = famous_quote_set_user_vote($id_quote, $vote, $id_user);

    return ($result["ok"]);
}
