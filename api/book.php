<?php

require_once ("tools/book_documents.php");

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

function AddBook($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    unset($data["action"]);
    if (count($data) != 4)
	forbidden();
    foreach (["name", "author", "edition"] as $f)
	$data[$f] = strip_tags($data[$f]);
    if (($ret = try_insert("book", $data["codename"], [
	"name" => $data["name"],
	"author" => $data["author"],
	"edition" => $data["edition"],
    ]))->is_error())
        return ($ret);
    //
    $ret = DisplayBooks(-1, [], "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Added"];
    return ($ret);
}

function QueueBookOverdueNotice($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $loan_id = (int)($data["loan_id"] ?? 0);
    if ((int)$id <= 0 || $loan_id <= 0)
        bad_request();
    $ret = book_overdue_notice_queue((int)$id, $loan_id);
    if ($ret->is_error())
        return ($ret);
    return (new ValueResponse([
        "msg" => $Dictionnary["DocumentQueuedForPrint"] ?? "Relance ajoutée aux documents à imprimer.",
        "task_id" => (int)($ret->value["task_id"] ?? 0),
    ]));
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
	if (!am_i_librarian() || @$data["command"] == "cancel")
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
	if (!am_i_librarian() || @$data["command"] == "cancel")
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
	if (!am_i_librarian())
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
	    "am_i_librarian",
	    "AddBook",
	],
        "overdue_notice" => [
            "am_i_librarian",
            "QueueBookOverdueNotice",
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
	    "am_i_librarian",
	    "DeleteBook",
	]
    ]
];


