<?php

require_once (__DIR__."/../../tools/document_print.php");

$access = am_i_cycle_director()
    || is_director_for_school(-1)
    || am_i_secretariat()
    || am_i_commercial()
    || am_i_accountant()
    || am_i_librarian()
    || document_print_can_access_page();
