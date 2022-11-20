<?php

class ActivityLayer extends Layer
{
    public $LAYER = "ACTIVITY";
    public $id_session = -1;
    public $begin_date = NULL;
    public $end_date = NULL;
    public $subject_appeir_date = NULL;
    public $pickup_date = NULL;
    public $archive = "";
    public $type = -1;
    public $registered = false;
    public $type_type = 2;
    public $subscription = FullActivity::MANUAL_SUBSCRIPTION;
}

