<?php

require_once (__DIR__."/../tools/generate_subject.php");

$row = [
    "fr_name" => "Nom direct",
    "template_fr_name" => "Nom du modèle",
    "template_link" => 1,
];
assert(subject_context_localized_value($row, "name", "FR") === "Nom direct");

$row["fr_name"] = "";
assert(subject_context_localized_value($row, "name", "FR") === "Nom du modèle");
$row["template_link"] = 0;
assert(subject_context_localized_value($row, "name", "FR") === "");

assert(subject_context_pick_school([1, 2], [2, 3]) === 2);
assert(subject_context_pick_school([5], []) === 5);
assert(subject_context_pick_school([1, 2], []) === 0);

echo "subject context tests: ok\n";
