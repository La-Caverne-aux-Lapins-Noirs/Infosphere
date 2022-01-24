<?php

function is_note($med)
{
    return (substr($med, 0, 4) == "note" && is_number(substr($med, 4)));
}
