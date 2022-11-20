<?php

function in_limit($val, $limit)
{
    if ($limit == -1 || $limit === NULL)
	return (true);
    return ($val < $limit);
}
