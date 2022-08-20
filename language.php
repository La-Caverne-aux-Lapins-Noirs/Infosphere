<?php

if (isset($Dictionnary))
    return ; // @codeCoverageIgnore

$LanguageList= [
    "fr" => "Français",
    "en" => "English"
];
if (!isset($Language))
    $Language = "fr";
if (!isset($LanguageList[$Language]))
    $Language = "fr"; // @codeCoverageIgnore

require_once ("languages/$Language.php");

?>
