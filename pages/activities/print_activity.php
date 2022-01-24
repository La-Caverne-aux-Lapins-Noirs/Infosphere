<?php
// @codeCoverageIgnoreStart
function weekday_or_date($a, $b, $template)
{
    if ($template)
	return (weekday_date($a, $b));
    return (human_date($a));
}

function print_activity($content, $cnt, $left, $unroll_first = false, $always_unroll = false, $offset = NULL)
{
    global $Position;
    global $ActivityType;
    global $Dictionnary;
    global $User;
    global $date0;

    if ($offset == NULL)
	$offset = $date0;

    require ("print_activity.phtml");
    if ($unroll_first == false)
	return ;
    if ($always_unroll == false)
	$unroll_first = false;
    $left += 5;
    if (count($content->subactivities) != 0)
    {
	echo "<tr><td colspan='6'>".
	     "<table ".
	     "class=\"content_table\" ".
	     "style=\"margin-left: 5px; width: calc(100% - {$left}px);\"".
	     ">\n";
    }
    $subcnt = 0;
    foreach ($content->subactivities as $subcontent)
    {
	if (have_rights($subcontent->id))
	    print_activity($subcontent, $subcnt++, $left, $unroll_first, $always_unroll, $offset);
    }
    if (count($content->subactivities) != 0)
	echo "</table></td></tr>\n";
}
// @codeCoverageIgnoreEnd
