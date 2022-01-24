<?php

$current_module = try_get($_GET, "a", -1);
$display_students = try_get($_GET, "student", "") != "";
$sort = try_get($_GET, "sort", "");
