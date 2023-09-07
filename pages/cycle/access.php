<?php

if ($page == "CycleMenu")
    $access = logged_in();
else
    $access = am_i_cycle_director() || am_i_director();
