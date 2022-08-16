<?php

function unmark_as_deleted($table, $id, $codename_column = "codename", $fetch = false, $delete_dir = false)
{
    global $Database;

    if (!is_number($id))
	return (new ErrorResponse("AnIdIsRequired"));

    if ($codename_column != "")
    {
	$target = db_select_one("codename FROM $table WHERE id = $id");
	$dcodename = $codename = explode("_", $target["codename"]);
	array_shift($codename); // On vire "del_"
	array_pop($codename); // On vire le code à la fin
	$codename = implode($codename); // On recolle tout

	// On verifie que le nom de code n'a pas été pris depuis...
	if (!($test = resolve_codename($table, $codename, $codename_column))->is_error())
	    // Si ce n'est pas une erreur, c'est qu'il est déjà pris...
	    return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
	
	if ($Database->query("
          UPDATE $table SET deleted = NULL, codename = '$codename' WHERE id = $id
	") == false)
	  return (new ErrorResponse("CannotEdit"));

	if ($delete_dir)
	    system("mv ./dres/trash/$table/$dcodename/ ./dres/$table/$codename");
    }
    else
    {
	if ($Database->query("
          UPDATE $table SET deleted = NULL WHERE id = $id
	") == false)
	return (new ErrorResponse("CannotEdit"));
    }

    add_log(EDITING_OPERATION, "$table $id");
    if ($fetch)
	return (new ValueResponse(db_select_one("* FROM $table WHERE id = $id")));
    return (new ValueResponse($id));
}

