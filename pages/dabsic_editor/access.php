<?php

require_once (__DIR__."/../../tools/questionnaire.php");
require_once (__DIR__."/../../tools/correction_catalog.php");
$access = dabsic_editor_user_can_access($_GET["file"] ?? "", true);
