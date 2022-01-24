<?php

if (!isset($ConfigurationFile))
    $ConfigurationFile = "./infosphere.dab";

class CConfiguration
{
    public $MedalsDir;
    public $GroupsDir;
    public $ELearningDir;
    public $UsersDir;
    public $Properties = [];

    function __construct()
    {
	global $ConfigurationFile;

	if (UNIT_TEST)
	    $DIR = "./dres";
	else
	    $DIR = "/etc/technocore"; // @codeCoverageIgnore
	$this->MedalsDir = "$DIR/medals/";
	$this->GroupsDir = "$DIR/groups/";
	$this->ELearningDir = "$DIR/elearning/";
	$this->UsersDir = "$DIR/users/";

	if (!UNIT_TEST && file_exists("/etc/technocore/infosphere.dab"))
	    $cnf = json_decode(shell_exec("mergeconf -i /etc/technocore/infosphere.dab -of .json"), true); // @codeCoverageIgnore
	else if (file_exists($ConfigurationFile))
	    $cnf = json_decode(shell_exec("mergeconf -i $ConfigurationFile -of .json"), true);
	if (isset($cnf["Directories"]) && $cnf != NULL)
	{
	    $cnf = $cnf["Directories"];
	    $this->MedalsDir = $cnf["Medals"];
	    $this->GroupsDir = $cnf["Groups"];
	    $this->ELearningDir = $cnf["ELearning"];
	    $this->UsersDir = $cnf["Users"];
	}
	foreach ($this as $v)
	{
	    if ($v == $this->Properties)
		continue ;
	    if (substr($v, -1) != "/")
		$this->$k .= "/"; // @codeCoverageIgnore
	    if (!is_dir($v))
	    {
		mkdir($v, 0775, true);
		system("touch {$v}index.htm");
	    }
	}
	$this->Properties = db_select_all("* FROM configuration", "codename");
    }
}

$Configuration = new CConfiguration;
