<?php

function class_level_school_year_delta($subscribe_date)
{
    $subscribe_date = date_to_timestamp($subscribe_date);
    if ($subscribe_date == NULL)
        return (0);

    $subscribe_year = (int)date('Y', $subscribe_date);
    $subscribe_month = (int)date('n', $subscribe_date);
    $subscribe_school_year = ($subscribe_month >= 9)
        ? $subscribe_year
        : $subscribe_year - 1;

    $now_year = (int)date('Y');
    $now_month = (int)date('n');
    $current_school_year = ($now_month >= 9)
        ? $now_year
        : $now_year - 1;

    return (max(0, $current_school_year - $subscribe_school_year));
}

function class_level_is_progressive($class_level)
{
    // Valeurs réellement stockées par le formulaire prospect :
    // -8 = CM1 ... 0 = Terminale ... 8 = Bac+8.
    // -9 = Autre, 9 = Reconversion et 10 = ? restent fixes.
    return ($class_level >= -8 && $class_level <= 8);
}

function real_class_level($subscribe_date, $class_level)
{
    $class_level = (int)$class_level;
    if (!class_level_is_progressive($class_level))
        return ($class_level);

    return (min(8, $class_level + class_level_school_year_delta($subscribe_date)));
}

function stored_class_level_for_current($subscribe_date, $current_class_level)
{
    $current_class_level = (int)$current_class_level;
    if (!class_level_is_progressive($current_class_level))
        return ($current_class_level);

    // current_class représente historiquement le niveau au moment de la prise
    // de contact. Lors d'une correction depuis la page prospect, on saisit au
    // contraire le niveau actuel : on reconstruit donc la valeur historique
    // qui donnera exactement ce niveau aujourd'hui.
    return (max(-8, $current_class_level - class_level_school_year_delta($subscribe_date)));
}
