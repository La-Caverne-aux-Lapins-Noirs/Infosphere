<?php


class Layer extends ArrayObject
{
    public $id = -1;
    public $codename = "";
    public $template_codename = "";
    public $name = "";
    public $description = "";
    public $credit = 0;
    public $acquired_credit = 0;
    public $hidden = false;

    public $sublayer = [];
    public $medal = [];
    public $note = false;

    public $work = NULL;
    public $nowork = NULL;
    public $present = NULL;
    public $late = NULL;
    public $missing = NULL;
    public $cumulated_late = NULL; // En seconde

    public $is_teacher = false;
    public $is_assistant = false;
    public $registered = false;
    public $closed = false;

    public $commentaries = "";

    public function __construct()
    {
	$this->work = new IntByDate;
	$this->nowork = new IntByDate;
	$this->present = new IntByDate;
	$this->late = new IntByDate;
	$this->missing = new IntByDate;
	$this->cumulated_late = new IntByDate;
    }
    public function __get($n)
    {
	if (!isset($this->$n))
	{
	    if (!isset($this[$n]))
	    {
		AddDebugLogR("$n does not exists");
		backtrack();
		return (NULL);
	    }
	    return ($this[$n]);
	}
	return ($this->$n);
    }

    function retrieve_one(&$l, $r)
    {
	if ($r == NULL)
	    return ;
	if ($l == NULL)
	    $l = new IntByDate;
	foreach ($r->values as $v)
	{
	    $l->add($v["date"], $v["value"]);
	}
    }

    public function retrieve()
    {
	global $Language;
	global $Configuration;

	if ($this->LAYER == "ACTIVITY")
	    return ;
	$fields = [
	    "work",
	    "nowork",
	    "present",
	    "late",
	    "missing",
	    "cumulated_late"
	];
	foreach ($this->sublayer as $sub)
	{
	    $sub->retrieve();
	}
	foreach ($this->sublayer as $sub)
	{
	    foreach ($fields as $f)
	    {
		$this->retrieve_one($this->$f, $sub->$f);
	    }
	    $note = 0;
	    $nbr_note = 0;
	    foreach ($sub->medal as $med)
	    {
		if (!isset($med["codename"]))
		    continue ;
		if (!isset($this->medal[$med["codename"]]))
		    $this->medal[$med["codename"]] = $med;
		else
		{
		    $this->medal[$med["codename"]]["success"] += $med["success"];
		    $this->medal[$med["codename"]]["success_list"] = array_merge($med["success_list"], $this->medal[$med["codename"]]["success_list"]);
		    $this->medal[$med["codename"]]["failure"] += $med["failure"];
		    $this->medal[$med["codename"]]["failure_list"] = array_merge($med["failure_list"], $this->medal[$med["codename"]]["failure_list"]);
		    $this->medal[$med["codename"]]["local_sum"] += $med["local_sum"];
		    if (isset($med["strength"]))
			if (!isset($this->medal[$med["codename"]]["strength"]) || $this->medal[$med["codename"]]["strength"] < $med["strength"])
			    $this->medal[$med["codename"]]["strength"] = $med["strength"];
			
		}
		if (is_note($med["codename"]) && $med["success"] > 0)
		{
		    $sub->note = true;
		    $note += intval(substr($med["codename"], 5));
		    $nbr_note += 1;
		}
	    }
	    if ($nbr_note > 0)
	    {
		$this->note = true;
		$med = "token".sprintf("%02d", round($note / $nbr_note));
		if (!isset($this->medal[$med]))
		    $this->medal[$med] = [];
		$this->medal[$med] = array_merge($this->medal[$med], db_select_one("
                   *,
                   {$Language}_name as name,
                   {$Language}_description as description
                   FROM medal
                   WHERE codename = '$med'
		"));
		$this->medal[$med]["type"] = 0;
		$this->medal[$med]["local"] = true;
		$this->medal[$med]["module_medal"] = true;
		$this->medal[$med]["success"] = 1;
		$this->medal[$med]["success_list"] = [];
		$this->medal[$med]["failure"] = 0;
		$this->medal[$med]["failure_list"] = [];
		$this->medal[$med]["local_sum"] = 0;
		
		$this->medal[$med]["icon"] = $Configuration->MedalsDir($this->medal[$med]["codename"])."icon.png";
		if (!file_exists($this->medal[$med]["icon"]))
		    $this->medal[$med]["icon"] = NULL;
		$this->medal[$med]["band"] = $Configuration->MedalsDir($this->medal[$med]["codename"])."band.png";
		if (!file_exists($this->medal[$med]["band"]))
		    $this->medal[$med]["band"] = NULL;
	    }
	}
    }
}
