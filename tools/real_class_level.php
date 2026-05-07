<?php

function real_class_level($subscribe_date, $class_level)
{
    // Niveaux qui évoluent automatiquement :
    // CM1 (1) -> Bac+8 (17)
    $first_progressive = 1;
    $last_progressive  = 17;

    // Niveaux fixes :
    // 0  = Autre
    // 18 = Reconversion
    // 19 = ?
    if ($class_level < $first_progressive || $class_level > $last_progressive)
        return $class_level;

    $subscribe_date = date_to_timestamp($subscribe_date);
    // Année scolaire de l'inscription
    $subscribe_year  = (int)date('Y', $subscribe_date);
    $subscribe_month = (int)date('n', $subscribe_date);
    $subscribe_school_year = ($subscribe_month >= 9)
        ? $subscribe_year
        : $subscribe_year - 1;

    // Année scolaire actuelle
    $now_year  = (int)date('Y');
    $now_month = (int)date('n');
    $current_school_year = ($now_month >= 9)
        ? $now_year
        : $now_year - 1;

    // Nombre de passages d'année scolaire depuis l'inscription
    $delta = $current_school_year - $subscribe_school_year;
    if ($delta <= 0)
        return $class_level;

    $real_level = $class_level + $delta;

    // On borne à Bac+8
    if ($real_level > $last_progressive)
        $real_level = $last_progressive;

    return $real_level;
}

