<?php

require_once (__DIR__."/dabsic_form.php");
require_once (__DIR__."/document_hash.php");

function build_document_list($value)
{
    if ($value === NULL)
	return ([]);
    if (is_array($value))
	return ($value);
    return ([$value]);
}

function build_document_clean_path($path)
{
    if (!is_string($path) || $path == "")
	return (NULL);
    return (str_replace("~", getenv("HOME"), $path));
}

function build_document_existing_files($files)
{
    $out = [];
    foreach (build_document_list($files) as $file)
    {
	$file = build_document_clean_path($file);
	if ($file !== NULL && file_exists($file) && !is_dir($file))
	    $out[] = $file;
    }
    return (array_values(array_unique($out)));
}

function build_document_existing_dirs($dirs)
{
    $out = [];
    foreach (build_document_list($dirs) as $dir)
    {
	$dir = build_document_clean_path($dir);
	if ($dir !== NULL && is_dir($dir))
	    $out[] = rtrim($dir, "/")."/";
    }
    return (array_values(array_unique($out)));
}

function build_document_scalar($value)
{
    if ($value === NULL)
	return ("");
    if (is_bool($value))
	return ($value ? "true" : "false");
    if (is_array($value))
	return (implode(", ", array_map("build_document_scalar", $value)));
    return ((string)$value);
}

function build_document_flatten_fields($prefix, $value, &$out)
{
    if (is_array($value))
    {
	foreach ($value as $key => $sub)
	{
	    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$key))
		continue ;
	    build_document_flatten_fields($prefix == "" ? $key : $prefix.".".$key, $sub, $out);
	}
	return ;
    }
    if ($prefix != "")
	$out[$prefix] = build_document_scalar($value);
}

function build_document_check_field_name($key)
{
    return (preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.]*$/', (string)$key));
}

function build_document_field_part($key, $value)
{
    if (!build_document_check_field_name($key))
	return (new ErrorResponse("InvalidParameter", $key));
    return ([
	"type" => "field",
	"key" => (string)$key,
	"value" => build_document_scalar($value),
    ]);
}

function build_document_file_part($file, $required = true)
{
    $file = build_document_clean_path($file);
    if ($file === NULL)
	return (new ErrorResponse("MissingFile"));
    if (!file_exists($file) || is_dir($file))
    {
	if ($required)
	    return (new ErrorResponse("MissingFile", $file));
	return (NULL);
    }
    return ([
	"type" => "file",
	"file" => $file,
    ]);
}

function build_document_normalize_part($part)
{
    if (is_array($part))
    {
	if (isset($part["file"]))
	    return (build_document_file_part($part["file"], isset($part["required"]) ? $part["required"] : true));
	if (isset($part["field"]) && isset($part["value"]))
	    return (build_document_field_part($part["field"], $part["value"]));
	if (isset($part["key"]) && isset($part["value"]))
	    return (build_document_field_part($part["key"], $part["value"]));
	return (new ErrorResponse("InvalidParameter", "document part"));
    }

    if (is_string($part) && preg_match('/^([a-zA-Z_][a-zA-Z0-9_\.]*)=(.*)$/', $part, $match))
	return (build_document_field_part($match[1], $match[2]));
    return (build_document_file_part($part));
}


/**
 * Escape plain form text before it reaches the LaTeX renderer.
 *
 * Values remain unmodified in their authoritative Dabsic storage. This is a
 * rendering boundary only: DocBuilder receives a temporary resolved document
 * where textual FormGroup fields are TeX-safe. Besides making ordinary input
 * such as "R&D" work, this prevents free-text form values from becoming TeX
 * commands while xelatex is invoked with shell escape enabled.
 */
function build_document_latex_escape_text($value)
{
    return (strtr((string)$value, [
        "\\" => "\\textbackslash{}",
        "{" => "\\{",
        "}" => "\\}",
        "$" => "\\$",
        "&" => "\\&",
        "#" => "\\#",
        "_" => "\\_",
        "%" => "\\%",
        "~" => "\\textasciitilde{}",
        "^" => "\\textasciicircum{}",
    ]));
}

function build_document_latex_form_field_is_text(array $definition)
{
    if (!empty($definition["raw_latex"]))
        return (false);

    // These values are controls/identifiers first and text second. They are
    // routinely consumed by IfC or server-side lookup logic and are not free
    // prose. Computed fields, on the other hand, may contain labels/currency
    // text and are safe to escape like ordinary display text.
    $type = strtolower(trim((string)($definition["type"] ?? "text")));
    return (!in_array($type, [
        "radio", "checkbox", "scale", "boolean_checkbox", "boolean",
        "billing_template",
    ], true));
}

function build_document_latex_form_metadata(array $parts)
{
    foreach ($parts as $part)
    {
        if (($part["type"] ?? "") !== "file")
            continue ;
        $file = (string)($part["file"] ?? "");
        if ($file == "" || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== "dab")
            continue ;
        $metadata = dabsic_form_form_metadata($file);
        if (is_array($metadata) && count($metadata["fields"] ?? []))
            return ($metadata);
    }
    return (NULL);
}

