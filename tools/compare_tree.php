<?php

function _compare_tree($a, $b, $addr, $echo = false)
{
    if (!is_array($a))
    {
	if (!is_array($b))
	{
	    if ($a != $b)
	    {
		if ($echo)
		    echo "$addr: $a != $b\n";
		return (false);
	    }
	}
	if ($echo)
	    echo "$addr: $a != b[]\n";
	return (false);
    }
    foreach ($a as $k => $v)
    {
	if (!isset($b[$k]))
	{
	    if ($echo)
		echo "$addr: b[$k] does not exists\n";
	    return (false);
	}
	if (is_array($v))
	{
	    if (is_array($b[$k]))
		if (_compare_tree($v, $b[$k], $addr.".$k", $echo) == false)
		    return (false);
	}
	else if ($v != $b[$k])
	{
	    if ($echo)
	    {
		if (is_array($b[$k]))
		    echo "$addr: $v != b[$k][]\n";
		else
		    echo "$addr: $v != b[$k] (".$b[$k].")\n";
	    }
	    return (false);
	}
    }
    return (true);
}

function compare_tree($a, $b, $echo = false)
{
    return (_compare_tree($a, $b, "[]", $echo) && _compare_tree($b, $a, "[]", $echo));
}

