<?php

function hash_method($str)
{
    // - Ajouter un sel calculé d'après la machine host? (2018)
    // - Oups. (2024)
    
    // return (password_hash($str, PASSWORD_BCRYPT));
    return (hash("whirlpool", $str, false));
}