function build_document_latex_form_overrides($merged_file, array $metadata)
{
    $content = @file_get_contents($merged_file);
    if ($content === false)
        return (new ErrorResponse("CannotReadFile", $merged_file));

    $process = dabsic_form_process("mergeconf -if .dabsic -of .json", $content);
    if (($process["status"] ?? 1) !== 0)
        return (new ErrorResponse("CannotExecute", dabsic_form_clean_diagnostic($process["stderr"] ?? "")));
    $tree = json_decode((string)($process["stdout"] ?? ""), true);
    if (!is_array($tree))
        return (new ErrorResponse("CannotReadFile", "resolved Dabsic form values"));

    $values = [];
    dabsic_form_flatten_values($tree, "", $values);
    $overrides = [];
    foreach (($metadata["fields"] ?? []) as $field => $definition)
    {
        if (!build_document_latex_form_field_is_text((array)$definition) ||
            !array_key_exists($field, $values) || is_array($values[$field]) || is_object($values[$field]))
            continue ;
        $raw = (string)$values[$field];
        $escaped = build_document_latex_escape_text($raw);
        if ($escaped !== $raw)
            $overrides[$field] = $escaped;
    }
    return ($overrides);
}

function build_document_latex_safe_merged_file($merged_file, array $parts, array $include_paths = [])
{
    $metadata = build_document_latex_form_metadata($parts);
    if ($metadata === NULL)
        return (new ValueResponse(["file" => $merged_file, "temporary" => false]));

    $overrides = build_document_latex_form_overrides($merged_file, $metadata);
    if (is_object($overrides) && $overrides->is_error())
        return ($overrides);
    if (!count($overrides))
        return (new ValueResponse(["file" => $merged_file, "temporary" => false]));

    $safe_file = $merged_file.".texsafe.dab";
    $safe_parts = [["type" => "file", "file" => $merged_file]];
    foreach ($overrides as $field => $value)
        $safe_parts[] = ["type" => "field", "key" => $field, "value" => $value];
    $ret = run_command(build_document_mergeconf_command($safe_parts, $safe_file, $include_paths));
    if ($ret["exit_code"] !== 0 || !file_exists($safe_file))
    {
        @unlink($safe_file);
        return (new ErrorResponse("CannotExecute", trim($ret["stderr"]."\n".$ret["stdout"])));
    }
    return (new ValueResponse(["file" => $safe_file, "temporary" => true]));
}

function build_document_mergeconf_command(array $parts, $output_file, array $include_paths = [])
{
    $cmd = "mergeconf";
    foreach (build_document_existing_dirs($include_paths) as $path)
	$cmd .= " -I ".escapeshellarg($path);
    foreach ($parts as $part)
    {
	if ($part["type"] == "file")
	    $cmd .= " -i ".escapeshellarg($part["file"]);
	else if ($part["type"] == "field")
	    $cmd .= " -m ".escapeshellarg($part["key"]."=".$part["value"]);
    }
    $cmd .= " -o ".escapeshellarg($output_file)." --resolve";
    return ($cmd);
}

function build_document_render_command($input_file, $output_name, array $include_paths = [], $hash_file = "")
{
    $cmd = "docbuilder";
    foreach (build_document_existing_dirs($include_paths) as $path)
	$cmd .= " -I ".escapeshellarg($path);
    if ($hash_file != "")
        $cmd .= " --hash-file ".escapeshellarg($hash_file);
    $cmd .= " -i ".escapeshellarg($input_file);
    $cmd .= " -o ".escapeshellarg($output_name);
    return ($cmd);
}

