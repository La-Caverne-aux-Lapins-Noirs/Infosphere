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
    public $_CyclesDir;
    public $_SessionsDir;
    public $_OrganizationsDir;
    public $_RoomsDir;
    public $_ConfigurationDir;
    public $_RobotDir;
    public $_DocDir;
    public $_BookDir;
    public $_QuizDir;
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

    function QuizDir($school = NULL, $relative = NULL)
    {
        if ($school === NULL)
            return ($this->_QuizDir);
        $school = trim((string)$school);
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $school))
            return (NULL);
        $dir = $this->_QuizDir.$school."/";
        $new_school_tree = !is_dir($dir);
        if ($new_school_tree)
            @mkdir($dir, 0750, true);
        // Conventional starting points only: seed them when the school's tree
        // is first created, then leave the user free to reorganize it.
        if ($new_school_tree)
            foreach (["quiz", "rubrics"] as $subdir)
                if (!is_dir($dir.$subdir))
                    @mkdir($dir.$subdir, 0750, true);
        if ($relative === NULL || trim((string)$relative, "/") === "")
            return ($dir);
        $relative = trim(str_replace("\\", "/", (string)$relative), "/");
        if (strpos($relative, "../") !== false || substr($relative, 0, 3) === "../" || strpos($relative, "/..") !== false)
            return (NULL);
        return ($dir.$relative."/");
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
	// La racine utilisateur n'est qu'un conteneur. Garantir les trois
	// espaces explicites même pour les comptes créés avant cette organisation.
	foreach ([
	    "public",
	    "perso",
	    "admin",
	    "admin/subscription",
	    "admin/subscription/diplomas",
	    "admin/subscription/school_reports",
	    "admin/subscription/identity",
	    "admin/subscription/residence",
	    "admin/paid_invoices",
	    "admin/invoices_to_pay",
	    "admin/deleted_invoices",
	    "admin/school_reports"
	] as $subdir)
	    if (!is_dir($dir.$subdir))
		new_directory($dir.$subdir."/index.php");
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
    function CyclesDir($cycle = NULL)
    {
        if ($cycle == NULL)
            return ($this->_CyclesDir);
        $dir = $this->_CyclesDir.$cycle."/";
        if (!is_dir($dir))
            new_directory($dir."index.php");
        return ($dir);
    }
    function SessionsDir($id_session = NULL)
    {
        if ($id_session === NULL)
            return ($this->_SessionsDir);
        $id_session = (int)$id_session;
        if ($id_session <= 0)
            return (NULL);
        return ($this->_SessionsDir.$id_session."/");
    }
    function OrganizationsDir($organization = NULL)
    {
	if ($organization == NULL)
	    return ($this->_OrganizationsDir);
	return ($this->_OrganizationsDir.$organization."/");
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
	// Les fichiers propres à un cycle (documents collectifs, exports, etc.).
	$this->_CyclesDir = "$DIR/cycle/";
	$this->_SessionsDir = "$DIR/session/";
	// Les fichiers associés aux organisations mutualisées
	$this->_OrganizationsDir = "$DIR/organization/";
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
        // Reusable Dabsic questionnaires, rubrics and their auxiliary assets.
        $this->_QuizDir = "$DIR/quiz/";

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
