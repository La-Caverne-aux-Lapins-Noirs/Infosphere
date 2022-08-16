<?php

class CConfiguration
{
    public $_MedalsDir;
    public $GroupsDir;
    public $ELearningDir;
    public $_UsersDir;
    public $_ActivitiesDir;
    public $SchoolsDir;
    public $Properties = [];

    function UsersDir($usr = NULL)
    {
	if ($usr == NULL)
	    return ($this->_UsersDir);
	return ($this->_UsersDir.$usr."/");
    }
    function MedalsDir($medal = NULL)
    {
	if ($medal == NULL)
	    return ($this->_MedalsDir);
	return ($this->_MedalsDir.$medal."/");
    }
    function ActivitiesDir($act = NULL, $lng = NULL)
    {
	global $Language;

	if ($lng === NULL)
	    $lng = $Language;
	if ($lng !== "")
	    $lng .= "/";
	if ($act == NULL)
	    return ($this->_ActivitiesDir);
	return ($this->_ActivitiesDir.$act."/$lng");
    }
    
    function __construct()
    {
	$DIR = "dres";
	$this->_MedalsDir = "$DIR/medals/";
	$this->GroupsDir = "$DIR/groups/";
	$this->ELearningDir = "$DIR/elearning/";
	$this->_UsersDir = "$DIR/users/";
	$this->SchoolsDir = "$DIR/schools/";
	$this->_ActivitiesDir = "$DIR/activity/";

	foreach ($this as $k => $v)
	{
	    if ($v == $this->Properties)
		continue ;
	    if (substr($v, -1) != "/")
		$this->$k .= "/"; // @codeCoverageIgnore
	    if (!is_dir($v))
		new_directory($v);
	}
	$tmp = db_select_all("* FROM configuration");
	foreach ($tmp as $v)
	    $this->Properties[$v["codename"]] = $v["value"];
    }
}

$Configuration = new CConfiguration;