function build_document_from_parts($output_name, array $document_parts, array $include_paths = [])
{
    $output_name = build_document_clean_path($output_name);
    if ($output_name === NULL)
	return (new ErrorResponse("MissingFile", "output"));

    $parts = [];
    foreach ($document_parts as $part)
    {
	$part = build_document_normalize_part($part);
	if (is_object($part) && $part->is_error())
	    return ($part);
	if ($part !== NULL)
	{
	    $parts[] = $part;
	    if ($part["type"] == "file")
		foreach (dabsic_form_mergeconf_fields_for_file($part["file"]) as $field)
		{
		    $pos = strpos($field, "=");
		    if ($pos !== false)
			$parts[] = build_document_field_part(substr($field, 0, $pos), substr($field, $pos + 1));
		}
	}
    }
    if (!count(array_filter($parts, function($part) { return ($part["type"] == "file"); })))
	return (new ErrorResponse("MissingFile", "document model"));

    if (!is_dir(dirname($output_name)))
    {
	$ret = new_directory($output_name);
	if ($ret->is_error())
	    return ($ret);
    }

    $tmp = tempnam(sys_get_temp_dir(), "infosphere_doc_");
    if ($tmp === false)
	return (new ErrorResponse("CannotWriteFile", "temporary document"));
    @unlink($tmp);
    $merged = $tmp.".dab";
    $hash_file = $tmp.".sha256";

    // Keep one explicit mergeconf pass for Infosphere diagnostics/forms, but let
    // DocBuilder compute the authoritative fingerprint from the resolved input.
    $merge_parts = document_builder_append_dabsic_hash_parts($parts, "");
    $ret = run_command(build_document_mergeconf_command($merge_parts, $merged, $include_paths));
    if ($ret["exit_code"] !== 0 || !file_exists($merged))
    {
	@unlink($merged);
	return (new ErrorResponse("CannotExecute", trim($ret["stderr"]."\n".$ret["stdout"])));
    }

    // FormGroup values are authoritative plain data, not TeX source. Build a
    // temporary rendering copy with only textual form fields escaped. The
    // original merged Dabsic remains untouched and stored form data never gains
    // presentation-level backslashes such as \& or \_.
    $render_input = $merged;
    $safe = build_document_latex_safe_merged_file($merged, $parts, $include_paths);
    if ($safe->is_error())
    {
        @unlink($merged);
        @unlink($hash_file);
        return ($safe);
    }
    if (!empty($safe->value["file"]))
        $render_input = (string)$safe->value["file"];

    $ret = run_command(build_document_render_command($render_input, $output_name, $include_paths, $hash_file));
    if (!empty($safe->value["temporary"]) && $render_input !== $merged)
        @unlink($render_input);
    @unlink($merged);
    if ($ret["exit_code"] !== 0 || !file_exists($output_name))
    {
        @unlink($hash_file);
	return (new ErrorResponse("CannotExecute", trim($ret["stderr"]."\n".$ret["stdout"])));
    }

    $dabsic_hash = strtolower(trim((string)@file_get_contents($hash_file)));
    @unlink($hash_file);
    if (!preg_match('/^[a-f0-9]{64}$/D', $dabsic_hash))
        return (new ErrorResponse("CannotReadFile", "DocBuilder Dabsic hash"));

    return (new ValueResponse([
	"output" => $output_name,
	"stdout" => $ret["stdout"],
	"stderr" => $ret["stderr"],
        "dabsic_hash" => $dabsic_hash,
    ]));
}

function build_document($output_name, $input_files, array $dyndata = [], array $include_paths = [])
{
    $parts = [];
    foreach (build_document_list($input_files) as $file)
	$parts[] = ["file" => $file];

    $flat = [];
    foreach ($dyndata as $key => $value)
    {
	if (is_array($value))
	    build_document_flatten_fields($key, $value, $flat);
	else
	    $flat[$key] = build_document_scalar($value);
    }
    foreach ($flat as $key => $value)
	$parts[] = ["field" => $key, "value" => $value];

    return (build_document_from_parts($output_name, $parts, $include_paths));
}

function document_builder_language_candidates($language = NULL)
{
    global $Language;

    if ($language === NULL || $language == "")
	$language = isset($Language) ? $Language : "fr";
    $language = strtolower($language);
    return (array_values(array_unique([$language, strtoupper($language), "fr", "FR", ""])));
}

function document_builder_model_names($kind)
{
    $kind = strtoupper((string)$kind);
    $map = [
	"ECL" => ["contrat_ecole.dab", "contract_school.dab", "ecl.dab", "ECL.dab"],
	"OF" => ["contrat_of_hors_alternance.dab", "contrat_of.dab", "of.dab", "OF.dab"],
	"OFA" => ["contrat_of_alternance.dab", "ofa.dab", "OFA.dab"],
	"CFA" => ["contrat_cfa.dab", "cfa.dab", "CFA.dab"]
    ];
    if (isset($map[$kind]))
	return ($map[$kind]);
    if (preg_match('/^[a-zA-Z0-9_\-]+(\.dab)?$/', $kind))
    {
	$base = preg_replace('/\.dab$/', '', $kind);
	return ([$base.".dab", strtolower($base).".dab"]);
    }
    return ([]);
}

function document_builder_model_dirs($language = NULL)
{
    global $Configuration;

    $dirs = [];
    foreach (document_builder_language_candidates($language) as $lng)
    {
	$suffix = $lng == "" ? "" : $lng."/";
	$dirs[] = "./dres/docs/".$suffix;
	$dirs[] = "./dres/doc/".$suffix;
	$dirs[] = "./res/docs/".$suffix;
	if (isset($Configuration))
	    $dirs[] = $Configuration->DocDir().$suffix;
    }
    return (build_document_existing_dirs($dirs));
}

function document_builder_find_model($kind, $language = NULL)
{
    $kind = (string)$kind;
    if (file_exists($kind) && !is_dir($kind))
	return ($kind);
    foreach (document_builder_model_dirs($language) as $dir)
	foreach (document_builder_model_names($kind) as $name)
	    if (file_exists($dir.$name) && !is_dir($dir.$name))
		return ($dir.$name);
    return (NULL);
}

function document_builder_description_file_names(array $extra_names = [], $legacy = true)
{
    $names = ["description.dab"];
    foreach ($extra_names as $name)
	if ($name != "" && !in_array($name, $names))
	    $names[] = $name;
    if ($legacy)
	foreach (["identity.dab", "identite.dab", "configuration.dab", "school.dab"] as $name)
	    if (!in_array($name, $names))
		$names[] = $name;
    return ($names);
}

function document_builder_description_files($base, array $names = [], $legacy = true)
{
    $base = rtrim($base, "/")."/";
    $names = count($names) ? $names : document_builder_description_file_names([], $legacy);
    $files = [];
    foreach ($names as $name)
	if (file_exists($base.$name) && !is_dir($base.$name))
	    $files[] = $base.$name;
    return ($files);
}

