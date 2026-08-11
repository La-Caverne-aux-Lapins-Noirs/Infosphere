<?php
$Convention = false;
$PreservedSubscriptionUser = NULL;
$SubscriptionSubmission = isset($_POST["logaction"])
    && in_array($_POST["logaction"], ["conv_subscribe", "conv_external_relation"], true);
$SubscriptionSuccessMessage = "";

// Les formulaires de création de comptes non actifs sont utilisables depuis le
// back-office. Comme leur POST passe par try_login.php, on restaure d'abord la
// session existante au lieu de traiter le POST comme une tentative de connexion.
if ($SubscriptionSubmission
    && isset($_COOKIE["login"])
    && isset($_COOKIE["password"])
    && trim((string)$_COOKIE["login"]) != ""
    && trim((string)$_COOKIE["password"]) != "")
{
    $preserved = get_login_info($_COOKIE["login"], $_COOKIE["password"], false, false);
    if (!$preserved->is_error())
    {
        $PreservedSubscriptionUser = $preserved->value;
        $User = $PreservedSubscriptionUser;
    }
}

function subscription_post_missing(array $fields)
{
    $missing = [];
    foreach ($fields as $field)
        if (!array_key_exists($field, $_POST) || trim((string)$_POST[$field]) === "")
            $missing[] = $field;
    return ($missing);
}

function validate_prospect_subscription_post()
{
    $missing = subscription_post_missing([
        "first_name", "family_name", "mail", "postal_code",
        "current_class", "target_class", "target_entry", "school"
    ]);
    if (count($missing))
        return (new ErrorResponse("MissingField", implode(", ", $missing)));

    if (!preg_match('/^[0-9]{5}$/', (string)$_POST["postal_code"]))
        return (new ErrorResponse("InvalidParameter", "postal_code"));
    $current_class = (int)$_POST["current_class"];
    $target_class = (int)$_POST["target_class"];
    $target_entry = (int)$_POST["target_entry"];
    $school = (int)$_POST["school"];
    if ($current_class < -9 || $current_class > 9)
        return (new ErrorResponse("InvalidParameter", "current_class"));
    if ($target_class < 1 || $target_class > 7)
        return (new ErrorResponse("InvalidParameter", "target_class"));
    if ($target_entry < 0 || $target_entry > 2)
        return (new ErrorResponse("InvalidParameter", "target_entry"));
    if ($school <= 0 || db_select_one("id FROM school WHERE id = $school AND deleted IS NULL") == NULL)
        return (new ErrorResponse("InvalidParameter", "school"));
    return (new Response);
}

function validate_external_relation_subscription_post()
{
    global $User;

    $missing = subscription_post_missing(["first_name", "family_name", "mail", "relation_target"]);
    if (count($missing))
        return (new ErrorResponse("MissingField", implode(", ", $missing)));
    $target = (int)$_POST["relation_target"];
    if ($target <= 0 || db_select_one("id FROM user WHERE id = $target AND authority != -1") == NULL)
        return (new ErrorResponse("UserNotFound"));
    if (!$User || (!is_commercial() && !is_identity_authority_for_user($target)))
        return (new ErrorResponse("InvalidParameter", "relation_target"));
    if (!count(user_relation_values($_POST["relation"] ?? [])))
        return (new ErrorResponse("MissingField", "relation"));
    return (new Response);
}

