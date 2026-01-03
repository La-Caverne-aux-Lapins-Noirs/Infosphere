<?php

function DisplayBooks($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;
    global $User;

    if (($books = fetch_books($id))->is_error())
	return ($books);
    $books = $books->value;
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($books, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    if (count($books) == 0)
	echo $Dictionnary["NoBook"];
    else
	require ("./pages/$module/booktable.php");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function EditBook($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $User;

    $id = (int)$id;
    if (db_select_all("id FROM book WHERE id = $id") == NULL)
	return (new ErrorResponse("NotFound"));
    
    if (($cst = db_select_all("
	* FROM book_user WHERE id_book = {$id} AND id_user = {$User["id"]}
	ORDER BY request_date DESC LIMIT 1
	")) != NULL)
        $cst = $cst[0];
    // debug_response($data);
    if ($cst == NULL || $cst["status"] == -1 || $cst["status"] == 3)
    {
	// Je demande a emprunter
	$Database->query("
	    INSERT INTO book_user (id_book, id_user) VALUES ($id, {$User["id"]})
	    ");
    }
    else if ($cst["status"] == 0)
    {
	if (!is_librarian() || @$data["command"] == "cancel")
	    // J'annule ma demande
	    db_update_one("book_user", $cst["id"], [
		"status" => -1,
		"last_update" => dbnow(),
		"id_last_user" => $User["id"]
	    ]);
	else if (@$data["command"] == "accept")
	    // Je confirme que vous pouvez avoir le livre
	    db_update_one("book_user", $cst["id"], [
		"status" => 1,
		"last_update" => dbnow(),
		"id_last_user" => $User["id"]
	    ]);
    }
    else if ($cst["status"] == 1)
    {
	// Le livre est emporté
	if (!is_librarian() || @$data["command"] == "cancel")
	    // J'annule ma demande
	    db_update_one("book_user", $cst["id"], [
		"status" => -1,
		"last_update" => dbnow(),
		"id_last_user" => $User["id"]
	    ]);
	else
	    // Le livre est emporté
	    db_update_one("book_user", $cst["id"], [
		"status" => 2,
		"start_date" => dbnow(),
		"end_date" => db_form_date(now() + 3 * 7 * 24 * 60 * 60),
		"last_update" => dbnow(),
		"id_last_user" => $User["id"]
	    ]);
    }
    else if ($cst["status"] == 2)
    {
	if (!is_librarian())
	    // On ne peut pas déclarer soi meme avoir rendu
	    return (new ErrorResponse("PermissionDenied"));
	// Le bibliothécaire annonce avoir rendu
	db_update_one("book_user", $cst["id"], [
	    "status" => 3,
	    "last_update" => dbnow(),
	    "id_last_user" => $User["id"]
	]);
    }

    $ret = DisplayBooks(-1, [], "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Done"];
    return ($ret);
}


$Tab = [
    "GET" => [
	"" => [
	    "logged_in",
	    "DisplayBooks"
	]
    ],
    "POST" => [
	"" => [
	    "is_librarian",
	    "AddBook",
	]
    ],
    "PUT" => [
	"" => [
	    "logged_in",
	    "EditBook",
	]
    ],
    "DELETE" => [
	"" => [
	    "is_librarian",
	    "DeleteBook",
	]
    ]
];


