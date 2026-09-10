<?php

function user_identity_administrative_data($user)
{
    if (!is_array($user))
        return ([]);
    $raw = $user["administrative_data"] ?? "{}";
    if (is_array($raw))
        return ($raw);
    if (!is_string($raw) || trim($raw) == "")
        return ([]);
    $data = json_decode($raw, true);
    return (is_array($data) ? $data : []);
}

function user_identity_path_parts($path)
{
    $path = trim((string)$path, " .\t\r\n");
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $path))
        return ([]);
    return (explode(".", $path));
}

function user_identity_nested_get($tree, $path, &$found = NULL)
{
    $found = false;
    $parts = is_array($path) ? $path : user_identity_path_parts($path);
    if (!count($parts))
        return (NULL);
    $node = $tree;
    foreach ($parts as $part)
    {
        if (!is_array($node) || !array_key_exists($part, $node))
            return (NULL);
        $node = $node[$part];
    }
    $found = true;
    return ($node);
}

function user_identity_nested_set(&$tree, $path, $value)
{
    $parts = is_array($path) ? $path : user_identity_path_parts($path);
    if (!count($parts))
        return (false);
    $node =& $tree;
    foreach ($parts as $index => $part)
    {
        if ($index == count($parts) - 1)
        {
            $node[$part] = $value;
            return (true);
        }
        if (!isset($node[$part]) || !is_array($node[$part]))
            $node[$part] = [];
        $node =& $node[$part];
    }
    return (false);
}

function user_identity_merge_tree(array $base, array $extra)
{
    foreach ($extra as $key => $value)
    {
        if (isset($base[$key]) && is_array($base[$key]) && is_array($value))
            $base[$key] = user_identity_merge_tree($base[$key], $value);
        else
            $base[$key] = $value;
    }
    return ($base);
}

function user_identity_dabsic_key($key)
{
    return (strtolower(str_replace(["_", "-", " "], "", (string)$key)));
}

function user_identity_merge_dabsic_tree(array $base, array $extra)
{
    foreach ($extra as $key => $value)
    {
        $target = NULL;
        $canonical = user_identity_dabsic_key($key);
        foreach (array_keys($base) as $candidate)
            if (user_identity_dabsic_key($candidate) === $canonical)
            {
                $target = $candidate;
                break ;
            }
        if ($target === NULL)
            $target = $key;
        if (isset($base[$target]) && is_array($base[$target]) && is_array($value))
            $base[$target] = user_identity_merge_dabsic_tree($base[$target], $value);
        else
            $base[$target] = $value;
    }
    return ($base);
}


function user_identity_fill_missing_values(array $base, array $known)
{
    foreach ($known as $key => $value)
    {
        if (is_array($value))
        {
            $current = isset($base[$key]) && is_array($base[$key]) ? $base[$key] : [];
            $base[$key] = user_identity_fill_missing_values($current, $value);
        }
        else if (!array_key_exists($key, $base) || $base[$key] === "" || $base[$key] === NULL)
            $base[$key] = $value;
    }
    return ($base);
}

function user_identity_complete_signatory_context(array $context)
{
    $signatories = $context["Signatories"] ?? [];
    $student = $signatories["Student"] ?? [];
    $legal1 = $context["Legal1"] ?? [];
    $legal2 = $context["Legal2"] ?? [];
    $finance = $signatories["Finance"] ?? [];
    $finance_is = $finance["Is"] ?? "";
    $finance_source = [];

    if ($finance_is == "Student")
        $finance_source = $student;
    else if ($finance_is == "Legal1")
        $finance_source = $legal1;
    else if ($finance_is == "Legal2")
        $finance_source = $legal2;
    if (count($finance_source))
        $finance = user_identity_fill_missing_values($finance, $finance_source);
    if (count($finance))
        $context["Signatories"]["Finance"] = $finance;

    $emergency = $context["Emergency"] ?? [];
    $emergency_is = $emergency["Is"] ?? "";
    $emergency_source = [];
    if ($emergency_is == "Legal1")
        $emergency_source = $legal1;
    else if ($emergency_is == "Legal2")
        $emergency_source = $legal2;
    else if ($emergency_is == "Finance")
        $emergency_source = $finance;
    if (count($emergency_source))
        $emergency = user_identity_fill_missing_values($emergency, $emergency_source);
    if (count($emergency))
        $context["Emergency"] = $emergency;
    return ($context);
}

