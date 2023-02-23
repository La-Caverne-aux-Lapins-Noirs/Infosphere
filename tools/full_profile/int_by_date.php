<?php

class IntByDate
{
    public $cumulated = 0;
    public $since = 0;
    public $last = 0;
    public $values = []; // timestamp => (date, value)

    public function __construct()
    {}

    public function add($date, $val)
    {
	$tstamp = day_to_timestamp($date);
	if ($tstamp < $this->since)
	    $this->since = $tstamp;
	if ($tstamp > $this->last)
	    $this->last = $tstamp;
	$this->values[$tstamp]["date"] = $date;
	if (!isset($this->values[$tstamp]["value"]))
	    $this->values[$tstamp]["value"] = 0;
	$this->values[$tstamp]["value"] += $val;
	$this->cumulated += $val;
    }

    public function get()
    {
	return ($this->cumulated);
    }
    
    function __toString()
    {
	return ((string)$this->cumulated);
    }
}
