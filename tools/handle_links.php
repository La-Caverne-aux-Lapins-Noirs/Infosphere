<?php

function handle_links(
    $left,
    $right,
    $tleft,
    $tright,
    $strict = false,
    $table_name = "",
    $allow_duplicate = false,
    $fleft = "",
    $fright = "",
    $props = []
)
{
    if (!isset($left))
	return (new ErrorResponse("MissingId"));
    if (!isset($right))
	return (new ErrorResponse("MissingId"));

    if (is_number($left))
	$left = [$left];
    else if (!is_array($left))
    {
	if (($left = split_symbols($left))->is_error())
	    return ($left); // @codeCoverageIgnore
	$left = $left->value;
    }

    if (is_number($right))
	$right = [$right];
    else if (!is_array($right))
    {
	if (($right = split_symbols($right))->is_error())
	    return ($right);
	$right = $right->value;
    }

    if (($tmp = check_codename($tleft, $left))->is_error())
	return ($tmp);
    if (count($tmp->value) != 0)
	return (new ErrorResponse("BadCodeName", implode(", ", $tmp->value)));

    if (($tmp = check_codename($tright, $right))->is_error())
	return ($tmp);
    if (count($tmp->value) != 0)
	return (new ErrorResponse("BadCodeName", implode(", ", $tmp->value)));

    $val = [];
    foreach ($left as $l)
    {
	foreach ($right as $r)
	{
	    $neg = false;
	    if (is_int($l) && $l < 0)
	    {
		$neg = true;
		$l *= -1;
	    }
	    else if (is_string($l) && (substr($l, 0, 1) == "-"))
	    {
		$l = substr($l, 1);
		$neg = true;
	    }

	    if (is_int($r) && $r < 0)
	    {
		$neg = true;
		$r *= -1;
	    }
	    else if (is_string($r) && (substr($r, 0, 1) == "-"))
	    {
		$r = substr($r, 1);
		$neg = true;
	    }

	    if ($neg)
		$msg = remove_link($l, $r, $tleft, $tright, $strict, $table_name, $fleft, $fright);
	    else
		$msg = add_link($l, $r, $tleft, $tright, $strict, $props, $table_name, $allow_duplicate, $fleft, $fright);
	    if ($msg->is_error())
		return ($msg);
	}
    }
    return (new ValueResponse(NULL));
}

