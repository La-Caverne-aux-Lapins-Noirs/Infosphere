<?php

// SEULEMENT POUR AFFICHER LES MEDAILLES BAREMES.

function print_medal($medal, $module = NULL, $user = NULL, $size = 75, $medal_teacher = false)
{
    global $Dictionnary;

    require (__DIR__."/template/print_medal.phtml");
}