function document_builder_identity_files($base, array $names = [])
{
    // Ancien nom conservé: les exports d'éléments de BDD s'appellent
    // désormais description.dab. Les anciens noms ne sont lus qu'en
    // compatibilité.
    return (document_builder_description_files($base, $names));
}

function document_builder_school_description_files($school)
{
    global $Configuration;

    if (!isset($school["codename"]) || $school["codename"] == "")
	return ([]);
    $base = $Configuration->SchoolsDir($school["codename"]);
    return (array_merge(
	document_builder_description_files($base),
	document_builder_description_files($base."admin/")
    ));
}

function document_builder_school_identity_files($school)
{
    return (document_builder_school_description_files($school));
}

function document_builder_contract_kind($data)
{
    if (isset($data["decision"]))
    {
	$map = ["ecole" => "ECL", "of" => "OF", "ofa" => "OFA", "cfa" => "CFA"];
	if (isset($map[$data["decision"]]))
	    return ($map[$data["decision"]]);
    }
    foreach (["contract", "type", "document", "model", "kind", "doc"] as $key)
	if (isset($data[$key]) && $data[$key] != "")
	    return (strtoupper($data[$key]));
    return ("ECL");
}

function document_builder_request_extra_fields(array $data)
{
    $extra = [];

    if (isset($data["fields"]))
    {
	$fields = is_array($data["fields"]) ? $data["fields"] : explode(" ", $data["fields"]);
	foreach ($fields as $field)
	{
	    $field = trim((string)$field);
	    if ($field == "")
		continue ;
	    if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_\.]*)=(.*)$/', $field, $match))
		return (new ErrorResponse("InvalidParameter", $field));
	    $extra[$match[1]] = $match[2];
	}
    }
    if (isset($data["output"]) && trim((string)$data["output"]) != "")
	$extra["Output"] = trim((string)$data["output"]);
    return (new ValueResponse($extra));
}

function document_builder_name(array $user)
{
    $name = trim(@$user["first_name"]." ".@$user["family_name"]);
    if ($name == "")
	$name = @$user["nickname"];
    if ($name == "")
	$name = @$user["codename"];
    return ($name);
}

function document_builder_optional_contract_field(array $fields, array &$out, $from, $to = NULL)
{
    if ($to === NULL)
	$to = $from;
    if (!array_key_exists($from, $fields))
	return ;
    if ($fields[$from] === NULL || $fields[$from] === "")
	return ;
    $out[$to] = $fields[$from];
}

function document_builder_contract_person_fields(array $user)
{
    $fields = refresh_user_fields($user);

    $out = [
	"id" => $user["id"] ?? -1,
	"codename" => $user["codename"] ?? "",
	"first_name" => $fields["first_name"] ?? "",
	"use_name" => $fields["use_name"] ?? "",
	"family_name" => $fields["family_name"] ?? "",
	"gender" => $fields["gender"] ?? "",
	"mail" => $fields["mail"] ?? "",
	"courriel" => $fields["courriel"] ?? ($fields["mail"] ?? ""),
	"phone" => $fields["phone"] ?? "",
	"address" => $fields["address"] ?? "",
	"city" => $fields["city"] ?? "",
	"postal_code" => $fields["postal_code"] ?? "",
	"birth_date" => $fields["birth_date"] ?? "",
	"birth_city" => $fields["birth_city"] ?? "",
	"birth_place" => $fields["birth_place"] ?? ($fields["birth_city"] ?? ""),
	"birth_country" => $fields["birth_country"] ?? "",
	"nationality" => $fields["nationality"] ?? "",

	// Ces deux clés doivent rester en majuscules: le modèle appelle INE/NIR,
	// pas Ine/Nir.
	"INE" => $fields["ine"] ?? "",
	"NIR" => $fields["nir"] ?? "",

	"school_period" => $fields["school_period"] ?? "",
	"chosen_class" => $fields["chosen_class"] ?? "",
	"month" => $fields["month"] ?? "",
	"other_month_day" => $fields["other_month_day"] ?? "",
	"day" => $fields["day"] ?? "",
	"chosen_specialty" => $fields["chosen_specialty"] ?? "",
    ];

    $administrative = function_exists("user_identity_student_administrative_fields")
        ? user_identity_student_administrative_fields($user) : [];
    foreach ([
        "handicap" => "Handicap",
        "handicap_kind" => "HandicapKind",
        "resubscribe" => "Resubscribe",
        "last_class" => "LastClass",
        "last_class_success" => "LastClassSuccess",
        "send_school_report" => "SendSchoolReport",
        "intranet_access" => "IntranetAccess"
    ] as $field => $pascal)
        if (array_key_exists($field, $user) || array_key_exists($field, $administrative) || array_key_exists($pascal, $administrative))
            $out[$field] = $fields[$field];

    if (count($administrative))
        $out = user_identity_merge_dabsic_tree($out, $administrative);
    if (function_exists("user_identity_document_signature_file"))
    {
        $signature = user_identity_document_signature_file($user);
        if ($signature != "" && is_file($signature))
            $out["signature"] = $signature;
    }
    return ($out);
}

