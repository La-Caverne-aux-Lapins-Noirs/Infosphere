<?php

require_once (__DIR__."/internship_calendar.php");

/*
** Shared interactive field contract
** ---------------------------------
** FormGroup is the declarative source of truth shared by DocBuilder forms and
** questionnaires.  Workflows remain distinct; this file only owns field
** semantics (types, choices, normalization, labels and HTML rendering).
*/

function form_field_common_types()
{
    return (["text", "textarea", "radio", "checkbox", "scale", "boolean_checkbox", "internship_calendar"]);
}

function form_field_is_common_type($type)
{
    return (in_array(strtolower(trim((string)$type)), form_field_common_types(), true));
}


function form_field_sequence($value)
{
    if ($value === NULL || $value === "")
        return ([]);
    if (!is_array($value))
        $value = [$value];
    $out = [];
    foreach ($value as $entry)
    {
        if (is_array($entry) || is_object($entry))
            continue ;
        $out[] = trim((string)$entry);
    }
    return ($out);
}

function form_field_scalar_list($value)
{
    if ($value === NULL || $value === "")
        return ([]);
    if (!is_array($value))
        $value = [$value];
    $out = [];
    foreach ($value as $entry)
    {
        if (is_array($entry) || is_object($entry))
            continue ;
        $entry = trim((string)$entry);
        if ($entry !== "" && !in_array($entry, $out, true))
            $out[] = $entry;
    }
    return ($out);
}

function form_field_definition(array $definition)
{
    $type = strtolower(trim((string)($definition["type"] ?? "text")));
    $raw_type = $type === "" ? "text" : $type;
    if (!form_field_is_common_type($type))
        $type = "text";

    $labels = form_field_sequence($definition["choices"] ?? []);
    $values = form_field_sequence($definition["choice_values"] ?? []);

    // V2 has an explicit identity for every selectable answer.  The only
    // implicit definition left is the conventional five-point scale: omitting
    // both lists means 1..5 for both the visible label and stored value.
    if ($type === "scale" && !count($labels) && !count($values))
    {
        $labels = ["1", "2", "3", "4", "5"];
        $values = ["1", "2", "3", "4", "5"];
    }

    $valid = true;
    $errors = [];
    if (in_array($type, ["radio", "checkbox", "scale"], true))
    {
        if (count($labels) !== count($values))
        {
            $valid = false;
            $errors[] = "choice_count";
        }
        if (in_array("", $labels, true))
        {
            $valid = false;
            $errors[] = "empty_choice_label";
        }
        if (in_array("", $values, true))
        {
            $valid = false;
            $errors[] = "empty_choice_value";
        }
        if (count($labels) !== count(array_unique($labels, SORT_STRING)))
        {
            $valid = false;
            $errors[] = "duplicate_choice_label";
        }
        if (count($values) !== count(array_unique($values, SORT_STRING)))
        {
            $valid = false;
            $errors[] = "duplicate_choice_value";
        }
        if (count($labels) < 1)
        {
            $valid = false;
            $errors[] = "missing_choices";
        }
        if ($type === "scale")
            foreach ($values as $value)
                if (!is_numeric($value))
                {
                    $valid = false;
                    $errors[] = "scale_non_numeric";
                    break ;
                }
    }
    else
    {
        $labels = [];
        $values = [];
    }

    return (array_merge($definition, [
        "type" => $type,
        "raw_type" => $raw_type,
        "required" => !empty($definition["required"]),
        "choices" => $labels,
        "choice_values" => $values,
        "valid" => $valid,
        "errors" => $errors,
    ]));
}

function form_field_choice_pairs(array $definition)
{
    $definition = form_field_definition($definition);
    $out = [];
    foreach ($definition["choice_values"] as $i => $value)
        $out[] = [
            "value" => (string)$value,
            "label" => (string)($definition["choices"][$i] ?? $value),
        ];
    return ($out);
}

function form_field_choice_label(array $definition, $value)
{
    $value = (string)$value;
    foreach (form_field_choice_pairs($definition) as $choice)
        if ($choice["value"] === $value)
            return ($choice["label"]);
    return ($value);
}

function form_field_normalize_values(array $definition, $raw)
{
    $definition = form_field_definition($definition);
    $type = $definition["type"];
    if ($type === "internship_calendar")
    {
        $entry = is_array($raw) ? (count($raw) ? reset($raw) : "") : ($raw ?? "");
        if (is_array($entry) || is_object($entry))
            return ([]);
        $entry = (string)$entry;
        if (strlen($entry) > 262144)
            return ([]);
        return (trim($entry) === "" ? [] : [$entry]);
    }
    if ($type === "boolean_checkbox")
    {
        $raw = is_array($raw) ? $raw : (($raw === NULL || $raw === "") ? [] : [$raw]);
        foreach ($raw as $entry)
            if (!is_array($entry) && !is_object($entry) && in_array(strtolower(trim((string)$entry)), ["1", "true", "on", "yes"], true))
                return (["1"]);
        return ([]);
    }
    if ($type === "checkbox")
        $raw = is_array($raw) ? $raw : (($raw === NULL || $raw === "") ? [] : [$raw]);
    else
        $raw = is_array($raw) ? (count($raw) ? [reset($raw)] : []) : (($raw === NULL || $raw === "") ? [] : [$raw]);

    $out = [];
    if (in_array($type, ["radio", "checkbox", "scale"], true))
    {
        $allowed = $definition["choice_values"];
        foreach ($raw as $entry)
        {
            if (is_array($entry) || is_object($entry))
                continue ;
            $entry = (string)$entry;
            if (in_array($entry, $allowed, true) && !in_array($entry, $out, true))
                $out[] = $entry;
        }
        if ($type !== "checkbox" && count($out) > 1)
            $out = [reset($out)];
        return ($out);
    }

    $entry = count($raw) && !is_array(reset($raw)) && !is_object(reset($raw)) ? (string)reset($raw) : "";
    if (strlen($entry) > 16384)
        $entry = substr($entry, 0, 16384);
    return (trim($entry) === "" ? [] : [$entry]);
}

