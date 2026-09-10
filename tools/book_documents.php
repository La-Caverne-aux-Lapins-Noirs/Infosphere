<?php

require_once (__DIR__."/build_document.php");
require_once (__DIR__."/document_context.php");
require_once (__DIR__."/document_print.php");

function book_overdue_loan($id_book, $id_book_user)
{
    $id_book = (int)$id_book;
    $id_book_user = (int)$id_book_user;
    if ($id_book <= 0 || $id_book_user <= 0)
        return (NULL);
    return (db_select_one("
        book_user.*,
        book.codename AS book_codename,
        book.name AS book_name,
        user.codename AS user_codename,
        user.first_name AS user_first_name,
        user.family_name AS user_family_name
        FROM book_user
        LEFT JOIN book ON book.id = book_user.id_book
        LEFT JOIN user ON user.id = book_user.id_user
        WHERE book_user.id = $id_book_user
          AND book_user.id_book = $id_book
          AND book_user.status = 2
          AND user.id IS NOT NULL
          AND user.deleted IS NULL
    "));
}

function book_overdue_loan_is_late(array $loan)
{
    $stamp = date_to_timestamp($loan["end_date"] ?? "");
    return ($stamp !== NULL && $stamp < now());
}

function book_overdue_notice_context(array $loan, $school_id)
{
    $student = document_context_person((int)$loan["id_user"]);
    $school = document_context_school((int)$school_id);
    $book_loan = document_context_book_loan((int)$loan["id"]);
    if (!is_array($student) || !is_array($school) || !is_array($book_loan))
        return (NULL);
    return (dabsic_pascalcase_array([
        "school" => $school,
        "student" => $student,
        "book_loan" => $book_loan,
        "generation" => [
            "date" => datex("d/m/Y"),
            "time" => datex("H:i"),
            "datetime" => datex("d/m/Y H:i"),
        ],
    ]));
}

function book_overdue_notice_build($id_book, $id_book_user)
{
    global $Configuration;
    global $Language;

    $loan = book_overdue_loan($id_book, $id_book_user);
    if (!is_array($loan) || !book_overdue_loan_is_late($loan))
        return (new ErrorResponse("InvalidParameter", "overdue loan"));
    $school_id = document_print_school_id_for_user((int)$loan["id_user"]);
    if ($school_id <= 0)
        return (new ErrorResponse("MissingParameter", "school"));
    $access_context = [
        "type" => "library",
        "owner_user_id" => (int)$loan["id_user"],
        "school_id" => $school_id,
        "book_user_id" => (int)$loan["id"],
    ];
    if (!document_print_current_user_can_manage_context($access_context))
        return (new ErrorResponse("PermissionDenied"));

    $context = book_overdue_notice_context($loan, $school_id);
    if (!is_array($context))
        return (new ErrorResponse("MissingParameter", "document context"));
    $model = __DIR__."/../res/docs/".$Language."/relance_retour_livre.dab";
    if (!is_file($model))
        $model = __DIR__."/../res/docs/fr/relance_retour_livre.dab";
    if (!is_file($model))
        return (new ErrorResponse("MissingFile", "relance_retour_livre.dab"));

    $user_dir = $Configuration->UsersDir($loan["user_codename"]);
    $directory = $user_dir."admin/library/";
    new_directory($directory."index.php");
    $context_file = $directory."overdue_".(int)$loan["id"]."_context.dab";
    if (($generated = generate_dabsic($context, $context_file))->is_error())
        return ($generated);
    $output = $directory.datex("Ymd_His")."_relance_retour_livre_".(int)$loan["id"].".pdf";
    $include_paths = document_builder_model_dirs($Language);
    $include_paths[] = dirname($model)."/";
    $include_paths[] = $user_dir;
    $include_paths[] = $user_dir."admin/";
    $include_paths[] = $directory;
    $school = fetch_school($school_id);
    if (is_array($school) && trim((string)($school["codename"] ?? "")) != "")
        $include_paths[] = $Configuration->SchoolsDir($school["codename"]);
    $ret = build_document_from_parts($output, [
        ["file" => $model],
        ["file" => $context_file],
    ], $include_paths);
    if ($ret->is_error())
        return ($ret);
    return (new ValueResponse([
        "output" => $output,
        "loan" => $loan,
        "school_id" => $school_id,
    ]));
}

function book_overdue_notice_queue($id_book, $id_book_user)
{
    $built = book_overdue_notice_build($id_book, $id_book_user);
    if ($built->is_error())
        return ($built);
    $loan = $built->value["loan"];
    $recipient = trim((string)($loan["user_first_name"] ?? "")." ".(string)($loan["user_family_name"] ?? ""));
    if ($recipient == "")
        $recipient = (string)($loan["user_codename"] ?? "");
    $book_label = trim((string)($loan["book_name"] ?? ""));
    if ($book_label == "")
        $book_label = (string)($loan["book_codename"] ?? "livre");
    $queued = document_print_queue_file(
        $built->value["output"],
        "Relance de retour — ".$book_label,
        [
            "type" => "library",
            "owner_user_id" => (int)$loan["id_user"],
            "school_id" => (int)$built->value["school_id"],
            "book_user_id" => (int)$loan["id"],
            "source_key" => "library-overdue:".(int)$loan["id"],
            "recipient_label" => $recipient,
        ]
    );
    if ($queued->is_error())
        return ($queued);
    return (new ValueResponse([
        "task_id" => (int)($queued->value["id"] ?? 0),
        "output" => $built->value["output"],
    ]));
}
