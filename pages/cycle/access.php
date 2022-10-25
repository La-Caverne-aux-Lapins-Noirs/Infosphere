<?php

if ($page == "CycleMenu")
    $access = logged_in();
else
    $access = is_director();
