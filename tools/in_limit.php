<?php

function in_limit($val, $limit)
{
    if ($limit == -1)
	return (true);
    return ($val < $limit);
}