function form_field_storage_value(array $definition, $raw)
{
    $definition = form_field_definition($definition);
    $values = form_field_normalize_values($definition, $raw);
    if ($definition["type"] === "checkbox")
        return ($values);
    if ($definition["type"] === "boolean_checkbox")
        return (count($values) ? "1" : "0");
    return (count($values) ? (string)$values[0] : "");
}

function form_field_is_answered(array $definition, $raw)
{
    $definition = form_field_definition($definition);
    if ($definition["type"] === "internship_calendar")
        return (internship_calendar_payload_has_work($raw));
    return (count(form_field_normalize_values($definition, $raw)) > 0);
}

function form_field_missing_required(array $definition, $raw)
{
    $definition = form_field_definition($definition);
    return ($definition["required"] && !form_field_is_answered($definition, $raw));
}

function form_field_display_values(array $definition, $raw)
{
    $definition = form_field_definition($definition);
    $values = form_field_normalize_values($definition, $raw);
    if ($definition["type"] === "internship_calendar")
    {
        $summary = internship_calendar_summary_from_payload($raw);
        return ($summary === "" ? [] : [$summary]);
    }
    if ($definition["type"] === "boolean_checkbox")
        return ([count($values) ? "Oui" : "Non"]);
    if (in_array($definition["type"], ["radio", "checkbox", "scale"], true))
        return (array_map(function($value) use ($definition) {
            return (form_field_choice_label($definition, $value));
        }, $values));
    return ($values);
}

function form_field_h($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}

function form_field_html_attributes(array $attributes)
{
    $out = "";
    foreach ($attributes as $name => $value)
    {
        if ($value === false || $value === NULL)
            continue ;
        $name = preg_replace('/[^A-Za-z0-9_:\-]/', '', (string)$name);
        if ($name === "")
            continue ;
        if ($value === true)
            $out .= " ".$name;
        else
            $out .= " ".$name.'="'.form_field_h($value).'"';
    }
    return ($out);
}

function form_field_render(array $definition, $raw, array $options = [])
{
    $definition = form_field_definition($definition);
    $type = $definition["type"];
    $values = form_field_normalize_values($definition, $raw);
    $name = (string)($options["name"] ?? "");
    $disabled = !empty($options["disabled"]);
    $readonly = !empty($options["readonly"]);
    $base_attributes = is_array($options["attributes"] ?? NULL) ? $options["attributes"] : [];
    if ($definition["required"])
        $base_attributes["aria-required"] = "true";
    if ($disabled)
        $base_attributes["disabled"] = true;

    if ($readonly)
    {
        $display = form_field_display_values($definition, $raw);
        return ('<div class="form-field-readonly-value">'.(count($display) ? form_field_h(implode(", ", $display)) : "—").'</div>');
    }

    if ($type === "internship_calendar")
        return (internship_calendar_render_editor($definition, $raw, $base_attributes));

    if ($type === "boolean_checkbox")
    {
        $attributes = $base_attributes;
        $attributes["type"] = "checkbox";
        if ($name !== "")
            $attributes["name"] = $name;
        $attributes["value"] = "1";
        if (count($values))
            $attributes["checked"] = true;
        return ('<div class="form-field-choices" role="group"><label class="form-field-choice"><input'.form_field_html_attributes($attributes).' /></label></div>');
    }

    if ($type === "textarea")
    {
        if ($name !== "")
            $base_attributes["name"] = $name;
        $base_attributes["rows"] = $options["rows"] ?? 5;
        return ('<textarea'.form_field_html_attributes($base_attributes).'>'.htmlspecialchars((string)($values[0] ?? ""), ENT_NOQUOTES | ENT_SUBSTITUTE, "UTF-8").'</textarea>');
    }

    if (in_array($type, ["radio", "checkbox", "scale"], true))
    {
        $input_type = $type === "checkbox" ? "checkbox" : "radio";
        $out = '<div class="form-field-choices" role="group">';
        foreach (form_field_choice_pairs($definition) as $choice)
        {
            $attributes = $base_attributes;
            $attributes["type"] = $input_type;
            if ($name !== "")
                $attributes["name"] = $name.($type === "checkbox" ? "[]" : "");
            $attributes["value"] = $choice["value"];
            if (in_array($choice["value"], $values, true))
                $attributes["checked"] = true;
            $out .= '<label class="form-field-choice"><input'.form_field_html_attributes($attributes).' /><span>'.form_field_h($choice["label"]).'</span></label>';
        }
        return ($out.'</div>');
    }

    $base_attributes["type"] = "text";
    if ($name !== "")
        $base_attributes["name"] = $name;
    $base_attributes["value"] = (string)($values[0] ?? "");
    return ('<input'.form_field_html_attributes($base_attributes).' />');
}
