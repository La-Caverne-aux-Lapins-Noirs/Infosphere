<?php

function is_note($med)
{
    return (substr($med, 0, 5) == "token" && is_number(substr($med, 5)));
}
