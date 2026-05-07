<?php

function format_phone_number(string $phone): string
{
    $phone = trim($phone);

    // On enlève tout sauf chiffres et éventuel + initial
    $phone = preg_replace('/(?!^\+)[^\d]/', '', $phone);
    $phone = preg_replace('/[^\d+]/', '', $phone);

    if ($phone === '') {
        return '';
    }

    // Format local français : 0123456789
    if (preg_match('/^0\d{9}$/', $phone)) {
        return implode(' ', str_split($phone, 2));
    }

    // Format international avec +
    if (preg_match('/^\+(\d+)$/', $phone, $m)) {
        return format_international_number($m[1]);
    }

    // Format international avec 00
    if (preg_match('/^00(\d+)$/', $phone, $m)) {
        return format_international_number($m[1]);
    }

    // Si uniquement des chiffres mais pas au format FR standard,
    // on renvoie les chiffres tels quels
    return $phone;
}

function format_international_number(string $digits): string
{
    // Cas français : +33XXXXXXXXX => 0X XX XX XX XX
    if (preg_match('/^33(\d{9})$/', $digits, $m)) {
        return implode(' ', str_split('0' . $m[1], 2));
    }

    $len = strlen($digits);

    /*
     * On cherche l'indicatif le plus long possible :
     * 3 chiffres, puis 2, puis 1
     *
     * En imposant qu'il reste derrière un numéro "national"
     * d'au moins 6 chiffres et d'au plus 12 chiffres.
     *
     * Cela fait que :
     * 67123456789
     * -> 67 + 123456789
     * et non 671 + 23456789
     */
    foreach ([3, 2, 1] as $country_len) {
        if ($len <= $country_len) {
            continue;
        }

        $country = substr($digits, 0, $country_len);
        $national = substr($digits, $country_len);
        $national_len = strlen($national);

        if ($national_len >= 6 && $national_len <= 12) {
            /*
             * Petite règle supplémentaire :
             * on évite de prendre 3 chiffres d'indicatif
             * si 2 chiffres laisseraient aussi un numéro plausible,
             * afin d'obtenir +67 1 23 45 67 89 plutôt que +671 ...
             *
             * Autrement dit, on préfère 2 chiffres à 3 quand les deux marchent.
             */
            if ($country_len === 3) {
                $country2 = substr($digits, 0, 2);
                $national2 = substr($digits, 2);
                $national2_len = strlen($national2);

                if ($national2_len >= 6 && $national2_len <= 12) {
                    return '+' . $country2 . ' ' . format_national_without_leading_zero($national2);
                }
            }

            return '+' . $country . ' ' . format_national_without_leading_zero($national);
        }
    }

    return '+' . $digits;
}

function format_national_without_leading_zero(string $digits): string
{
    if ($digits === '') {
        return '';
    }

    $parts = [substr($digits, 0, 1)];

    $rest = substr($digits, 1);
    if ($rest !== '') {
        $parts = array_merge($parts, str_split($rest, 2));
    }

    return implode(' ', $parts);
}
