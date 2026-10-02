<?php

/*
** Public invitation primitives
** ----------------------------
** Document/user forms and questionnaires have different persistence and
** workflows, but their public links share the same security primitives.
** Keep those primitives here instead of making either workflow depend on the
** other or introducing a polymorphic invitation table.
*/

function public_invitation_generate_token()
{
    return (bin2hex(random_bytes(32)));
}

function public_invitation_token_is_valid($token)
{
    return (is_string($token) && preg_match('/^[a-f0-9]{64}$/iD', $token) === 1);
}

function public_invitation_token_hash($token)
{
    return (hash("sha256", (string)$token));
}

function public_invitation_request_base_url()
{
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (isset($_SERVER["HTTP_X_FORWARDED_PROTO"])
            && strtolower((string)$_SERVER["HTTP_X_FORWARDED_PROTO"]) === "https");
    $host = trim((string)($_SERVER["HTTP_HOST"] ?? ""));
    if ($host === "")
        return ("");
    return (($https ? "https" : "http")."://".$host);
}

function public_invitation_url($page, $token, array $extra_query = [], $base_url = NULL)
{
    $query = array_merge(["p" => (string)$page, "token" => (string)$token], $extra_query);
    $base = $base_url === NULL
        ? public_invitation_request_base_url()
        : rtrim(trim((string)$base_url), "/");
    return ($base."/index.php?".http_build_query($query, "", "&", PHP_QUERY_RFC3986));
}

function public_invitation_expiration_days($days, $default = 14, $maximum = 365)
{
    $default = max(1, (int)$default);
    $maximum = max($default, (int)$maximum);
    if ($days === NULL || $days === "")
        $days = $default;
    return (max(1, min($maximum, (int)$days)));
}

function public_invitation_is_revoked($revoked_at)
{
    return ($revoked_at !== NULL && trim((string)$revoked_at) !== "");
}

function public_invitation_is_expired($expires_at)
{
    if ($expires_at === NULL || trim((string)$expires_at) === "")
        return (false);
    $timestamp = strtotime((string)$expires_at);
    return ($timestamp === false || $timestamp < time());
}
