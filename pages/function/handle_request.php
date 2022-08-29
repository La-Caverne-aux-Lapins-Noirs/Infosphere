<?php

if ($_POST["action"] == "edit_function")
{
    if (($request = split_symbols($_POST["codename"]))->is_error())
	return ;
    foreach ($request->value as $val)
    {
	if (substr($val, 0, 1) == "-")
	{
	    if (($request = resolve_codename("function", $val))->is_error())
		return ;
	    mark_as_deleted("function", $request->value);
	}
	else
	{
	    if (($request = resolve_codename("function", $val))->is_error() == false)
	    {
		$request = new ErrorResponse("CodeNameAlreadyUsed", $val);
		return ;
	    }
	    if ($request->label != "BadCodeName")
		return ;
	    $Database->query("INSERT INTO function (codename) VALUES('$val')");
	    $request = new Response;
	}
    }
    $LogMsg = "FunctionEdited";
}
else if ($_POST["action"] == "edit_function_medal")
{
    $request = handle_links($_POST["function"], $_POST["medals"], "function", "medal");
    $LogMsg = "FunctionEdited";
}