// On cherche à modifier son status de connexion
if (isset($_POST["logaction"]))
{
    // On veut se connecter
    if ($_POST["logaction"] == "login")
    {
        $Msg = get_login_info($_POST["login"], $_POST["password"]);
        unset($_COOKIE["log_as"]);
    }
    // On veut se deconnecter
    else if ($_POST["logaction"] == "logout")
    {
        $Msg = new Response;
        set_cookie("login", "", time() - 1);
        set_cookie("password", "", time() - 1);
        set_cookie("log_as", "", time() - 1);
        unset($_COOKIE["log_as"]);
    }
    // On veut s'inscrire, inscrire un prospect ou créer un contact externe relié à un utilisateur.
    else if (in_array($_POST["logaction"], ["subscribe", "conv_subscribe", "conv_external_relation"], true))
    {
        $is_prospect = $_POST["logaction"] == "conv_subscribe";
        $is_external_relation = $_POST["logaction"] == "conv_external_relation";
        $fake = $is_prospect || $is_external_relation;

        if ($is_prospect)
        {
            $Msg = validate_prospect_subscription_post();
            if ($Msg->is_error())
            {
                $PreviousPosition = $Position;
                $Position = "Subscribe";
                goto TLEnd;
            }
        }
        else if ($is_external_relation)
        {
            $Msg = validate_external_relation_subscription_post();
            if ($Msg->is_error())
            {
                $PreviousPosition = $Position;
                $Position = "Subscribe";
                goto TLEnd;
            }
        }

        if (!isset($_POST["login"]))
        {
            if (!isset($_POST["first_name"]) || trim((string)$_POST["first_name"]) == ""
                || !isset($_POST["family_name"]) || trim((string)$_POST["family_name"]) == "")
            {
                $Msg = new ErrorResponse("MissingField", "first_name, family_name");
                $Position = "Subscribe";
                goto TLEnd;
            }
            $first_name = convert_to_codename($_POST["first_name"]);
            $family_name = convert_to_codename($_POST["family_name"]);
            $_POST["login"] = "$first_name.$family_name";
        }
        if (!isset($_POST["mail"]) || trim((string)$_POST["mail"]) == "")
        {
            $Msg = new ErrorResponse("MissingField", "mail");
            $Position = "Subscribe";
            goto TLEnd;
        }
        if (!isset($_POST["password"]))
            $_POST["password"] = $_POST["repeat_password"] = NULL;

        // On accepte de s'inscrire sur l'Infosphere.
        if (isset($_POST["accept_rules"]) || isset($_POST["accept_privacy"]))
        {
            if ($is_external_relation)
            {
                $Msg = create_external_user_relation((int)$_POST["relation_target"], $_POST);
                if (!$Msg->is_error())
                    $SubscriptionSuccessMessage = "Relation ajoutée";
            }
            else
            {
                $profile_status = $is_prospect ? "prospect" : NULL;
                $Msg = try_subscribe(
                    $_POST["login"], $_POST["mail"], $_POST["password"], $_POST["repeat_password"],
                    $fake, $profile_status
                );
                if (!$Msg->is_error())
                {
                    $_POST["id"] = $Msg->value["id"];
                    if ($is_prospect)
                    {
                        $edits = [];
                        foreach ([
                            "postal_code", "current_class", "target_class", "target_entry",
                            "first_name", "family_name", "phone",
                        ] as $field)
                            if (array_key_exists($field, $_POST))
                                $edits[$field] = $_POST[$field];

                        $edit_result = set_user_data($_POST["id"], $edits);
                        if ($edit_result->is_error())
                            $Msg = $edit_result;
                        else
                        {
                            $school_link = handle_linksf([
                                "left_value" => (int)$_POST["id"],
                                "right_value" => (int)$_POST["school"],
                                "left_field_name" => "user",
                                "right_field_name" => "school",
                                "properties" => [
                                    "authority" => user_school_student_authority_value(),
                                ],
                                "allow_duplicate" => true
                            ]);
                            if ($school_link->is_error())
                                $Msg = $school_link;
                            else
                                $SubscriptionSuccessMessage = "ProspectAdded";
                        }
                    }
                }
            }

            if ($Msg->is_error())
            {
                $PreviousPosition = $Position;
                $Position = "Subscribe";
                goto TLEnd;
            }
        }
        else
        {
            $Msg = new ErrorResponse("MissingField", "accept_rules or accept_privacy");
            $Position = "Subscribe";
            goto TLEnd;
        }

        // Une inscription normale ouvre la session du nouvel utilisateur. Les créations
        // de comptes non actifs, elles, ne doivent jamais modifier la session courante.
        if (!$fake)
        {
            set_cookie("login", "", time() - 1);
            set_cookie("password", "", time() - 1);
            set_cookie("log_as", "", time() - 1);
            unset($_COOKIE["log_as"]);
        }
        unset($_POST);
    }
}
// Peut-être qu'on est déjà connecté?
else if (isset($_COOKIE["login"]) && isset($_COOKIE["password"]))
    $Msg = get_login_info($_COOKIE["login"], $_COOKIE["password"], false);
// On est pas connecté
else
    $Msg = new ErrorResponse();
if ($Convention)
{
    header("Location: ".unrollurl());
    exit ;
}
TLEnd:
$User = NULL;
$ErrorMsg = "";
$LogMsg = "";

if ($SubscriptionSubmission)
{
    // Le résultat du formulaire ne devient pas l'utilisateur connecté : on conserve
    // la session qui existait avant l'envoi, y compris le futur traitement log_as.
    $User = $PreservedSubscriptionUser;
    if ($Msg->is_error())
        $ErrorMsg = strval($Msg);
    else if ($SubscriptionSuccessMessage != "")
        $LogMsg = isset($Dictionnary[$SubscriptionSuccessMessage])
            ? $SubscriptionSuccessMessage
            : new InfoResponse("Added", $SubscriptionSuccessMessage);
}
else if ($Msg->is_error())
    $ErrorMsg = strval($Msg);
else
    $User = $Msg->value;
