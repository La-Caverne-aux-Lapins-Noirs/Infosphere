<?php

class Response
{
    public $label = "";
    public $details = "";
    public $recommandation = "";
    public $build_location = "";
    public $value = "";

    function __toString()
    {
	return ("");
    }

    function is_error()
    {
	return (false);
    }
}

class ErrorResponse extends Response
{
    function __construct($label = "", $details = "", $recommandation = "") // Example: Unknown Login, Yyrkoon
    {
	global $Dictionnary;

	if (!isset($label) || $label == "")
	    return ;
	if (!isset($Dictionnary[$this->label = $label]))
	    throw new ErrorException("Invalid error label");
	if (isset($details))
	    $this->details = $details;
	if ($recommandation != "")
	    $this->recommandation = $recommandation;

	// Sera affiché seulement par un print_r situé dans les tests unitaires.
	$this->build_location = "";
	$tmp = debug_backtrace();
	for ($i = 0; isset($tmp[$i]); ++$i)
	    $this->build_location .= "\n".$tmp[$i]["file"].": ".$tmp[$i]["line"];
	$this->build_location .= "\n";
    }

    function __toString()
    {
	global $Dictionnary;
	global $BaseDir;

	if ($this->label == "")
	    return ("");
	if ($this->details == "")
	    return ($Dictionnary[$this->label]);
	if (is_array($this->details))
	    $t = implode(", ", $this->details);
	else
	    $t = $this->details;
	if ($BaseDir == "")
	    $t = str_replace("\n", "<br />", $t);
	if ($this->recommandation != "")
	{
	    if (isset($Dictionnary[$this->recommandation]))
		$t .= " (".$Dictionnary[$this->recommandation].")";
	    else
		$t .= " (".$this->recommandation.")";
	}
	if ($BaseDir == "")
	    $t .= '<span style="display: none;">'.$this->build_location.'</span>';
	/*
	   else
	   $t .= $this->build_location;
	 */
	return ($Dictionnary[$this->label].": $t");
    }

    function is_error()
    {
	return (true);
    }
}

class InfoResponse extends ErrorResponse
{
    function __construct($label = "", $details = "", $recommandation = "") // Example: Unknown Login, Yyrkoon
    {
	parent::__construct($label, $details, $recommandation);
    }
}

class ValueResponse extends Response
{
    function __construct($value = NULL)
    {
	$this->value = $value;
    }
}