function document_builder_existing_file_from_candidates(array $candidates)
{
    foreach ($candidates as $candidate)
    {
	if (!is_string($candidate) || $candidate == "")
	    continue ;
	if ($candidate[0] != "/")
	    $candidate = dirname(__DIR__)."/".$candidate;
	if (file_exists($candidate) && !is_dir($candidate))
	    return ($candidate);
    }
    return ("");
}

function document_builder_fetch_full_user_from_row(array $row)
{
    if (isset($row["id"]) && (int)$row["id"] > 0)
    {
	$ret = resolve_codename("user", (int)$row["id"], "codename", true);
	if (is_object($ret) && !$ret->is_error())
	    return ($ret->value);
    }
    if (isset($row["codename"]) && $row["codename"] != "")
    {
	$ret = resolve_codename("user", $row["codename"], "codename", true);
	if (is_object($ret) && !$ret->is_error())
	    return ($ret->value);
    }
    return ([]);
}

function document_builder_fetch_student_school(array $student)
{
    if (!isset($student["school"]) || !count($student["school"]))
	return (NULL);
    $school = $student["school"][array_key_first($student["school"])] ;
    if (isset($school["id_school"]) && (int)$school["id_school"] > 0)
	return (fetch_school((int)$school["id_school"]));
    if (isset($school["codename"]) && $school["codename"] != "")
	return (fetch_school($school["codename"]));
    return (NULL);
}

function document_builder_director_role(array $director)
{
    $gender = strtolower(trim(refresh_user_value($director, ["gender", "sex"], "")));

    if (in_array($gender, ["f", "female", "femme", "madame", "mme"]))
	return ("Directrice");
    if (in_array($gender, ["m", "male", "homme", "monsieur", "mr", "m."]))
	return ("Directeur");
    return ("Directeur(trice)");
}

function document_builder_director_document_path(array $director, $school_codename, $kind)
{
    global $Configuration;

    if (!isset($director["codename"]) || $director["codename"] == "")
	return ("");
    $user_dir = $Configuration->UsersDir($director["codename"]);
    $extensions = ["png", "jpg", "jpeg", "pdf"];
    $names = [];

    if ($kind == "initials")
	$names[] = "initials";
    else
	$names[] = "signature";

    $dirs = [""];
    $candidates = [];
    foreach ($dirs as $dir)
	foreach ($names as $name)
	    foreach ($extensions as $ext)
		$candidates[] = $user_dir.$dir.$name.".".$ext;
    return (document_builder_existing_file_from_candidates($candidates));
}

function document_builder_contract_director_fields(array $student)
{
    $school = document_builder_fetch_student_school($student);
    if (!is_array($school) || !isset($school["director"]) || !count($school["director"]))
	return ([]);

    $director = document_builder_fetch_full_user_from_row($school["director"][0]);
    if (!count($director))
	return ([]);

    $fields = document_builder_contract_person_fields($director);
    $fields["role"] = document_builder_director_role($director);
    $fields["identity"] = document_builder_name($director);

    $school_codename = $school["codename"] ?? "";
    $signature = document_builder_director_document_path($director, $school_codename, "signature");
    $initials = document_builder_director_document_path($director, $school_codename, "initials");
    if ($signature != "")
	$fields["signature"] = $signature;
    if ($initials != "")
	$fields["initials"] = $initials;
    return ($fields);
}

function document_builder_fetch_relation_representatives($student_id, $relation)
{
    $student_id = (int)$student_id;
    $rows = db_select_all("
        user.*,
        parent_child.relation as relation,
        parent_child.id as id_relation
        FROM parent_child
        LEFT JOIN user ON parent_child.id_parent = user.id
        WHERE parent_child.id_child = $student_id
        AND user.id IS NOT NULL
        AND user.authority != -1
        ORDER BY parent_child.id ASC
    ");
    return (array_values(array_filter($rows, function($row) use ($relation) {
        return (user_relation_has($row["relation"] ?? "", $relation));
    })));
}

function document_builder_fetch_legal_representatives($student_id)
{
    return (document_builder_fetch_relation_representatives($student_id, "legal"));
}


function document_builder_signatory_context(array $person, $role)
{
    $role = trim((string)$role);
    if ($role == "" || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $role) !== 1)
        return ($person);
    $person["Signatory"] = 1;
    $person["As"] = $role;
    return ($person);
}

