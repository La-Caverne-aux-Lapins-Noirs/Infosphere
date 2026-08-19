<?php

require_once (__DIR__."/../../tools/dabsic_form.php");

$access = dabsic_form_user_can_access_output($_GET["output"] ?? "");
