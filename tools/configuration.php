<?php

class CConfiguration
{
    public $_MedalsDir;
    public $_GroupsDir;
    public $_SupportDir;
    public $_UsersDir;
    public $_tmpFileDir;
    public $_ActivitiesDir;
    public $_SchoolsDir;
    public $_RoomsDir;
    public $_ConfigurationDir;
    public $_RobotDir;
    public $_DocDir;
    public $_BookDir;
    public $Properties = [];

    function _ConfigurationDir($cnf = NULL)
    {
	if ($cnf == NULL)
	    return ($this->_ConfigurationDir);
	return ($this->_ConfigurationDir.$cnf."/");
    }

    function RobotDir($cnf = NULL)
    {
	if ($cnf == NULL)
	    return ($this->_RobotDir);
	return ($this->_RobotDir.$cnf."/");
    }

    function DocDir($cnf = NULL)
    {
	if ($cnf == NULL)
	    return ($this->_DocDir);
	return ($this->_DocDir.$cnf."/");
    }

    function BookDir($cnf = NULL)
    {
	if ($cnf == NULL)
	    return ($this->_BookDir);
	return ($this->_BookDir.$cnf.".pdf");
    }

    function GroupsDir($grp = NULL)
    {
	if ($grp == NULL)
	    return ($this->_GroupsDir);
	return ($this->_GroupsDir.$grp."/");
    }

    function SupportDir($cat = NULL, $dom = NULL, $asset = NULL, $lng = NULL)
    {
	global $Language;
	
	if ($cat == NULL)
	    $target = $this->_SupportDir;
	else if ($dom == NULL)
	    $target = $this->_SupportDir.$cat."/";
	else if ($asset == NULL)
	    $target = $this->_SupportDir.$cat."/".$dom."/";
	else
	{
	    if ($lng === NULL)
		$lng = $Language;
	    if ($lng !== "")
		$lng .= "/";
	    $target = $this->_SupportDir.$cat."/".$dom."/".$lng.$asset;
	}
	if (!is_dir($target))
	    new_directory($target);
	return ($target);
    }
    
    function UsersDir($usr = NULL)
    {
	if ($usr == NULL)
	    return ($this->_UsersDir);
	$dir = $this->_UsersDir.$usr."/";
	if (!is_dir($dir))
	{
	    new_directory($dir."public/index.php");
	    new_directory($dir."admin/index.php");
	    new_directory($dir."admin/subscription/index.php");
	    new_directory($dir."admin/subscription/diplomas/index.php");
	    new_directory($dir."admin/subscription/school_reports/index.php");
	    new_directory($dir."admin/subscription/identity/index.php");
	    new_directory($dir."admin/subscription/residence/index.php");
	    new_directory($dir."admin/paid_invoices/index.php");
	    new_directory($dir."admin/invoices_to_pay/index.php");
	    new_directory($dir."admin/deleted_invoices/index.php");
	    new_directory($dir."admin/school_reports/index.php");
	}
	return ($dir);
    }
    
    function TmpDir($usr = NULL)
    {
	return (UsersDir($usr)."trace/");
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
	// Les fichiers des médailles: leurs images
	$this->_MedalsDir = "$DIR/medals/";
	// Les fichiers associés au groupes, c'est à dire les logos
	// ainsi que les fichiers librement manipulés qui devraient
	// être chiffrés
	$this->_GroupsDir = "$DIR/groups/";
	// Les fichiers associés aux supports de cours présents sur
	// l'infosphere
	$this->_SupportDir = "$DIR/support/";
	// Les fichiers utilisateurs, y compris les bulletins, les
	// fichiers persos et administratifs
	// Ces fichiers devraient être chiffré et déchiffré à la volée
	$this->_UsersDir = "$DIR/users/";
	// Les fichiers associés aux écoles, c'est à dire principalement
	// leurs logos et documents administratifs
	$this->_SchoolsDir = "$DIR/school/";
	// Les fichiers associés aux salles, c'est à dire leur images
	$this->_RoomsDir = "$DIR/room/";
	// Les fichiers associés aux activités
	$this->_ActivitiesDir = "$DIR/activity/";
	// Bibliothèque de configuration servant aux exercices
	// Mais n'étant pas forcement auto suffisant pour composer
	// des activités.
	$this->_ConfigurationDir = "$DIR/configuration/";
	// Les bibliothèques dynamiques utilisées dans le cas
	// de tests de programmes compilés de l'intérieur.
	// Ces programmes doivent être encodé afin de rendre
	// impossible leur execution en l'état, par sécurité.
	$this->_RobotDir = "$DIR/robot/";
	$this->_DocDir = "$DIR/doc/";
	$this->_BookDir = "$DIR/book/";

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
	$prop = [];
	$this->Properties = db_select_all("* FROM configuration", "codename");
	foreach ($this->Properties as $i => $v)
	{
	    if ($v["secured"])
		$prop[$v["codename"]] = unsecure_data($v["value"]);
	    else
		$prop[$v["codename"]] = $v["value"];
	}
	$this->Properties = $prop;
    }
}

$Configuration = new CConfiguration;
