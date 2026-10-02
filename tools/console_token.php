<?php

function console_token_hash($token)
{
    return (hash("sha256", (string)$token));
}

function console_token_random()
{
    return ("isp_".rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "="));
}

function console_token_authorization_header()
{
    foreach (["HTTP_AUTHORIZATION", "REDIRECT_HTTP_AUTHORIZATION"] as $key)
        if (isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) != "")
            return (trim((string)$_SERVER[$key]));

    if (function_exists("apache_request_headers"))
    {
        $headers = apache_request_headers();
        foreach ($headers as $name => $value)
            if (strcasecmp((string)$name, "Authorization") == 0)
                return (trim((string)$value));
    }
    return ("");
}

function console_token_from_request()
{
    $header = console_token_authorization_header();
    if ($header == "")
        return (NULL);
    if (stripos($header, "Bearer") !== 0)
        return (NULL);
    if (!preg_match('/^Bearer\s+([^\s]+)$/iD', $header, $matches))
        return (false);
    return ($matches[1]);
}

function console_token_user($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (NULL);

    $rows = implode(", ", db_select_rows("user", ["full_profile"]));
    $usr = db_select_one("$rows FROM user WHERE id = $id_user");
    if ($usr == NULL || ($usr["deleted"] ?? NULL) != NULL)
        return (NULL);

    unset($usr["password"], $usr["salt"], $usr["local_salt"]);
    $usr["admin_mode"] = false;
    get_user_public_data($usr);
    return ($usr);
}

function console_token_authenticate_request()
{
    global $Database;
    global $ConsoleToken;

    $token = console_token_from_request();
    if ($token === NULL)
        return (NULL);
    if ($token === false || strlen($token) < 16 || strlen($token) > 255)
        return (new ErrorResponse("InvalidConsoleToken"));

    $hash = $Database->real_escape_string(console_token_hash($token));
    $row = db_select_one("
        user_console_token.*
        FROM user_console_token
        LEFT JOIN user ON user.id = user_console_token.id_user
        WHERE user_console_token.token_hash = '$hash'
          AND user_console_token.revoked_at IS NULL
          AND (user_console_token.expires_at IS NULL OR user_console_token.expires_at > CURRENT_TIMESTAMP)
          AND user.id IS NOT NULL
          AND user.deleted IS NULL
    ");
    if ($row == NULL)
        return (new ErrorResponse("InvalidConsoleToken"));

    $usr = console_token_user((int)$row["id_user"]);
    if ($usr == NULL)
        return (new ErrorResponse("InvalidConsoleToken"));

    $ConsoleToken = $row;
    $Database->query("UPDATE user_console_token SET last_used_at = CURRENT_TIMESTAMP WHERE id = ".(int)$row["id"]);
    return (new ValueResponse($usr));
}

function console_token_authenticated()
{
    global $ConsoleToken;
    return (isset($ConsoleToken) && is_array($ConsoleToken));
}

function console_token_scope_allows($scope)
{
    global $ConsoleToken;

    if (!console_token_authenticated())
        return (true);
    $granted = array_filter(array_map("trim", explode(",", (string)($ConsoleToken["scope"] ?? ""))));
    return (in_array("*", $granted, true) || in_array((string)$scope, $granted, true));
}

function console_token_require_scope($scope)
{
    if (!console_token_scope_allows($scope))
        forbidden();
}

function console_token_create($id_user, $name = "Terminal", $scope = "console.read")
{
    global $Database;

    $id_user = (int)$id_user;
    $name = trim((string)$name);
    if ($id_user <= 0 || $name == "" || strlen($name) > 80)
        return (new ErrorResponse("InvalidParameter"));
    if ($scope !== "console.read")
        return (new ErrorResponse("InvalidParameter", "scope"));

    $token = console_token_random();
    $hash = $Database->real_escape_string(console_token_hash($token));
    $escaped_name = $Database->real_escape_string($name);
    $escaped_scope = $Database->real_escape_string($scope);

    if (!$Database->query("
        INSERT INTO user_console_token (id_user, name, token_hash, scope)
        VALUES ($id_user, '$escaped_name', '$hash', '$escaped_scope')
    "))
        return (new ErrorResponse("CannotCreateConsoleToken"));

    return (new ValueResponse([
        "id" => (int)$Database->insert_id,
        "name" => $name,
        "scope" => $scope,
        "token" => $token,
    ]));
}

function console_token_list($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ([]);
    return (db_select_all("
        id, name, scope, created_at, last_used_at, expires_at
        FROM user_console_token
        WHERE id_user = $id_user
          AND revoked_at IS NULL
        ORDER BY created_at DESC, id DESC
    "));
}

function console_token_revoke($id_token, $id_user)
{
    global $Database;

    $id_token = (int)$id_token;
    $id_user = (int)$id_user;
    if ($id_token <= 0 || $id_user <= 0)
        return (new ErrorResponse("InvalidParameter"));

    if (!$Database->query("
        UPDATE user_console_token
        SET revoked_at = CURRENT_TIMESTAMP
        WHERE id = $id_token
          AND id_user = $id_user
          AND revoked_at IS NULL
    "))
        return (new ErrorResponse("CannotRevokeConsoleToken"));
    if ($Database->affected_rows <= 0)
        return (new ErrorResponse("ConsoleTokenNotFound"));
    return (new Response);
}
