<?php

class CConfiguration
{
    public $_MedalsDir;
    public $_GroupsDir;
    public $ELearningDir;
    public $_UsersDir;
    public $_ActivitiesDir;
    public $_SchoolsDir;
    public $_RoomsDir;
    public $Properties = [];

    function GroupsDir($grp = NULL)
    {
	if ($grp == NULL)
	    return ($this->_GroupsDir);
	return ($this->_GroupsDir.$grp."/");
    }
    
    function UsersDir($usr = NULL)
    {
	if ($usr == NULL)
	    return ($this->_UsersDir);
	$dir = $this->_UsersDir.$usr."/";
	if (!is_dir($dir))
	{
	    new_directory($dir."public/index.php");
	    new_directory($dir."www/index.php");
	    new_directory($dir."personnal/index.php");
	    new_directory($dir."admin/index.php");
	}
	return ($dir);
    }
    function MedalsDir($medal = NULL)
    {
	if ($medal == NULL)
	    return ($this->_MedalsDir);
	return ($this->_MedalsDir.$medal."/");
    }
    function SchoolsDir($school = NULL)
    {
	if ($school == NULL)
	    return ($this->_SchoolsDir);
	return ($this->_SchoolsDir.$school."/");
    }
    function RoomsDir($room = NULL)
    {
	if ($room == NULL)
	    return ($this->_RoomsDir);
	return ($this->_RoomsDir.$room."/");
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
	$this->_GroupsDir = "$DIR/groups/";
	$this->ELearningDir = "$DIR/elearning/";
	$this->_UsersDir = "$DIR/users/";
	$this->_SchoolsDir = "$DIR/school/";
	$this->_RoomsDir = "$DIR/room/";
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
	if (!is_dir($this->MedalsDir(".ressources")))
	    new_directory($this->MedalsDir(".ressources"));
	$tmp = db_select_all("* FROM configuration");
	foreach ($tmp as $v)
	    $this->Properties[$v["codename"]] = $v["value"];
    }
}

$Configuration = new CConfiguration;