function document_builder_contract_context(array $student, $kind)
{
    global $Language;

    $student_fields = document_builder_contract_person_fields($student);
    $parents = document_builder_fetch_legal_representatives($student["id"]);
    $financials = document_builder_fetch_relation_representatives($student["id"], "financial");

    $ctx = [
	"contract" => [
	    "type" => strtoupper($kind),
	    "kind" => strtoupper($kind),
	    "generation_date" => date("Y-m-d H:i:s"),
	    "language" => $Language,
	],
	"signatories" => [
	    "student" => $student_fields,
	],
    ];

    $school_context = document_builder_school_context(document_builder_student_school($student));
    if (count($school_context))
    {
	$ctx["company"] = $school_context;
	$ctx["school"] = $school_context;
    }
    $director = document_builder_contract_director_fields($student);
    if (count($director))
	$ctx["signatories"]["director"] = $director;
    if (isset($student["cycle"]) && count($student["cycle"]))
    {
	$cycle = $student["cycle"][array_key_first($student["cycle"])] ;
	$ctx["cycle"] = [
	    "id" => @$cycle["id_cycle"],
	    "codename" => @$cycle["codename"],
	    "name" => @$cycle["name"],
	];
    }

    for ($i = 0; $i < 2; ++$i)
    {
	$key = "legal".($i + 1);
	if (isset($parents[$i]))
	    $ctx[$key] = document_builder_contract_person_fields($parents[$i]);
	else
	    $ctx[$key] = [];
    }

    // Les réponses dynamiques du formulaire public complètent le contexte
    // sans dupliquer dans PHP la liste des champs demandés par les modèles.
    if (function_exists("user_identity_document_context"))
        $ctx = user_identity_merge_dabsic_tree($ctx, user_identity_document_context($student));

    // Finance and emergency contacts may explicitly designate an already
    // known student or legal representative. Complete those blocks from the
    // known person instead of asking for the same identity twice.
    $ctx = dabsic_pascalcase_array($ctx);

    // Une relation financière explicite dans parent_child est l'autorité sur
    // l'identité du payeur. Elle doit donc primer sur un ancien bloc Finance
    // conservé dans DocumentContext.
    if (count($financials))
    {
        $financial = $financials[0];
        $finance_is = "Other";
        if (isset($parents[0]["id"]) && (int)$parents[0]["id"] == (int)$financial["id"])
            $finance_is = "Legal1";
        else if (isset($parents[1]["id"]) && (int)$parents[1]["id"] == (int)$financial["id"])
            $finance_is = "Legal2";
        $finance = dabsic_pascalcase_array(document_builder_contract_person_fields($financial));
        $finance["Is"] = $finance_is;
        if (!isset($ctx["Signatories"]) || !is_array($ctx["Signatories"]))
            $ctx["Signatories"] = [];
        $ctx["Signatories"]["Finance"] = $finance;
    }
    else
    {
        // Pour l'analyse du formulaire historique, Finance reste une projection
        // du résultat normalisé DocBuilder. Sans payeur distinct, l'étudiant
        // occupe aussi le rôle Finance.
        if (!isset($ctx["Signatories"]) || !is_array($ctx["Signatories"]))
            $ctx["Signatories"] = [];
        $finance = $ctx["Signatories"]["Student"] ?? [];
        $finance["Is"] = "Student";
        $ctx["Signatories"]["Finance"] = $finance;
    }

    if (function_exists("user_identity_complete_signatory_context"))
        $ctx = user_identity_complete_signatory_context($ctx);
    return ($ctx);
}


function document_builder_documentation_file_root()
{
    return ("admin/documentation");
}

function document_builder_letter_file_root()
{
    return ("admin/letters");
}

function document_builder_original_generator()
{
    global $OriginalUser;

    if (isset($OriginalUser) && isset($OriginalUser["id"]))
    {
	$ret = resolve_codename("user", (int)$OriginalUser["id"], "codename", true);
	if (is_object($ret) && !$ret->is_error())
	    return ($ret->value);
    }
    return ([]);
}

function document_builder_person_context(array $user)
{
    $fields = document_builder_contract_person_fields($user);
    $identity = document_builder_name($user);
    $fields["id"] = isset($user["id"]) ? (int)$user["id"] : -1;
    $fields["codename"] = $user["codename"] ?? "";
    $fields["nickname"] = $user["nickname"] ?? "";
    $fields["identity"] = $identity;
    $fields["name"] = $identity;
    $fields["street"] = $fields["address"] ?? "";
    $fields["postal_city"] = trim(($fields["postal_code"] ?? "")." ".($fields["city"] ?? ""));
    $signature = function_exists("user_identity_document_signature_file") ? user_identity_document_signature_file($user) : "";
    $fields["signature"] = ($signature != "" && is_file($signature)) ? $signature : "";
    return ($fields);
}

function document_builder_financial_responsible(array $student)
{
    $parents = document_builder_fetch_legal_representatives($student["id"]);
    if (count($parents))
	return ($parents[0]);
    return ($student);
}

function document_builder_student_school(array $student)
{
    $school = document_builder_fetch_student_school($student);
    if (!is_array($school))
	return ([]);
    refresh_school($school);
    return ($school);
}

