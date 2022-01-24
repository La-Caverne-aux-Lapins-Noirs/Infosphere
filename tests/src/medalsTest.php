<?php

require_once ("xtestcase.php");
require_once ("../tools/index.php");

class MedalsTest extends XTestCase
{
    public function testHandleRequest()
    {
	global $Configuration;
	global $LanguageList;
	global $ErrorMsg;
	global $_POST;
	global $_GET;
	global $_FILES;
	global $User;
	global $Database;

	clear_table("user");
	clear_table("medal");
	clear_table("activity");
	clear_table("activity_medal");
	$this->AddRealUser();

	$_POST = [];
	$_POST["action"] = "add";
	$_POST["medal"] = "new_medal";
	$_POST["fr_name"] = "nom francais";
	$_POST["fr_description"] = "description francaise";
	copy("./res/medal.png", "./medal.png");
	$_FILES["icon"]["tmp_name"] = "./medal.png";
	$_FILES["icon"]["name"] = $_FILES["icon"]["tmp_name"];
	$request = "";
	$LogMsg = "";
	require ("../pages/medals/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "MedalAdded");
	$med = db_select_one("* FROM medal");
	$this->assertSame($med["id"], 1);
	$this->assertSame($med["codename"], "new_medal");
	$this->assertSame($med["fr_name"], "nom francais");
	$this->assertSame($med["fr_description"], "description francaise");
	$this->assertSame($med["icon"], "./dres/medals/new_medal.png");

	$_POST = [];
	$_POST["action"] = "update";
	$_POST["medal"] = "new_medal";
	$_POST["lang"] = "fr";
	$_POST["fr_name"] = "nouveau nom francais";
	$_POST["fr_description"] = "nouvelle description francaise";
	copy("./res/medal.png", "./medal.png");
	$_FILES["icon"]["tmp_name"] = "./medal.png";
	$_FILES["icon"]["name"] = $_FILES["icon"]["tmp_name"];
	$request = "";
	$LogMsg = "";
	require ("../pages/medals/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "MedalUpdated");
	$med = db_select_one("* FROM medal");
	$this->assertSame($med["id"], 1);
	$this->assertSame($med["codename"], "new_medal");
	$this->assertSame($med["fr_name"], "nouveau nom francais");
	$this->assertSame($med["fr_description"], "nouvelle description francaise");
	$this->assertSame($med["icon"], "./dres/medals/new_medal.png");

	$_POST = [];
	$_POST["action"] = "update";
	$_POST["medal"] = "new_medal";
	$_POST["lang"] = "fr";
	$_POST["fr_name"] = "le nom";
	unset($_FILES);
	$request = "";
	$LogMsg = "";
	require ("../pages/medals/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "MedalUpdated");
	$med = db_select_one("* FROM medal");
	$this->assertSame($med["id"], 1);
	$this->assertSame($med["codename"], "new_medal");
	$this->assertSame($med["fr_name"], "le nom");
	$this->assertSame($med["fr_description"], "nouvelle description francaise"); // ce champ devrait etre inchangé
	$this->assertSame($med["icon"], "./dres/medals/new_medal.png"); // pareil

	$_POST = [];
	$_POST["action"] = "delete";
	$_POST["medal"] = "new_medal";
	$request = "";
	$LogMsg = "";
	require ("../pages/medals/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "MedalDeleted");
	$med = db_select_one("* FROM medal");
	$this->assertSame($med["deleted"], 1);
	$ret = fetch_data("medal")->value;
	$this->assertSame(count($ret), 0);

	$_POST["action"] = "delete";
	$_POST["medal"] = "blarf";
	$request = "";
	$LogMsg = "";
	require ("../pages/medals/handle_request.php");
	$this->assertSame($request->label, "BadCodeName");
	$this->assertSame($request->details, "blarf");
	$this->assertSame($LogMsg, "MedalDeleted");

	$_POST["action"] = "add";
	$_POST["medal"] = "new_medal";
	$_POST["fr_name"] = "nom francais";
	$_POST["fr_description"] = "description francaise";
	copy("./res/medal.png", "./medal.png");
	$_FILES["icon"]["tmp_name"] = "./medal.png";
	$_FILES["icon"]["name"] = $_FILES["icon"]["tmp_name"];
	$request = "";
	$LogMsg = "";
	require ("../pages/medals/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "MedalAdded");
	$med = db_select_one("* FROM medal WHERE id = 2");
	$this->assertSame($med["id"], 2);
	$this->assertSame($med["codename"], "new_medal");
	$this->assertSame($med["fr_name"], "nom francais");

	$Database->query("INSERT INTO activity (type, codename) VALUES (0, 'activity')");

	$_POST["action"] = "link";
	$_POST["medal"] = "new_medal";
	$_POST["activity"] = "activity";
	$request = "";
	$LogMsg = "";
	require ("../pages/medals/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "LinkEstablished");
	$med = db_select_all("* FROM activity_medal WHERE id_activity = 1 AND id_medal = 2");
	$this->assertSame(count($med), 1);

	$_GET["a"] = "new_medal";
	require ("../pages/medals/handle_parameters.php");
	$this->assertSame($fetch->value["name"], "nom francais");
	$this->assertSame($fetch->value["activity"][0]["codename"], "activity");

	unset($_GET);
	require ("../pages/medals/handle_parameters.php");
	$this->assertSame($fetch->value[0]["codename"], "new_medal");

	$ret = fetch_activities_for_medal("!");
	$this->assertSame($ret->is_error(), true);

	$ret = fetch_activities_for_medal("new_medal");
	$ret = $ret->value;
	$this->assertSame($ret["codename"], "new_medal");
	$this->assertSame($ret["activity"][0]["codename"], "activity");

    	$_POST["action"] = "unlink";
	$_POST["medal"] = "new_medal";
	$_POST["activity"] = "activity";
	$request = "";
	$LogMsg = "";
	require ("../pages/medals/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "LinkRemoved");
	$med = db_select_all("* FROM activity_medal WHERE id_activity = 1 AND id_medal = 2");
	$this->assertSame(count($med), 0);

	$ret = fetch_activities_for_medal("new_medal");
	$ret = $ret->value;
	$this->assertSame(count($ret["activity"]), 0);
    }
}

