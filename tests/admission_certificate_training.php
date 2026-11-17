<?php

// Standalone regression checks; no database or document engine is required.
require_once (__DIR__."/../tools/response.php");
require_once (__DIR__."/../tools/admission_certificate.php");

$Dictionnary = ["InvalidParameter" => "Paramètre invalide"];
$NoLocalisation = new DateTimeZone("Europe/Paris");

$empty = admission_certificate_form_training_options([
    "Admission.CourseTitle" => "   ",
    "Admission.EntryDate" => "",
    "Admission.EndDate" => "",
]);
if ($empty->is_error() || $empty->value !== [])
    throw new RuntimeException("Blank details must preserve the historical certificate and automatic entry date");

$text = "Cours, projets & recherche : 100 % en présentiel.\nPrécisions supplémentaires à la demande.";
$completed = admission_certificate_form_training_options([
    "Admission.CourseTitle" => "première année du Bachelor en informatique",
    "Admission.TrainingRegime" => "formation initiale, hors alternance",
    "Admission.StudyPace" => "temps plein",
    "Admission.TrainingDuration" => "une année de formation",
    "Admission.TeachingMode" => "en présentiel au sein de l'établissement",
    "Admission.AdditionalInformation" => $text,
    "Admission.EntryDate" => "04/01/2027",
    // Synthetic end date for this test, not a school-calendar default.
    "Admission.EndDate" => "2028-01-03",
    "Admission.PaymentState" => "full",
    "Signatories.Director.Identity" => "Must not override the signatory",
]);
if ($completed->is_error() || count($completed->value) !== 8 ||
    $completed->value["course_title"] !== "première année du Bachelor en informatique" ||
    $completed->value["training_regime"] !== "formation initiale, hors alternance" ||
    $completed->value["study_pace"] !== "temps plein" ||
    $completed->value["training_duration"] !== "une année de formation" ||
    $completed->value["teaching_mode"] !== "en présentiel au sein de l'établissement" ||
    $completed->value["additional_information"] !== $text ||
    $completed->value["entry_date"] !== "2027-01-04" ||
    $completed->value["end_date"] !== "2028-01-03")
    throw new RuntimeException("Training details must survive form extraction without accepting unrelated overrides");

$leap = admission_certificate_form_training_options(["Admission.EndDate" => "29/02/2028"]);
if ($leap->is_error() || $leap->value["end_date"] !== "2028-02-29")
    throw new RuntimeException("Valid leap days must be accepted");

foreach (["29/02/2027", "31/04/2027", "2027-02-30", "1/2/2027", "tomorrow", "04/01/2027\0"] as $date)
    foreach (["Admission.EntryDate", "Admission.EndDate"] as $field)
        if (!admission_certificate_form_training_options([$field => $date])->is_error())
            throw new RuntimeException("Malformed or impossible dates must be rejected: ".$field);

if (!admission_certificate_form_training_options(["Admission.AdditionalInformation" => ["unexpected"]])->is_error())
    throw new RuntimeException("Structured values must not be silently cast to text");

foreach ([
    [[], 0, 0],
    [["Admission.AutomaticSignature" => "1"], 1, 0],
    [["Admission.ShowStamp" => "1"], 0, 1],
    [["Admission.AutomaticSignature" => "1", "Admission.ShowStamp" => "1"], 1, 1],
    [["Admission.AutomaticSignature" => "false", "Admission.ShowStamp" => "0"], 0, 0],
] as [$values, $signature, $stamp])
{
    $options = admission_certificate_form_signature_options($values);
    if ($options->is_error() || $options->value !== ["automatic_signature" => $signature, "show_stamp" => $stamp])
        throw new RuntimeException("Signature and stamp choices must remain independent and disabled by default");
}
foreach (["unexpected", ["1"]] as $invalid)
    if (!admission_certificate_form_signature_options(["Admission.ShowStamp" => $invalid])->is_error())
        throw new RuntimeException("Invalid stamp choices must be rejected");

echo "admission_certificate_training: OK\n";