function document_builder_school_context(array $school)
{
    if (!count($school))
	return ([]);

    $name = $school["name"] ?? ($school["fr_name"] ?? ($school["codename"] ?? ""));
    $main_info = function_exists("school_main_info") ? school_main_info($school) : ($school["main_info"] ?? "");
    $school_info = function_exists("school_private_school_info") ? school_private_school_info($school) : ($school["school_info"] ?? "");
    $formation_info = function_exists("school_formation_info") ? school_formation_info($school) : ($school["formation_info"] ?? "");
    $alternation_info = function_exists("school_alternation_info") ? school_alternation_info($school) : ($school["alternation_info"] ?? "");

    return ([
	"id" => $school["id"] ?? -1,
	"codename" => $school["codename"] ?? "",
	"name" => $name,
	"legal_name" => $school["legal_name"] ?? $name,
	"address" => $school["address"] ?? "",
	"street" => $school["address"] ?? "",
	"city" => $school["city"] ?? "",
	"phone" => $school["phone"] ?? "",
	"mail" => $school["mail"] ?? "",
	"billing_information" => $school["organization_billing_information"] ?? ($school["billing_information"] ?? ""),
	"RIB" => $school["organization_billing_information"] ?? ($school["billing_information"] ?? ""),
	"main_info" => $main_info,
	"is_school" => school_activity_flags($school)["is_school"],
        "is_of" => school_activity_flags($school)["is_of"],
        "is_cfa" => school_activity_flags($school)["is_cfa"],
	"school_info" => $school_info,
	"formation_info" => $formation_info,
	"alternation_info" => $alternation_info,
	"main" => $main_info,
	"school" => $school_info,
	"formation" => $formation_info,
	"alternation" => $alternation_info,
	"logo" => function_exists("school_document_logo_path") ? school_document_logo_path($school, true) : "",
	"document_logo" => function_exists("school_document_logo_path") ? school_document_logo_path($school, true) : "",
	"site_logo" => function_exists("school_site_logo_path") ? school_site_logo_path($school, true) : "",
	"stamp" => function_exists("school_stamp_path") ? school_stamp_path($school, true) : "",
	"logo_width" => "3cm",
	"logo_height" => "2cm",
	"document_logo_width" => "3cm",
	"document_logo_height" => "2cm",
	"site_logo_width" => "3cm",
	"site_logo_height" => "2cm",
    ]);
}

function document_builder_letter_model_names($kind)
{
    $kind = trim((string)$kind);
    if ($kind == "")
	return ([]);
    if (preg_match('/^[a-zA-Z0-9_\/\.-]+\.dab$/', $kind))
	return ([$kind]);
    if (!preg_match('/^[a-zA-Z0-9_\/-]+$/', $kind))
	return ([]);
    return ([
	"letters/".$kind.".dab",
	"lettres/".$kind.".dab",
	"courriers/".$kind.".dab",
	"letter_".$kind.".dab",
	"lettre_".$kind.".dab",
	"courrier_".$kind.".dab",
	$kind.".dab",
    ]);
}

function document_builder_find_letter_model($kind, $language = NULL)
{
    $kind = (string)$kind;
    if (file_exists($kind) && !is_dir($kind))
	return ($kind);
    foreach (document_builder_model_dirs($language) as $dir)
	foreach (document_builder_letter_model_names($kind) as $name)
	    if (file_exists($dir.$name) && !is_dir($dir.$name))
		return ($dir.$name);
    return (NULL);
}

function document_builder_letter_models($language = NULL)
{
    $models = [];
    foreach (document_builder_model_dirs($language) as $dir)
    {
	foreach (["letter_*.dab", "lettre_*.dab", "courrier_*.dab", "letters/*.dab", "lettres/*.dab", "courriers/*.dab"] as $pattern)
	{
	    foreach (glob($dir.$pattern) as $file)
	    {
		if (is_dir($file))
		    continue ;
		$key = preg_replace('/\.dab$/', '', basename($file));
		$key = preg_replace('/^(letter_|lettre_|courrier_)/', '', $key);
		$models[$key] = $file;
	    }
	}
    }
    ksort($models);
    return ($models);
}

function document_builder_letter_context(array $student, array $generator, array $financial, array $school, $kind)
{
    global $Language;

    $student_context = document_builder_person_context($student);
    $generator_context = document_builder_person_context($generator);
    $financial_context = document_builder_person_context($financial);
    $school_context = document_builder_school_context($school);

    return ([
	"letter" => [
	    "kind" => $kind,
	    "type" => $kind,
	    "generation_date" => datex("Y-m-d H:i:s"),
	    "language" => $Language,
	],
	"student" => $student_context,
	"destination" => $student_context,
	"target" => $student_context,
	"generator" => $generator_context,
	"financial" => $financial_context,
	"financial_responsible" => $financial_context,
	"responsible" => $financial_context,
	"school" => $school_context,
	"company" => $school_context,
	"signature_sources" => [
	    "student" => document_builder_signatory_context($student_context, "Student"),
	    "generator" => document_builder_signatory_context($generator_context, "Generator"),
	    "financial" => document_builder_signatory_context($financial_context, "Finance"),
	],
    ]);
}

function document_builder_full_student($id_user)
{
    $id_user = (int)$id_user;
    if (($ret = fetch_user($id_user))->is_error())
	return ($ret);
    $student = $ret->value;
    if (($full = resolve_codename("user", $id_user, "codename", true))->is_error())
	return ($full);
    $student = array_merge($student, $full->value);
    get_user_promotions($student);
    get_user_school($student);
    return (new ValueResponse($student));
}

function document_builder_output_from_extra($directory, $default_name, array $extra_fields)
{
    $directory = rtrim($directory, "/")."/";
    if (isset($extra_fields["Output"]) && preg_match('/^[a-zA-Z0-9_\-.]+$/', $extra_fields["Output"]))
	return ($directory.$extra_fields["Output"]);
    return ($directory.$default_name);
}

