<?php

global $OriginalUser;

$access = logged_in();

// Les comptes bannis ou supprimés peuvent rester consultables par un
// administrateur pour audit, mais ne doivent pas exposer leur profil aux
// autres utilisateurs.
if ($access && !is_admin() && isset($_GET["a"]))
{
    $target = resolve_codename("user", $_GET["a"], "codename", true);
    if (!$target->is_error())
    {
        $target = $target->value;
        if (($target["deleted"] ?? NULL) !== NULL
            || (int)($target["authority"] ?? 0) === BANISHED)
            $access = false;
    }
}
