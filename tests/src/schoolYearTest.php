<?php

require_once ("xtestcase.php");
require_once ("../pages/school_year/fetch_school_year.php");
require_once ("../pages/school_year/add_school_year.php");
require_once ("../pages/school_year/export_promotion.php");

class SchoolYearTest extends XTestCase
{
    public function testExportPromotion()
    {
	global $ErrorMsg;
	global $_POST;
	global $_FILES;
	global $User;

	clear_table("user");
	clear_table("school_year");
	clear_table("user_school_year");
	$this->AddRealUser();

	$_POST["action"] = "import";
	$_FILES["userfile"]["name"] = "./res/import_school_year.json";
	$_FILES["userfile"]["tmp_name"] = $_FILES["userfile"]["name"];
	$request = "";
	$LogMsg = "";
	require ("../pages/school_year/handle_request.php");
	$this->assertFalse($request->is_error());
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "ImportSuccess");

	$out = export_promotion(-1, "json");
	$this->assertFalse($out->is_error());
	$out = json_decode($out->value, true);
	$cur = file_get_contents("./res/import_school_year.json");
	$cur = json_decode($cur, true);
	$this->assertTrue(compare_tree($out, $cur, true));

	$out = export_promotion(1, "json");
	$this->assertSame($out->is_error(), false);
	$out = json_decode($out->value, true);
	unset($cur["SecondCycle"]);
	$this->assertTrue(compare_tree($out, $cur, true));
    }

    public function testHandleParameters()
    {
	global $ErrorMsg;
	global $_GET;

	clear_table("user");
	clear_table("school_year");
	clear_table("user_school_year");

	$_GET["a"] = "abc";
	$_GET["b"] = "";
	$fetch = NULL;
	$sort = NULL;
	require ("../pages/school_year/handle_parameters.php");
	$this->assertSame($fetch->label, "BadCodeName");
	$this->assertSame($sort, false);

	$_GET["a"] = -42;
	$_GET["b"] = "true";
	$fetch = NULL;
	$sort = NULL;
	require ("../pages/school_year/handle_parameters.php");
	$this->assertSame($fetch->label, "NotAnId");
	$this->assertSame($sort, true);

	add_school_year("codename", 20, "1/2/2020");
	$_GET["a"] = 1;
	$_GET["b"] = "false";
	$fetch = NULL;
	$sort = NULL;
	require ("../pages/school_year/handle_parameters.php");
	$this->assertSame($fetch->value["cycle"], 20);
	$this->assertSame($sort, true);

	unset($_GET["a"]);
	unset($_GET["b"]);
	$fetch = NULL;
	$sort = 0;
	require ("../pages/school_year/handle_parameters.php");
	$this->assertSame($fetch->value[0]["codename"], "codename");
	$this->assertSame($sort, false);

	$_GET["b"] = "true";
	$fetch = NULL;
	$sort = 0;
	require ("../pages/school_year/handle_parameters.php");
	$this->assertSame($fetch->value["codename"]["codename"], "codename");
	$this->assertSame($sort, true);
    }

    public function testAddSchoolYear()
    {
	global $User;
	global $_POST;
	global $request;
	global $LogMsg;

	clear_table("school_year");
	$ret = @add_school_year($lel, 1, "1/2/2002");
	$this->assertSame($ret->label, "MissingCodeName");
	$this->assertSame($ret->details, "");

	$ret = @add_school_year("codename", 42, "1/2/2002");
	$this->assertSame($ret->label, "InvalidCycleNumber");
	$this->assertSame($ret->details, 42);

	$ret = @add_school_year("codename", $lel, "1/2");
	$this->assertSame($ret->label, "InvalidCycleNumber");
	$this->assertSame($ret->details, "");

	$ret = @add_school_year("codename", 20, $lel);
	$this->assertSame($ret->label, "InvalidDate");
	$this->assertSame($ret->details, "");

	$ret = @add_school_year("codename", 20, "1/2");
	$this->assertSame($ret->label, "InvalidDate");
	$this->assertSame($ret->details, "1/2");
    }

    public function testInvalidRequest()
    {
	global $User;
	global $_POST;
	global $request;
	global $LogMsg;

	$_POST["action"] = "lel";
	$_POST["x"] = 42;
	$request = new Response;
	$LogMsg = "X";
	require ("../pages/school_year/handle_request.php");
	$this->assertFalse($request->is_error());
	$this->assertSame($LogMsg, "");

	$_POST["action"] = "lel";
	$_POST["x"] = 42;
	$request = new ErrorResponse("NotAnId", "Lel");
	$LogMsg = "X";
	require ("../pages/school_year/handle_request.php");
	$this->assertSame($request->label, "NotAnId");
	$this->assertSame($request->details, "Lel");
	$this->assertSame($LogMsg, "");
    }

    public function testRequestImport()
    {
	global $DBSelect;
	global $User;
	global $request;
	global $LogMsg;

	clear_table("user");
	clear_table("school_year");
	clear_table("user_school_year");

	$_POST["action"] = "import";
	$_FILES["userfile"]["name"] = "./res/bad_codename_import_school_year.json";
	$_FILES["userfile"]["tmp_name"] = $_FILES["userfile"]["name"];
	$request = "";
	require ("../pages/school_year/handle_request.php");
	$this->assertSame($request->label, "NotAnId");
	$this->assertSame($request->details, 0);

	$_POST["action"] = "import";
	$_FILES["userfile"]["name"] = "./res/missing_cycle_import_school_year.dab";
	$_FILES["userfile"]["tmp_name"] = $_FILES["userfile"]["name"];
	$request = "";
	require ("../pages/school_year/handle_request.php");
	$this->assertSame($request->label, "MissingField");
	$this->assertSame($request->details, "[].FirstCycle.cycle");

	$_POST["action"] = "import";
	$_FILES["userfile"]["name"] = "./res/missing_first_day_import_school_year.dab";
	$_FILES["userfile"]["tmp_name"] = $_FILES["userfile"]["name"];
	$request = "";
	require ("../pages/school_year/handle_request.php");
	$this->assertSame($request->label, "MissingField");
	$this->assertSame($request->details, "[].FirstCycle.first_day");

	$this->AddRealUser();
	$return = subscribe("User1", "a@b.c", "TheMightyPassword789");
	$this->assertSame($return["Error"], "");
	$return = subscribe("User2", "a@c.c", "TheMightyPassword789");
	$this->assertSame($return["Error"], "");
	$return = subscribe("User3", "a@d.c", "TheMightyPassword789");
	$this->assertSame($return["Error"], "");

	$_POST["action"] = "import";
	$_FILES["userfile"]["name"] = "./res/import_school_year.dab";
	$_FILES["userfile"]["tmp_name"] = $_FILES["userfile"]["name"];
	$request = "";
	$LogMsg = "";
	require ("../pages/school_year/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "ImportSuccess");

	$r = db_select_all("* FROM user_school_year ORDER BY id_user");
	$this->assertSame($r[0]["id_user"], 1);
	$this->assertSame($r[0]["id_school_year"], 1);
	$this->assertSame($r[1]["id_user"], 1);
	$this->assertSame($r[1]["id_school_year"], 2);
	$this->assertSame($r[2]["id_user"], 2);
	$this->assertSame($r[2]["id_school_year"], 1);
	$this->assertSame(count($r), 3);

	$_POST["action"] = "import";
	$_FILES["userfile"]["name"] = "./res/extend_school_year.dab";
	$_FILES["userfile"]["tmp_name"] = $_FILES["userfile"]["name"];
	$request = "";
	$LogMsg = "";
	require ("../pages/school_year/handle_request.php");
	require ("../pages/error_net.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "ImportSuccess");

	$r = db_select_all("* FROM user_school_year ORDER BY id_user");
	$this->assertSame($r[0]["id_user"], 1);
	$this->assertSame($r[0]["id_school_year"], 1);
	$this->assertSame($r[1]["id_user"], 1);
	$this->assertSame($r[1]["id_school_year"], 2);
	$this->assertSame($r[2]["id_user"], 2);
	$this->assertSame($r[2]["id_school_year"], 1);
	$this->assertSame($r[3]["id_user"], 3);
	$this->assertSame($r[3]["id_school_year"], 1);
	$this->assertSame(count($r), 4);

	$prom = fetch_school_year(1, true);
	$prom = $prom->value;
	$this->assertSame($prom["codename"], "FirstCycle");
	$prom = fetch_school_year("SecondCycle", true);
	$prom = $prom->value;
	$this->assertSame($prom["codename"], "SecondCycle");
	$prom = fetch_school_year(-1, false);
	$prom = $prom->value;
	$this->assertSame(count($prom), 2);
	$this->assertSame($prom[0]["codename"], "FirstCycle");
	$this->assertSame($prom[1]["codename"], "SecondCycle");
	$prom = fetch_school_year(-1, true);
	$prom = $prom->value;
	$this->assertSame(count($prom), 2);
	$this->assertSame($prom["FirstCycle"]["codename"], "FirstCycle");
	$this->assertSame($prom["SecondCycle"]["codename"], "SecondCycle");

	$_POST["action"] = "import";
	$_FILES["userfile"]["name"] = "./res/unknown_user_import_school_year.dab";
	$_FILES["userfile"]["tmp_name"] = $_FILES["userfile"]["name"];
	$request = "";
	require ("../pages/school_year/handle_request.php");
	$this->assertSame($request->label, "UnknownLogin");
	$this->assertSame($request->details, "[].FirstCycle.user[0]=Yyrkoon");

	$_POST["action"] = "import";
	$_FILES["userfile"]["name"] = "./res/invalid_file";
	$_FILES["userfile"]["tmp_name"] = $_FILES["userfile"]["name"];
	$request = "";
	require ("../pages/school_year/handle_request.php");
	$this->assertSame($request->label, "InvalidFile");
	$this->assertSame($request->details, "./res/invalid_file");
    }

    public function testRequestAdd()
    {
	global $Database;
	global $User;
	global $request;
	global $LogMsg;

	clear_table("user");
	clear_table("school_year");
	clear_table("user_school_year");
	$this->AddRealUser();
	$return = subscribe("User1", "a@b.c", "TheMightyPassword789");
	$this->assertSame($return["Error"], "");
	$_POST["action"] = "add";
	$_POST["name"] = "cycle0";
	$_POST["year"] = 20;
	$_POST["first_week"] = "01/07/2020";
	$request = "";
	$LogMsg = "";
	require ("../pages/school_year/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "SchoolYearAdded");
	$ret = $Database->query("SELECT * FROM school_year");
	$r = $ret->fetch_assoc();
	$this->assertSame($r["id"], 1);
	$this->assertSame($r["codename"], "cycle0");
	$this->assertSame($r["cycle"], 20);
	$this->assertSame($r["first_day"], "01/07/2020");

	$_POST["action"] = "add";
	$_POST["name"] = "cycle1";
	$_POST["year"] = 19;
	$_POST["first_week"] = "02/07/2020";
	$request = "";
	$LogMsg = "";
	require ("../pages/school_year/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "SchoolYearAdded");
	$ret = $Database->query("SELECT * FROM school_year");
	$r = $ret->fetch_assoc();
	$r = $ret->fetch_assoc();
	$this->assertSame($r["id"], 2);
	$this->assertSame($r["codename"], "cycle1");
	$this->assertSame($r["cycle"], 19);
	$this->assertSame($r["first_day"], "02/07/2020");

	unset($_POST);
	$_POST["action"] = "move";
	$_POST["id_user"] = [1];
	$_POST["id_school_year"] = 1;
	require ("../pages/school_year/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "Success");

	unset($_POST);
	$_POST["action"] = "move";
	$_POST["id_user"] = [1];
	$_POST["id_school_year"] = 2;
	require ("../pages/school_year/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "Success");

	unset($_POST);
	$_POST["action"] = "move";
	$_POST["id_user"] = [1, 2];
	$_POST["id_school_year"] = 2;
	require ("../pages/school_year/handle_request.php");
	$this->assertSame(strval($request), "");
	$this->assertSame($LogMsg, "Success");

	$ret = $Database->query("SELECT * FROM user_school_year ORDER BY id_user");
	$r = $ret->fetch_assoc();
	$this->assertSame($r["id"], 1);
	$this->assertSame($r["id_user"], 1);
	$this->assertSame($r["id_school_year"], 1);
	$r = $ret->fetch_assoc();
	$this->assertSame($r["id"], 2);
	$this->assertSame($r["id_user"], 1);
	$this->assertSame($r["id_school_year"], 2);
	$r = $ret->fetch_assoc();
	$this->assertSame($r["id"], 3);
	$this->assertSame($r["id_user"], 2);
	$this->assertSame($r["id_school_year"], 2);
    }

    public function testGetUserPromotions()
    {
	global $Database;

	$var = [];
	$prom = get_user_promotions($var);
	$this->assertSame($prom, []);

	$var = ["id" => "lel"];
	$prom = get_user_promotions($var);
	$this->assertSame($prom, []);

	clear_table("school_year");
	clear_table("user_school_year");

	$f = $this->AddRealUser();
	$user = $this->user;

	$user["cycle"] = 42;
	$prom = get_user_promotions($user);
	$this->assertSame($user["cycle"], $prom);

	$Database->query("INSERT INTO school_year (id, codename, cycle) VALUES (1, 'code', 1)");
	$Database->query("INSERT INTO user_school_year (id, id_user, id_school_year) VALUES (1, 1, 1)");

	unset($user["cycle"]);
	$prom = get_user_promotions($user, true);
	$this->assertSame($prom["code"]["id"], 1);
    }
}