function user_identity_core_fields()
{
    return ([
        "FirstName" => "first_name",
        "UseName" => "use_name",
        "FamilyName" => "family_name",
        "Gender" => "gender",
        "Mail" => "mail",
        "Phone" => "phone",
        "Address" => "street_name",
        "PostalCode" => "postal_code",
        "City" => "city",
        "BirthDate" => "birth_date",
        "Nationality" => "nationality",
    ]);
}

function user_identity_boolean_field($path)
{
    $parts = user_identity_path_parts($path);
    $leaf = count($parts) ? strtolower(end($parts)) : "";
    return (in_array($leaf, [
        "handicap", "lastclasssuccess", "resubscribe",
        "sendschoolreport", "intranetaccess"
    ], true));
}

function user_identity_normalize_answer($path, $value)
{
    if (is_array($value) || is_object($value))
        return (NULL);
    $value = trim((string)$value);
    if (user_identity_boolean_field($path))
        return (in_array(strtolower($value), ["1", "true", "yes", "oui", "on"], true));
    return ($value);
}

function user_identity_student_path($path)
{
    $prefix = "Signatories.Student.";
    if (strncmp($path, $prefix, strlen($prefix)) !== 0)
        return (NULL);
    return (substr($path, strlen($prefix)));
}

function user_identity_update_registration_answers($id_user, array $answers)
{
    $id_user = (int)$id_user;
    $user = db_select_one("* FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL)
        return (new ErrorResponse("UserNotFound"));

    $administrative = user_identity_administrative_data($user);
    $document_context = isset($administrative["DocumentContext"]) && is_array($administrative["DocumentContext"])
        ? $administrative["DocumentContext"] : [];
    $answered = isset($administrative["RegistrationAnswered"]) && is_array($administrative["RegistrationAnswered"])
        ? $administrative["RegistrationAnswered"] : [];
    $core = user_identity_core_fields();
    $updates = [];

    foreach ($answers as $path => $raw_value)
    {
        $path = dabsic_form_normalize_field($path);
        if ($path === NULL)
            return (new ErrorResponse("InvalidParameter", "field"));
        $value = user_identity_normalize_answer($path, $raw_value);
        if ($value === NULL)
            return (new ErrorResponse("InvalidParameter", $path));

        $student_path = user_identity_student_path($path);
        if ($student_path !== NULL)
        {
            $parts = user_identity_path_parts($student_path);
            if (count($parts) == 1 && isset($core[$parts[0]]))
            {
                $column = $core[$parts[0]];
                if ($column == "birth_date")
                    $updates[$column] = $value == "" ? NULL : db_form_date($value);
                else
                    $updates[$column] = $value;
            }
            else
                user_identity_nested_set($administrative, $parts, $value);
        }
        else
            user_identity_nested_set($document_context, user_identity_path_parts($path), $value);
        $answered[$path] = true;
    }

    $administrative["DocumentContext"] = $document_context;
    $administrative["RegistrationAnswered"] = $answered;
    $json = json_encode($administrative, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false)
        return (new ErrorResponse("CannotEdit"));
    $updates["administrative_data"] = $json;

    if (isset($updates["mail"]) && $updates["mail"] != "")
    {
        global $Database;
        $mail = $Database->real_escape_string($updates["mail"]);
        if (db_select_one("id FROM user WHERE mail = '$mail' AND id != $id_user AND authority != -1"))
            return (new ErrorResponse("MailUsed"));
    }
    return (set_user_data($id_user, $updates));
}

function user_identity_relation_administrative_fields()
{
    // administrative_data complète les colonnes de user : ne pas recopier ici
    // l'identité, les coordonnées, l'adresse, la naissance ou la nationalité.
    // Ces champs restent demandés par le formulaire public mais sont enregistrés
    // directement dans user.
    return ([
        "BirthCity" => "text",
        "BirthCountry" => "text",
        "SendSchoolReport" => "boolean",
        "IntranetAccess" => "boolean",
    ]);
}

function user_identity_contract_administrative_fields($user = NULL)
{
    if (is_object($user))
        $user = (array)$user;
    if (is_array($user)
        && isset($user["id"])
        && function_exists("user_relation_is_administrative_contact")
        && user_relation_is_administrative_contact((int)$user["id"]))
        return (user_identity_relation_administrative_fields());

    return ([
        "BirthCity" => "text",
        "BirthCountry" => "text",
        "INE" => "text",
        "NIR" => "text",
        "Handicap" => "boolean",
        "HandicapKind" => "text",
        "LastClass" => "text",
        "LastClassSuccess" => "boolean",
    ]);
}

function user_identity_contract_administrative_values(array $user)
{
    $definitions = user_identity_contract_administrative_fields($user);
    $administrative = user_identity_administrative_data($user);
    $fields = refresh_user_fields($user);
    $core = user_identity_core_fields();
    $values = [];

    foreach ($definitions as $field => $type)
    {
        if (isset($core[$field]))
        {
            $column = $core[$field];
            $value = $fields[$column] ?? ($user[$column] ?? "");
            if ($column == "birth_date" && $value)
                $value = db_form_date($value);
            $values[$field] = $value;
        }
        else
            $values[$field] = $administrative[$field] ?? ($type == "boolean" ? false : "");
    }
    return ($values);
}

function user_identity_update_contract_administrative_fields($id_user, array $values)
{
    $id_user = (int)$id_user;
    $user = db_select_one("* FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL)
        return (new ErrorResponse("UserNotFound"));

    $definitions = user_identity_contract_administrative_fields($user);
    foreach (array_keys($values) as $field)
        if (!isset($definitions[$field]))
            return (new ErrorResponse("InvalidParameter", $field));

    $administrative = user_identity_administrative_data($user);
    $core = user_identity_core_fields();
    $updates = [];
    foreach ($definitions as $field => $type)
    {
        if (!array_key_exists($field, $values))
            continue ;
        $value = trim((string)$values[$field]);
        if (isset($core[$field]))
        {
            $column = $core[$field];
            if ($column == "birth_date")
                $updates[$column] = $value == "" ? NULL : db_form_date($value);
            else
                $updates[$column] = $value;
            continue ;
        }
        if ($type == "boolean")
            $administrative[$field] = in_array(strtolower($value), ["1", "true", "yes", "oui", "on"], true);
        else if ($value == "")
            unset($administrative[$field]);
        else
            $administrative[$field] = $value;
    }
    $json = json_encode($administrative, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false)
        return (new ErrorResponse("CannotEdit"));
    $updates["administrative_data"] = $json;
    if (isset($updates["mail"]) && $updates["mail"] != "")
    {
        global $Database;
        $mail = $Database->real_escape_string($updates["mail"]);
        if (db_select_one("id FROM user WHERE mail = '$mail' AND id != $id_user AND authority != -1"))
            return (new ErrorResponse("MailUsed"));
    }
    return (set_user_data($id_user, $updates));
}

function user_identity_student_administrative_fields(array $user)
{
    $data = user_identity_administrative_data($user);
    foreach (["DocumentContext", "RegistrationAnswered"] as $meta)
        unset($data[$meta]);
    return ($data);
}

function user_identity_document_context(array $user)
{
    $data = user_identity_administrative_data($user);
    return (isset($data["DocumentContext"]) && is_array($data["DocumentContext"])
        ? $data["DocumentContext"] : []);
}

function user_identity_answered_fields(array $user)
{
    $data = user_identity_administrative_data($user);
    return (isset($data["RegistrationAnswered"]) && is_array($data["RegistrationAnswered"])
        ? $data["RegistrationAnswered"] : []);
}

function user_identity_write_identity_dabsic($id_user)
{
    global $Configuration;

    $id_user = (int)$id_user;
    $ret = resolve_codename("user", $id_user, "codename", true);
    if ($ret->is_error())
        return ($ret);
    $user = $ret->value;
    $fields = refresh_user_fields($user);
    $fields = user_identity_merge_dabsic_tree($fields, user_identity_student_administrative_fields($user));

    // identity.dab is the portable identity of the person. Document contexts
    // may inject it under any semantic scope (Student, Legal1, Signatories.*...).
    // Keep identity facts here; the role in a given document stays contextual.
    $identity = trim((string)($fields["first_name"] ?? "")." ".(string)($fields["family_name"] ?? ""));
    if ($identity == "")
        $identity = $user["codename"] ?? "";
    $fields["id"] = (int)$id_user;
    $fields["codename"] = $user["codename"] ?? "";
    $fields["identity"] = $identity;
    $fields["name"] = $identity;
    $fields["street"] = $fields["address"] ?? "";
    $fields["postal_city"] = trim((string)($fields["postal_code"] ?? "")." ".(string)($fields["city"] ?? ""));
    $signature = user_identity_document_signature_file($user);
    $fields["signature"] = ($signature != "" && is_file($signature)) ? $signature : "";

    $file = $Configuration->UsersDir($user["codename"])."admin/identity.dab";
    return (generate_dabsic($fields, $file));
}

function user_identity_signature_file(array $user)
{
    global $Configuration;

    if (!isset($user["codename"]) || $user["codename"] == "")
        return ("");
    return ($Configuration->UsersDir($user["codename"])."admin/signature.png");
}

/**
 * Store a handwritten signature as a compact black/transparent PNG.
 *
 * The browser canvas is deliberately comfortable to sign in, therefore the
 * uploaded bitmap usually contains a lot of empty area.  Keeping that canvas
 * geometry in the document makes the handwriting look tiny.  We detect the
 * actual ink (alpha + luminance), crop it with a small safety margin and turn
 * it into black ink on transparency.  White backgrounds are therefore also
 * removed when a browser/device flattened the canvas before upload.
 */
function user_identity_store_signature_png($source, $target, $require_uploaded_file = true)
{
    if (($require_uploaded_file && !is_uploaded_file($source)) || !is_file($source) || filesize($source) > 4 * 1024 * 1024)
        return (["ok" => false, "error" => "RegistrationFormInvalidSignature"]);
    $info = @getimagesize($source);
    if (!$info || $info[2] != IMAGETYPE_PNG || $info[0] < 20 || $info[1] < 20 || $info[0] > 2000 || $info[1] > 1000)
        return (["ok" => false, "error" => "RegistrationFormInvalidSignature"]);
    if (!function_exists("imagecreatefrompng") || !function_exists("imagepng"))
        return (["ok" => false, "error" => "RegistrationFormInvalidSignature"]);

    $input = @imagecreatefrompng($source);
    if ($input === false)
        return (["ok" => false, "error" => "RegistrationFormInvalidSignature"]);
    if (!imageistruecolor($input) && function_exists("imagepalettetotruecolor"))
        @imagepalettetotruecolor($input);
    $truecolor = imageistruecolor($input);

    $width = imagesx($input);
    $height = imagesy($input);
    $min_x = $width;
    $min_y = $height;
    $max_x = -1;
    $max_y = -1;
    $visible_pixels = 0;

    // "Ink" is opacity multiplied by darkness.  It detects an ordinary
    // transparent canvas as well as a PNG whose empty background became white.
    // A deliberately low threshold keeps anti-aliased edges in the crop.
    for ($y = 0; $y < $height; ++$y)
        for ($x = 0; $x < $width; ++$x)
        {
            $pixel = imagecolorat($input, $x, $y);
            if ($truecolor)
            {
                $alpha = ($pixel >> 24) & 0x7F;
                $red = ($pixel >> 16) & 0xFF;
                $green = ($pixel >> 8) & 0xFF;
                $blue = $pixel & 0xFF;
            }
            else
            {
                $rgba = imagecolorsforindex($input, $pixel);
                $alpha = (int)($rgba["alpha"] ?? 127);
                $red = (int)($rgba["red"] ?? 255);
                $green = (int)($rgba["green"] ?? 255);
                $blue = (int)($rgba["blue"] ?? 255);
            }
            $luminance = (299 * $red + 587 * $green + 114 * $blue) / 1000;
            $ink = (127 - $alpha) * (255 - $luminance);
            if ($ink < 1024)
                continue ;
            $min_x = min($min_x, $x);
            $min_y = min($min_y, $y);
            $max_x = max($max_x, $x);
            $max_y = max($max_y, $y);
            if ($ink >= 4096)
                ++$visible_pixels;
        }

    if ($max_x < $min_x || $max_y < $min_y || $visible_pixels < 8)
    {
        imagedestroy($input);
        return (["ok" => false, "error" => "RegistrationFormInvalidSignature"]);
    }

    $ink_width = $max_x - $min_x + 1;
    $ink_height = $max_y - $min_y + 1;
    $padding = max(3, (int)ceil(min($ink_width, $ink_height) * 0.04));

    // When normalizing the canonical file in place, avoid rewriting it on
    // every document generation once it is already tightly cropped and has a
    // transparent background.
    $source_real = @realpath($source);
    $target_real = @realpath($target);
    if (!$require_uploaded_file && $source_real !== false && $source_real === $target_real)
    {
        $margins = [$min_x, $min_y, $width - 1 - $max_x, $height - 1 - $max_y];
        $transparent_corners = true;
        foreach ([[0, 0], [$width - 1, 0], [0, $height - 1], [$width - 1, $height - 1]] as $corner)
        {
            $pixel = imagecolorat($input, $corner[0], $corner[1]);
            if ($truecolor)
                $alpha = ($pixel >> 24) & 0x7F;
            else
            {
                $rgba = imagecolorsforindex($input, $pixel);
                $alpha = (int)($rgba["alpha"] ?? 0);
            }
            if ($alpha < 96)
            {
                $transparent_corners = false;
                break ;
            }
        }
        if ($transparent_corners && max($margins) <= $padding + 2)
        {
            imagedestroy($input);
            return ([
                "ok" => true,
                "hash" => hash_file("sha256", $source),
                "width" => $width,
                "height" => $height,
                "unchanged" => true,
            ]);
        }
    }

    $min_x = max(0, $min_x - $padding);
    $min_y = max(0, $min_y - $padding);
    $max_x = min($width - 1, $max_x + $padding);
    $max_y = min($height - 1, $max_y + $padding);
    $crop_width = $max_x - $min_x + 1;
    $crop_height = $max_y - $min_y + 1;

    $output = imagecreatetruecolor($crop_width, $crop_height);
    if ($output === false)
    {
        imagedestroy($input);
        return (["ok" => false, "error" => "CannotWritePngFile"]);
    }
    imagealphablending($output, false);
    imagesavealpha($output, true);
    $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
    imagefill($output, 0, 0, $transparent);
    $colors = [127 => $transparent];

    for ($out_y = 0; $out_y < $crop_height; ++$out_y)
        for ($out_x = 0; $out_x < $crop_width; ++$out_x)
        {
            $pixel = imagecolorat($input, $min_x + $out_x, $min_y + $out_y);
            if ($truecolor)
            {
                $alpha = ($pixel >> 24) & 0x7F;
                $red = ($pixel >> 16) & 0xFF;
                $green = ($pixel >> 8) & 0xFF;
                $blue = $pixel & 0xFF;
            }
            else
            {
                $rgba = imagecolorsforindex($input, $pixel);
                $alpha = (int)($rgba["alpha"] ?? 127);
                $red = (int)($rgba["red"] ?? 255);
                $green = (int)($rgba["green"] ?? 255);
                $blue = (int)($rgba["blue"] ?? 255);
            }
            $luminance = (299 * $red + 587 * $green + 114 * $blue) / 1000;
            $effective_opacity = (127 - $alpha) * (255 - $luminance) / 255;
            $output_alpha = max(0, min(127, 127 - (int)round($effective_opacity)));
            if (!isset($colors[$output_alpha]))
                $colors[$output_alpha] = imagecolorallocatealpha($output, 0, 0, 0, $output_alpha);
            imagesetpixel($output, $out_x, $out_y, $colors[$output_alpha]);
        }
    imagedestroy($input);

    if (($ret = new_directory($target))->is_error())
    {
        imagedestroy($output);
        return (["ok" => false, "error" => "CannotWritePngFile", "details" => strval($ret)]);
    }
    $tmp = tempnam(dirname($target), ".signature-");
    if ($tmp === false || !@imagepng($output, $tmp, 6))
    {
        imagedestroy($output);
        if ($tmp !== false)
            @unlink($tmp);
        return (["ok" => false, "error" => "CannotWritePngFile"]);
    }
    imagedestroy($output);
    @chmod($tmp, 0640);
    if (!@rename($tmp, $target))
    {
        @unlink($tmp);
        return (["ok" => false, "error" => "CannotWritePngFile"]);
    }

    return ([
        "ok" => true,
        "hash" => hash_file("sha256", $target),
        "width" => $crop_width,
        "height" => $crop_height,
    ]);
}

/**
 * Return the canonical profile signature used by documents.
 *
 * Signatures are normalized directly in admin/signature.png.  Older files
 * created before automatic trimming are rewritten in place the first time a
 * document uses them.  The former signature.document.png derivative is only
 * recognized as a migration aid and is removed after its contents have been
 * promoted to the canonical file.
 */
function user_identity_document_signature_file(array $user)
{
    $source = user_identity_signature_file($user);
    if ($source == "" || !is_file($source))
        return ("");

    $legacy = dirname($source)."/signature.document.png";
    if (is_file($legacy))
    {
        $normalized = user_identity_store_signature_png($legacy, $source, false);
        @unlink($legacy);
        if ($normalized["ok"])
            return ($source);
    }

    // Normalize an old, wide-canvas signature directly in place.  New
    // signatures already went through the same routine when they were saved;
    // applying it again is harmless and keeps a single canonical file.
    user_identity_store_signature_png($source, $source, false);
    return ($source);
}

function user_identity_nested_unset(&$tree, $path)
{
    $parts = is_array($path) ? $path : user_identity_path_parts($path);
    if (!count($parts))
        return ;
    $part = array_shift($parts);
    if (!is_array($tree) || !array_key_exists($part, $tree))
        return ;
    if (!count($parts))
    {
        unset($tree[$part]);
        return ;
    }
    user_identity_nested_unset($tree[$part], $parts);
    if (is_array($tree[$part]) && !count($tree[$part]))
        unset($tree[$part]);
}

function user_identity_delete_registration_paths($id_user, array $paths)
{
    $id_user = (int)$id_user;
    if (!count($paths))
        return (new Response);
    $user = db_select_one("* FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL)
        return (new ErrorResponse("UserNotFound"));
    $administrative = user_identity_administrative_data($user);
    foreach ($paths as $path)
    {
        $student_path = user_identity_student_path($path);
        if ($student_path !== NULL)
            user_identity_nested_unset($administrative, user_identity_path_parts($student_path));
        else if (isset($administrative["DocumentContext"]))
            user_identity_nested_unset($administrative["DocumentContext"], user_identity_path_parts($path));
        if (isset($administrative["RegistrationAnswered"][$path]))
            unset($administrative["RegistrationAnswered"][$path]);
    }
    $json = json_encode($administrative, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return (set_user_data($id_user, ["administrative_data" => $json === false ? "{}" : $json]));
}
