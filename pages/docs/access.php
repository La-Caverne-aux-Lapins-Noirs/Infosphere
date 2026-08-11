<?php

$access = am_i_cycle_director() || is_director_for_school(-1) || is_secretariat() || is_commercial();
