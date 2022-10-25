<?php

global $OriginalUser;

$access = is_admin() || ($OriginalUser != NULL && $OriginalUser["authority"] >= ADMINISTRATOR);
//$access = logged_in();
