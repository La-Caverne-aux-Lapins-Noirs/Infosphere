<?php

function unrollurl($add = [], $js = false)
{
    return ("index.php?".unrollget($add, $js));
}

