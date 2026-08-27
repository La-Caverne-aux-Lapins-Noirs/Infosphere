<?php

require_once (__DIR__."/../../tools/quiz_attempt.php");
$access = quiz_attempt_can_view((int)($_GET["a"] ?? 0));