function document_builder_context_field_parts($kind, array $fields, array $extra_fields)
{
    global $Language;

    $parts = [];
    foreach ($fields as $key => $value)
	$parts[] = ["field" => $key, "value" => $value];
    $parts[] = ["field" => "Language", "value" => $Language];
    foreach ($extra_fields as $key => $value)
	if ($key != "Output")
	    $parts[] = ["field" => $key, "value" => $value];
    return ($parts);
}

function build_user_document($student, $kind, $model, $document_dir, $context_name, $output_name, $context_builder, array $fields, array $extra_fields = [])
{
    global $Configuration;
    global $Language;

    if (!is_array($student) || !isset($student["id"]) || !isset($student["codename"]))
	return (new ErrorResponse("InvalidParameter", "student"));
    if ($model === NULL || $model == "")
	return (new ErrorResponse("MissingFile", "document model"));

    $user_dir = $Configuration->UsersDir($student["codename"]);
    $document_dir = rtrim($document_dir, "/")."/";
    $context = $document_dir.$context_name;
    $output = document_builder_output_from_extra($document_dir, $output_name, $extra_fields);

    if (($ret = refresh_user((int)$student["id"]))->is_error())
	return ($ret);
    $context_data = call_user_func($context_builder, $student, $kind);
    if (is_object($context_data) && $context_data->is_error())
	return ($context_data);
    if (($ret = generate_dabsic($context_data, $context))->is_error())
	return ($ret);

    $inputs = [$model];
    $include_paths = document_builder_model_dirs($Language);
    $include_paths[] = dirname($model)."/";
    $include_paths[] = $user_dir;
    $include_paths[] = $user_dir."admin/";
    $include_paths[] = $document_dir;

    $school = document_builder_student_school($student);
    if (count($school) && isset($school["codename"]))
	$include_paths[] = $Configuration->SchoolsDir($school["codename"]);

    // Les exports permanents d'éléments de BDD sont maintenant des
    // description.dab non scopés. On ne les inclut donc plus directement dans
    // un document: le contexte temporaire ci-dessous place les mêmes données
    // sous le scope attendu par le modèle (Company, Signatories.Student, etc.).
    $inputs[] = $context;

    $parts = [];
    foreach (array_values(array_unique($inputs)) as $input)
	$parts[] = ["file" => $input];
    $parts = array_merge($parts, document_builder_context_field_parts($kind, $fields, $extra_fields));
    return (build_document_from_parts($output, $parts, $include_paths));
}

function document_builder_letter_context_for_student(array $student, $kind)
{
    $generator = document_builder_original_generator();
    if (!count($generator))
	$generator = $GLOBALS["User"];
    $financial = document_builder_financial_responsible($student);
    $school = document_builder_student_school($student);

    if (isset($generator["id"]) && (int)$generator["id"] > 0)
	refresh_user((int)$generator["id"]);
    if (isset($financial["id"]) && (int)$financial["id"] > 0 && (int)$financial["id"] != (int)$student["id"])
	refresh_user((int)$financial["id"]);

    return (document_builder_letter_context($student, $generator, $financial, $school, $kind));
}

function build_user_letter($id_user, $kind, array $extra_fields = [])
{
    global $Configuration;
    global $Language;

    if (($ret = document_builder_full_student($id_user))->is_error())
	return ($ret);
    $student = $ret->value;
    $model = document_builder_find_letter_model($kind, $Language);
    if ($model === NULL)
	return (new ErrorResponse("MissingFile", "letter model: ".$kind));

    $user_dir = $Configuration->UsersDir($student["codename"]);
    $safe_kind = preg_replace('/[^a-zA-Z0-9_\.-]+/', "_", pathinfo($kind, PATHINFO_FILENAME));
    if ($safe_kind == "")
	$safe_kind = "letter";

    return (build_user_document(
	$student,
	$kind,
	$model,
	$user_dir.document_builder_letter_file_root()."/",
	$safe_kind."_context.dab",
	datex("Ymd_His")."_".$safe_kind.".pdf",
	"document_builder_letter_context_for_student",
	[
	    "Letter.Kind" => $kind,
	    "Letter.Type" => $kind,
	],
	$extra_fields
    ));
}

function document_builder_public_url($file)
{
    $file = preg_replace('/^\.\//', '', $file);
    if (substr($file, 0, 5) == "dres/")
	return ("/".$file);
    return ($file);
}

function build_user_contract($id_user, $kind = "ECL", array $extra_fields = [])
{
    global $Configuration;
    global $Language;

    if (($ret = document_builder_full_student($id_user))->is_error())
	return ($ret);
    $student = $ret->value;
    $kind = strtoupper($kind);
    $model = document_builder_find_model($kind, $Language);
    if ($model === NULL)
	return (new ErrorResponse("MissingFile", "contract model: ".$kind));

    $user_dir = $Configuration->UsersDir($student["codename"]);
    return (build_user_document(
	$student,
	$kind,
	$model,
	$user_dir."admin/subscription/",
	"contract_".strtolower($kind)."_context.dab",
	"contract_".strtolower($kind).".pdf",
	"document_builder_contract_context",
	[
	    "Contract.Type" => $kind,
	    "Contract.Kind" => $kind,
	],
	$extra_fields
    ));
}
