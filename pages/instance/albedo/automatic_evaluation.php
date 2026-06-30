<?php
/////////////////////////////////////////////////////////
/// Passage automatique périodique des moulinettes     ///
/////////////////////////////////////////////////////////

if (!isset($albedo) || $albedo != 1)
    return ;

if (!isset($HandOk) || !$HandOk)
    return ;

activity_automatic_evaluation_run_candidates();
