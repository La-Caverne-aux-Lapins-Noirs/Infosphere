<?php

require_once ("xtestcase.php");

class UtilsTest extends XTestCase
{
    public function testWeekDayToTimestamp()
    {
	$this->assertEquals(weekday_to_timestamp(1, 1, "00:00"), 0);
	$this->assertEquals(weekday_to_timestamp(1, 1, "00:30"), 30 * 60);
	$this->assertEquals(weekday_to_timestamp(1, 1, "01:00"), 60 * 60);
	$this->assertEquals(weekday_to_timestamp(1, 2, "00:00"), 24 * 60 * 60);
	$this->assertEquals(weekday_to_timestamp(2, 1, "00:00"), 7 * 24 * 60 * 60);
    }
    public function testDispatch()
    {
	$Position = "a";
	$User = ["authority" => EXTERN];
	$Pages = [
	    "a" => [
		"File" => __DIR__."/../res/included.php",
		"Authority" => EXTERN
	    ],
	    "HomeMenu" => [
		"File" => __DIR__."/../res/fake_home.php",
		"Authority" => EXTERN
	    ]
	];
	require ("../dispatch.php");
	$this->assertSame($Var, 42);

	$Position = "HomeMenu";
	require ("../dispatch.php");
	$this->assertSame($Var, 84);
    }

    public function testAuthorized()
    {
	$this->assertTrue(is_page_authorized(["Authority" => OUTSIDE], ["authority" => -500]));
	$this->assertTrue(is_page_authorized(["Authority" => OUTSIDE], ["authority" => +500]));
	$this->assertTrue(is_page_authorized(["Authority" => OUTSIDE], []));
	$this->assertFalse(is_page_authorized(["Authority" => 1], []));
	$this->assertTrue(is_page_authorized(["Authority" => 1], ["authority" => 2]));
	$this->assertTrue(is_page_authorized(["Authority" => 2], ["authority" => 2]));
	$this->assertFalse(is_page_authorized(["Authority" => 3], ["authority" => 2]));
    }

    public function testDebug()
    {
	global $DebugLog;

	$DebugLog = "";
	AddDebugLog("abc");
	$this->assertSame($DebugLog, "'abc'\n");

	$ret = DebugQuery("a b\n");
	$this->assertSame($ret, "a&nbsp;b<br />");

	$ret = PrintR(["a" => "b"], true);
	$this->assertTrue(!!strstr($ret, "Array"));
	$this->assertTrue(!!strstr($ret, "&nbsp;"));
	$this->assertTrue(!!strstr($ret, "<br />"));

	$DebugLog = "";
	AddDebugLogR(["a" => "b"]);
	$this->assertTrue(!!strstr($DebugLog, "Array"));
	$this->assertTrue(!!strstr($DebugLog, "&nbsp;"));
	$this->assertTrue(!!strstr($DebugLog, "<br />"));
    }
    public function testSameDay()
    {
	$this->assertTrue(same_day(0, 1));
	$this->assertTrue(same_day(0, 60 * 60 * 24 - 1));
	$this->assertFalse(same_day(0, 60 * 60 * 24));
    }
    public function testConstants()
    {
	global $DeskTypes;
	global $ActivityType;
	global $Database;
	global $Configuration;

	$Database->query("INSERT INTO configuration (codename, value) VALUES ('test', 'valeur')");
	load_constants();
	$this->assertSame($DeskTypes[0], "ClassSeat");
	$this->assertSame($ActivityType["LearningDay"], 1);
	$this->assertSame($Configuration->Properties["test"], "valeur");
    }

    public function testErrorNet()
    {
	global $ErrorMsg;
	global $LogMsg;
	global $_POST;

	$_POST["a"] = "52";
	$LogMsg = "42";
	$request = new ErrorResponse("NotAnId");
	$fetch = $request;
	require ("../pages/error_net.php");
	$this->assertSame($LogMsg, "");
	$this->assertSame($fetch, NULL);
	$this->assertSame($request, NULL);
	$this->assertSame($_POST["a"], "52");

	$_POST["a"] = "52";
	$LogMsg = "42";
	$request = new ValueResponse(true);
	$fetch = $request;
	require ("../pages/error_net.php");
	$this->assertSame($LogMsg, "42");
	$this->assertSame($fetch, true);
	$this->assertSame($request, true);
	$this->assertSame($_POST, []);
    }

    public function testTryInsert()
    {
	global $Database;

	clear_table("laboratory");

	$ret = @try_insert("laboratory", "!", [], "", "", ["name"], [], "codename", true, false);
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");

	$ret = @try_insert("laboratory", "new_group", [], "", "", ["name"], [], "codename", true, false);
	$this->assertSame($ret->label, "MissingField");

	copy("./res/medal.png", "./icon.png");
	$ret = @try_insert("laboratory", "new_group", ["deleted" => 42], "./icon.png", "./dres/groups/", ["name"], ["fr_name" => "Nouveau"], "codename", true, false);
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value["codename"], "new_group");
	$this->assertSame($ret->value["name"], "Nouveau");
	$this->assertSame($ret->value["icon"], "./dres/groups/new_group.png");
	$this->assertSame($ret->value["id"], 1);
	$this->assertSame($ret->value["deleted"], 42);

	clear_table("laboratory");
	copy("./res/medal.png", "./icon.png");
	$request = [
	    "table" => "laboratory",
	    "codename" => "new_group",
	    "trusted_fields" => ["deleted" => 42],
	    "icon" => "./icon.png",
	    "icon_dir" => "./dres/groups/",
	    "language_fields" => ["name"],
	    "language" => ["fr_name" => "Nouveau"],
	];
	$ret = @try_insert($request);
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value["codename"], "new_group");
	$this->assertSame($ret->value["name"], "Nouveau");
	$this->assertSame($ret->value["icon"], "./dres/groups/new_group.png");
	$this->assertSame($ret->value["id"], 1);
	$this->assertSame($ret->value["deleted"], 42);
	unset($request["codename"]);
	$ret = @try_insert($request);
	$this->assertSame($ret->label, "MissingCodeName");
	unset($request["table"]);
	$ret = @try_insert($request);
	$this->assertSame($ret->label, "MissingParameter");
	$ret = db_select_one("* FROM laboratory");
	$this->assertSame($ret["codename"], "new_group");
	$this->assertSame($ret["deleted"], 42);

	$ret = @try_update("laboratory");
	$this->assertSame($ret->label, "MissingCodeName");
	$request = [
	    "table" => "laboratory",
	    "codename" => 1,
	    "trusted_fields" => ["deleted" => 0],
	];
	$ret = @try_update($request);
	$ret = db_select_one("* FROM laboratory");
	$this->assertSame($ret["id"], 1);
	$this->assertSame($ret["codename"], "new_group");
	$this->assertSame($ret["deleted"], NULL);
	unset($request["codename"]);
	$ret = @try_update($request);
	$this->assertSame($ret->label, "MissingCodeName");
	unset($request["table"]);
	$ret = @try_update($request);
	$this->assertSame($ret->label, "MissingParameter");


	$ret = @try_insert("laboratory", "new_group", [], "", "", ["name"], ["fr_name" => "Nouveau"], "codename", true, false);
	$this->assertSame($ret->label, "CodeNameAlreadyUsed");
	$this->assertSame($ret->details, "new_group");

	copy("./res/medal.png", "./icon.png");
	$ret = @try_insert("laboratory", "new_group2", [], "./icon.png", "./dres/groups/", ["name"], ["fr_name" => "Nouveau"], "codename", true, true);
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value["codename"], "new_group2");
	$this->assertSame($ret->value["name"], "Nouveau");
	$this->assertSame($ret->value["icon"], "./dres/groups/new_group2.png");
	$this->assertSame($ret->value["id"], 2);

	$out = db_select_all("* FROM laboratory");
	$this->assertSame($out[0]["codename"], "new_group");
	$this->assertSame($out[0]["id"], 1);
	$this->assertSame($out[1]["codename"], "new_group2");
	$this->assertSame($out[1]["id"], 2);
    }

    public function testDictionnary()
    {
	$this->assertFalse(isset($LanguageList));
	$this->assertFalse(isset($Language));
	$this->assertFalse(isset($Dictionnary));
	require ("../language.php");
	global $Dictionnary;

	$this->assertTrue(isset($LanguageList));
	$this->assertTrue(isset($Language));
	$this->assertSame($LanguageList["fr"], "Français");
	$this->assertSame($Language, "fr");
	$this->assertSame($Dictionnary["Infosphere"], "Infosphere");
    }

    public function testFetchData()
    {
	clear_table("room");

	$out = add_room("Enterprise", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Entreprise"]);
	$this->assertFalse($out->is_error());
	$out = add_room("Voyager", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Voyageur"]);
	$this->assertFalse($out->is_error());

	$out = fetch_data("room", -1, "name", "codename", true, true, false, []);
	$this->assertSame($out->is_error(), false);
	$out = $out->value;
	$this->assertSame(count($out), 2);
	$this->assertSame($out["Enterprise"]["codename"], "Enterprise");
	$this->assertSame($out["Enterprise"]["name"], "Entreprise");
	$this->assertSame($out["Voyager"]["codename"], "Voyager");
	$this->assertSame($out["Voyager"]["name"], "Voyageur");

	$out = fetch_data("room", -1, "name", "codename", true, true, false, ["id" => 1]);
	$this->assertSame($out->is_error(), false);
	$out = $out->value;
	$this->assertSame(count($out), 1);
	$this->assertSame($out["Enterprise"]["codename"], "Enterprise");
	$this->assertSame($out["Enterprise"]["name"], "Entreprise");

	$out = fetch_data("room", -1, "name", "codename", true, false, false, ["id" => 1]);
	$this->assertSame($out->is_error(), false);
	$out = $out->value;
	$this->assertSame(count($out), 1);
	$this->assertSame($out["Enterprise"]["codename"], "Enterprise");
	$this->assertSame($out["Enterprise"]["name"], "Entreprise");

	$out = fetch_data("room", "Voyager", ["name"], "codename", false, true, true);
	$this->assertFalse($out->is_error());
	$out = $out->value;
	$this->assertSame($out["id"], 2);
	$this->assertSame($out["name"], "Voyageur");

	$out = fetch_data([]);
	$this->assertSame($out->label, "MissingParameter");
	$this->assertSame($out->details, "table name");
	$request = [
	    "table" => "room",
	    "id" => "Voyager",
	    "language_fields" => ["name"],
	    "codename_column" => "codename",
	    "by_name" => false,
	    "pack" => true
	];
	$out = fetch_data($request);
	$this->assertFalse($out->is_error());
	$out = $out->value;
	$this->assertSame($out["id"], 2);
	$this->assertSame($out["name"], "Voyageur");

	
	$out = fetch_data("room", "Voyager", [], "codename", false);
	$this->assertFalse($out->is_error());
	$out = $out->value;
	$this->assertSame(count($out), 1);
	$this->assertSame($out[0]["id"], 2);
	$this->assertFalse(isset($out[0]["name"]));

	$out = fetch_data("room", "Reliant", [], "codename", false);
	$this->assertSame($out->label, "BadCodeName");
	$this->assertSame($out->details, "Reliant");
    }

    public function testPrintAll()
    {
	ob_start();
	db_print_all("* FROM authorities");
	$ob = ob_get_clean();
	$out = db_print_all("* FROM authorities", "", true);
	$this->assertSame($ob, $out);
    }

    public function testForgeLanguageFields()
    {
	global $LanguageList;

	$LanguageList = ["fr" => "", "en" => "", "kl" => ""]; // Francais, anglais, klingon
	$ret = forge_language_fields(["a", "b", "c"]);
	$this->assertSame($ret,
			  ["fr_a", "fr_b", "fr_c",
			   "en_a", "en_b", "en_c",
			   "kl_a", "kl_b", "kl_c"]);
	$ret = forge_language_fields("a", false, false, "x");
	$this->assertSame($ret, ["x.fr_a", "x.en_a", "x.kl_a"]);
	$LanguageList = ["fr" => "Français"];

	$ret = forge_language_insert("name", ["fr_name" => "FR"]);
	$ret = $ret->value;
	$this->assertSame($ret["Labels"][0], "fr_name");
	$this->assertSame($ret["Texts"][0], "'FR'");

	$ret = forge_language_insert("!", ["fr_name" => "FR"]);
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");

	$ret = forge_language_insert("name", ["f_name" => "FR"]);
	$this->assertSame($ret->label, "MissingField");
	$this->assertSame($ret->details, "Français 'name'");

	$ret = forge_language_update("!", []);
	$this->assertSame($ret->label, "InvalidParameter");
	$ret = forge_language_update("name", ["fr_name" => "FR"], true);
	$ret = $ret->value;
	$this->assertSame($ret, "`fr_name` = 'FR'");
    }

    public function testLoadConfiguration()
    {
	$this->assertTrue(@load_configuration($lel)->is_error());
	$ret = load_configuration('{"field":42}');
	$this->assertTrue(compare_tree($ret->value, ["field" => 42], true));
	$ret = load_configuration("[Field\nInside = 42\n]\n");
	$this->assertTrue(compare_tree($ret->value, ["Field" => ["Inside" => 42]], true));
	$ret = load_configuration("}{");
	$this->assertSame($ret->label, "InvalidConfiguration");
	$ret = load_configuration("./res/room.dab");
	$this->assertSame($ret->is_error(), false);
	$ret = $ret->value;
	$this->assertSame($ret[0]["Name"], "Obs");
	$this->assertSame($ret[1]["Name"], "Promenade");
	$ret = load_configuration("./res/room.png");
    }

    public function testSaveConfiguration()
    {
	$this->assertSame(save_configuration(NULL), NULL);
	$this->assertSame(save_configuration(["Field" => ["Inside" => 42]]), "\n[Field\n  Inside = 42\n]\n\n");
	$this->assertSame(save_configuration(["Field" => 42], "json"), "{\n    \"Field\": 42\n}");
	if (file_exists("./dres/tmp.json"))
	    unlink("./dres/tmp.json");
	save_configuration_file(["Field" => 42], "./dres/tmp.json");
	$this->assertTrue(file_exists("./dres/tmp.json"));
	$tmp = file_get_contents("./dres/tmp.json");
	$this->assertSame($tmp, "{\n  \"Field\": 42\n}\n");
    }

    public function testCompareTree()
    {
	$a["a"] = 42;
	$a["b"]["a"] = 80;
	$a["b"]["b"] = 120;
	$a["b"]["c"]["a"] = "lel";
	$b["a"] = 42;
	$b["b"]["a"] = 80;
	$b["b"]["b"] = 120;
	$b["b"]["c"]["a"] = "lel";
	$this->assertTrue(compare_tree($a, $b, true));
	$this->assertTrue(compare_tree($b, $a, true));
	$b["b"]["c"]["a"] = "ko";
	$this->assertFalse(compare_tree($a, $b, true));
	$this->assertFalse(compare_tree($b, $a, true));
	unset ($a["b"]["c"]["a"]);
	$this->assertFalse(compare_tree($a, $b, true));
	$this->assertFalse(compare_tree($b, $a, true));
	unset ($b["b"]["c"]["a"]);
	$this->assertTrue(compare_tree($a, $b, true));
	$this->assertTrue(compare_tree($b, $a, true));
	$a["b"]["d"] = 50;
	$b["b"]["d"]["a"] = 10;
	$this->assertFalse(compare_tree($a, $b, true));
	$this->assertFalse(compare_tree($b, $a, true));
	unset($a["b"]["d"]);
	unset($b["b"]["d"]["a"]);
	$b["b"]["d"] = 50;
	$a["b"]["d"]["a"] = 10;
	$this->assertFalse(compare_tree($a, $b, true));
	$this->assertFalse(compare_tree($b, $a, true));
	$this->assertFalse(compare_tree(42, 50, true));
	$this->assertFalse(compare_tree(42, [50], true));
    }

    public function testCheckDate()
    {
	$this->assertNotFalse(check_date("1/2/1993"));
	$this->assertNotFalse(check_date("01/02/1993"));
	$this->assertFalse(check_date("1/1993"));
    }

    public function testResponse()
    {
	global $Dictionnary;

	$err = new Response;
	$this->assertSame($err->is_error(), false);

	$err = new ErrorResponse("BadCodeName", ["A", "B", "C"], "BadCodeName");
	$this->assertSame(strval($err), $Dictionnary["BadCodeName"].": A, B, C (".$Dictionnary["BadCodeName"].")");
	$err = new ErrorResponse("BadCodeName", ["A", "B", "C"], "Arbitraire");
	$this->assertSame(strval($err), $Dictionnary["BadCodeName"].": A, B, C (Arbitraire)");
	$err = new ErrorResponse("UnknownCodeName", "Mordanis");
	$this->assertSame(strval($err), $Dictionnary["UnknownCodeName"].": Mordanis");
	$err = new Response("UnknownCodeName");
	$this->assertSame(strval($err), $Dictionnary["UnknownCodeName"]);
	$err = new Response();
	$this->assertSame(strval($err), "");
    }

    public function testGetAuthorities()
    {
	$auth = [
	    -1 => "Banished",
	    0 => "Visitor",
	    1 => "Extern",
	    2 => "Student",
	    3 => "Assistant",
	    4 => "Teacher",
	    5 => "ContentAuthor",
	    6 => "Administrator"
	];
	$this->assertSame(get_authorities(), $auth);
	$this->assertSame(get_authorities(2), "Student");
	$this->assertSame(get_authorities([1, 2]), [1 => "Extern", 2 => "Student"]);
    }

    public function testLinks()
    {
	global $Database;

	$this->AddRealUser();
	clear_table("laboratory");
	$lng = ["fr_name" => "groupe", "fr_description" => "super groupe"];
	$lng = ["fr_name" => "groupe", "fr_description" => "super groupe"];
	system("cp ./res/pic.png ./pic.png");
	$ret = try_insert("laboratory", "elem", [], "./pic.png", "./dres/groups/", ["name", "description"], $lng);
	$this->assertSame($ret->is_error(), false);
	system("cp ./res/pic.png ./pic.png");
	$ret = try_insert("laboratory", "elem2", [], "./pic.png", "./dres/groups/", ["name", "description"], $lng);
	$this->assertSame($ret->is_error(), false);
	system("cp ./res/pic.png ./pic.png");
	$ret = try_insert("laboratory", "elem3", [], "./pic.png", "./dres/groups/", ["name", "description"], $lng);
	$this->assertSame($ret->is_error(), false);

	$ret = try_insert("laboratory", "elemX", ["!" => 42], "./pic.png", "./dres/groups/", ["name", "description"], $lng);
	$this->assertSame($ret->is_error(), true);
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");

	$ret = try_update("!", "!o", [], "", "", ["name", "description"], $lng);
	$this->assertSame($ret->label, "InvalidTableName");
	$this->assertSame($ret->details, "!");
	$ret = try_update("laboratory", "!", [], "", "", ["name", "description"], $lng);
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");
	$ret = try_update("laboratory", "elem3", ["!o" => 42], "", "", ["name", "description"], $lng);
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!o");

	$lng = [];
	$lng["fr_description"] = "bouh groupe";
	$ret = try_update("laboratory", "elem3", ["deleted" => 1], "", "", ["name", "description"], $lng);
	$this->assertSame($ret->is_error(), false);
	$x = db_select_one("* FROM laboratory WHERE codename = 'elem3'");
	$this->assertSame($x["fr_description"], "bouh groupe");
	$this->assertSame($x["fr_name"], "groupe");
	$this->assertSame($x["icon"], "./dres/groups/elem3.png");
	$Database->query("UPDATE laboratory SET deleted = 0 WHERE codename = 'elem3'");

	$ret = try_update("laboratory", "elem3", ["deleted" => 1], "", "", ["name", "description"], $lng, "codename", true);
	$this->assertSame($ret->value["id"], 3);

	$ret = @add_links($lel, $lel, $lel, $lel);
	$this->assertSame($ret->label, "MissingId");
	$ret = @add_links("Mordanis", $lel, $lel, $lel);
	$this->assertSame($ret->label, "MissingId");

	$ret = @add_link($lel, $lel, $lel, $lel);
	$this->assertSame($ret->label, "MissingCodeName");
	$ret = @add_link("!", $lel, $lel, $lel) ;
	$this->assertSame($ret->label, "MissingTableName");
	$ret = @add_link("!", "!", $lel, $lel);
	$this->assertSame($ret->label, "MissingTableName");
	$ret = @add_link(42, "!", $lel, $lel);
	$this->assertSame($ret->label, "MissingTableName");
	$ret = @add_link(42, 43, "!", "!");
	$this->assertSame($ret->label, "InvalidTableName");
	$ret = @add_link(42, 43, "user", "!");
	$this->assertSame($ret->label, "NotAnId"); // Because 42 is invalid
	$ret = @add_link($lel, 43, "user", "laboratory");
	$this->assertSame($ret->label, "MissingCodeName");
	$ret = @add_link(42, $lel, "user", "laboratory");
	$this->assertSame($ret->label, "NotAnId"); // Because 42 is invalid

	$ret = @add_link("Mordanis", "elem", "user", "laboratory!");
	$this->assertSame($ret->label, "InvalidTableName");
	$this->assertSame($ret->details, "laboratory!");

	$ret = add_link("Mordanis", "elem", "user", "laboratory");
	$this->assertFalse($ret->is_error());
	$ret = add_link("Mordanis", "elem", "user", "laboratory");
	$this->assertFalse($ret->is_error());
	$ret = add_link("Mordanis", "elem", "user", "laboratory", true);
	$this->assertTrue($ret->is_error());
	$this->assertSame($ret->label, "AlreadyAssociated");
	$this->assertSame($ret->details, "");

	$ret = add_links(["Mordanis"], ["elem", "elem2", "elem3"], "user", "laboratory", false, [], "user_laboratory!");
	$this->assertSame($ret->label, "InvalidTableName", "user_laboratory!");
	$ret = add_links(["Mordanis"], ["elem", "elem2", "elem3"], "user", "laboratory", false, [], "user_laboratory");
	$this->assertFalse($ret->is_error());
	$ret = add_links(1, ["elem", "elem2", "elem3"], "user", "laboratory");
	$this->assertFalse($ret->is_error());
	$ret = add_links("Mordanis", 1, "user", "laboratory");
	$this->assertFalse($ret->is_error());
	$ret = add_links(["Mordanis"], "elem;elem2;elem3", "user", "laboratory", true);
	$this->assertSame($ret->label, "AlreadyAssociated");
	$this->assertSame($ret->details, "");
	global $Database;

	$test = [];
	$x = $Database->query("SELECT * FROM user_laboratory");
	while (($y = $x->fetch_assoc()))
	    $test[$y["id_user"]][$y["id_laboratory"]] = true;
	$this->assertSame(@$test[1][0], NULL);
	$this->assertSame(@$test[1][1], true);
	$this->assertSame(@$test[1][2], true);
	$this->assertSame(@$test[1][3], true);
	$this->assertSame(@$test[1][4], NULL);
	$this->assertSame(@$test[0][1], NULL);
	$this->assertSame(@$test[2][1], NULL);

	$ret = @handle_links($lel, "elem", "user", "laboratory");
	$this->assertSame($ret->label, "MissingId");
	$ret = @handle_links("Mordanis", $mem, "user", "laboratory");
	$this->assertSame($ret->label, "MissingId");

	$ret = handle_links("Mordanis_", "elem", "!", "laboratory");
	$this->assertSame($ret->label, "InvalidTableName");
	$ret = handle_links(1, 1, "user", "!");
	$this->assertSame($ret->label, "InvalidTableName");

	$ret = handle_links("Mordanislol", "elem", "user", "laboratory");
	$this->assertSame($ret->label, "BadCodeName");
	$this->assertSame($ret->details, "Mordanislol");

	$ret = handle_links("Mordanis", "elemlol", "user", "laboratory");
	$this->assertSame($ret->label, "BadCodeName");
	$this->assertSame($ret->details, "elemlol");

	$ret = @remove_links($lel, $lel, $lel, $lel);
	$this->assertSame($ret->label, "MissingId");
	$ret = @remove_links("Mordanis", $lel, $lel, $lel);
	$this->assertSame($ret->label, "MissingId");

	$ret = @remove_link($lel, $lel, $lel, $lel);
	$this->assertSame($ret->label, "MissingCodeName");
	$ret = @remove_link("!", $lel, $lel, $lel);
	$this->assertSame($ret->label, "MissingTableName");
	$ret = @remove_link("!", "!", $lel, $lel);
	$this->assertSame($ret->label, "MissingTableName");
	$ret = @remove_link(42, "!", $lel, $lel);
	$this->assertSame($ret->label, "MissingTableName");
	$ret = @remove_link(42, 43, $lel, $lel);
	$this->assertSame($ret->label, "MissingTableName");
	$ret = @remove_link(42, 43, "user", $lel);
	$this->assertSame($ret->label, "NotAnId"); // 42 is not an id

	$ret = remove_link("Mordanis", "elem2", "user", "g!");
	$this->assertSame($ret->label, "InvalidTableName");
	$ret = remove_links("Mordanis", "elem2", "user", "g!");
	$this->assertSame($ret->label, "InvalidTableName");
	$ret = remove_links("Mordanis", "elem2", "user!", "laboratory");
	$this->assertSame($ret->label, "InvalidTableName");
	$ret = remove_links("Mordanis", "elem2", "user", "laboratory", false, "user_laboratory!");
	$this->assertSame($ret->label, "InvalidTableName");

	$ret = update_link("Mordanis", "elem2", "user", "laboratory", []);
	$this->assertSame($ret->value["id"], 2);
	$this->assertSame(count($ret->value), 1);

	$ret = remove_link("Mordanis", "elem2", "user", "laboratory", false, "user_laboratory");
	$this->assertFalse($ret->is_error());
	$ret = remove_link("Mordanis", "elem2", "user", "laboratory");
	$this->assertFalse($ret->is_error());
	$ret = remove_links(1, 2, "user", "laboratory", true);
	$this->assertSame($ret->label, "AssociationDoesNotExist");

	$ret = update_link("Mordanis", "elem2", "user", "laboratory", []);
	$this->assertSame($ret->label, "AssociationDoesNotExist");

	$ret = handle_links("-Mordanis", "-elem", "user", "laboratory", "user_laboratory");
	$this->assertFalse($ret->is_error());
	$ret = handle_links(-1, -1, "user", "laboratory");
	$this->assertFalse($ret->is_error());
	$ret = handle_links("-Mordanis", "-elem", "user", "laboratory", true);
	$this->assertSame($ret->label, "AssociationDoesNotExist");
	$ret = handle_links("Mordanis", "elem", "user", "laboratory");
	$this->assertFalse($ret->is_error());
	$ret = handle_links("-Mordanis", "-elem", "user", "laboratory", true);
	$this->assertFalse($ret->is_error());

	unset($test);
	$test = [];
	$x = $Database->query("SELECT * FROM user_laboratory");
	while (($y = $x->fetch_assoc()))
	    $test[$y["id_user"]][$y["id_laboratory"]] = true;
	$this->assertSame(@$test[1][0], NULL);
	$this->assertSame(@$test[1][1], NULL);
	$this->assertSame(@$test[1][2], NULL);
	$this->assertSame(@$test[1][3], true);
	$this->assertSame(@$test[1][4], NULL);
	$this->assertSame(@$test[0][1], NULL);
	$this->assertSame(@$test[2][1], NULL);

	$ret = remove_links("Mordanis", ["elem", "elem3"], "user", "laboratory");
	$this->assertFalse($ret->is_error());
	$ret = remove_links("Mordanis", "elem3", "user", "laboratory");
	$this->assertFalse($ret->is_error());
	$ret = remove_links("Damdoshi", "elem4", "user", "laboratory");
	$this->assertSame($ret->label, "BadCodeName");
	$this->assertSame(count($ret->details), 1);
	$this->assertSame($ret->details[0], "Damdoshi");
	remove_links("Mordanis", "elem4", "user", "laboratory");
	$this->assertSame($ret->label, "BadCodeName");
	$this->assertSame(count($ret->details), 1);
	$this->assertSame($ret->details[0], "Damdoshi");
	$ret = remove_links("Mordanis", ["elem", "elem3"], "user", "laboratory", true);
	$this->assertSame($ret->label, "AssociationDoesNotExist");

	clear_table("school_year");
	clear_table("instance");
	clear_table("instance_school_year");
	$Database->query("INSERT INTO instance (id_activity, codename) VALUES (-1, 'test')");
	$Database->query("INSERT INTO school_year (codename) VALUES ('c0')");
	$ret = add_link("test", "c0", "instance", "school_year", false, ["tags!" => 3]);
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "tags!");
	$ret = add_link("test", "c0", "instance", "school_year", false, ["tags" => 3]);
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value["id"], 1);
	$this->assertSame($ret->value["tags"], 3);
	$ret = db_select_one("* FROM instance_school_year WHERE id = 1");
	$this->assertSame($ret["id"], 1);
	$this->assertSame($ret["id_instance"], 1);
	$this->assertSame($ret["id_school_year"], 1);
	$this->assertSame($ret["tags"], "3");

	$ret = update_link("!", "c0", "instance", "school_year", ["tags" => 4], "instance_school_year");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");
	$ret = update_link("test", "!", "instance", "school_year", ["tags" => 4], "instance_school_year");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");
	$ret = update_link("test", "c0", "instance", "school_year", ["tags" => 4], "!");
	$this->assertSame($ret->label, "InvalidTableName");
	$this->assertSame($ret->details, "!");

	$ret = update_link("test", "c0", "instance", "school_year", ["tags" => 4], "instance_school_year");
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value["id"], 1);
	$this->assertSame($ret->value["tags"], 4);
	$ret = db_select_one("* FROM instance_school_year WHERE id = 1");
	$this->assertSame($ret["id"], 1);
	$this->assertSame($ret["id_instance"], 1);
	$this->assertSame($ret["id_school_year"], 1);
	$this->assertSame($ret["tags"], "4");

	$ret = update_link("test", "c0", "instance", "school_year", ["!" => 4]);
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");
    }

    public function testUploadPng()
    {
	$ret = upload_png("./missing_file.png", "./final0.png", [400, 400], EXACT_PICTURE_SIZE, true);
	$this->assertSame($ret->label, "MissingFile");

	system("cp ./res/pic.png ./pic.png");
	$ret = upload_png("./pic.png", "./final0.png", [400, 400], EXACT_PICTURE_SIZE, true);
	$this->assertSame($ret->is_error(), false);

	system("cp ./res/pic.png ./pic.png");
	$ret = upload_png("./pic.png", "./final1.png", [206, 206], EXACT_PICTURE_SIZE, false);
	$this->assertSame($ret->is_error(), false);

	system("cp ./res/pic.png ./pic.png");
	$ret = upload_png("./pic.png", "./final2.png", [205, 205], EXACT_PICTURE_SIZE, false);
	$this->assertSame($ret->label, "InvalidPictureSize");

	system("cp ./res/pic.png ./pic.png");
	$ret = upload_png("./pic.png", "./final2.png", -1, EXACT_PICTURE_SIZE, false);
	$this->assertSame($ret->label, "BadUsage");

	// room.png is not a real png
	system("cp ./res/room.png ./pic.png");
	$ret = upload_png("./pic.png", "./final2.png", -1, EXACT_PICTURE_SIZE, false);
	$this->assertSame($ret->label, "BadFileFormat");
    }

    public function testisSizeValid()
    {
	$this->assertSame(is_size_valid(MINIMUM_PICTURE_SIZE, 0, 5), true);
	$this->assertSame(is_size_valid(MINIMUM_PICTURE_SIZE, 5, 0), false);
	$this->assertSame(is_size_valid(EXACT_PICTURE_SIZE, 5, 5), true);
	$this->assertSame(is_size_valid(EXACT_PICTURE_SIZE, 0, 5), false);
	$this->assertSame(is_size_valid(MAXIMUM_PICTURE_SIZE, 0, 5), false);
	$this->assertSame(is_size_valid(MAXIMUM_PICTURE_SIZE, 5, 0), true);
	$this->assertSame(is_size_valid("!", 5, 0), false);
    }

    public function testUploadArchive()
    {
	$this->assertSame(upload_archive("./res/archive.zip", "./copied_archive.zip", 10), "FileTooBig");
	$this->assertSame(upload_archive("./res/archive.zip", "./copied_archive", 500), "");
	$this->assertSame(sha1_file("./res/ref.zip"), sha1_file("./copied_archive.zip"));
	rename("./copied_archive.zip", "./res/archive.zip");
	$this->assertSame(upload_archive("./res/archive.tar.gz", "./copied_archive.tar.gz", 10), "FileTooBig");
	$this->assertSame(upload_archive("./res/archive.tar.gz", "./copied_archive", 500), "");
	$this->assertSame(sha1_file("./res/ref.tar.gz"), sha1_file("./copied_archive.tar.gz"));
	rename("./copied_archive.tar.gz", "res/archive.tar.gz");
	$this->assertSame(upload_archive("./infosphere.dab", "./kaboom", 500), "BadFileFormat");
    }

    public function testUnroll()
    {
	$gen = unroll(["a" => false, "b" => "aaa", "c" => "bbb"], WHERE, ["c"]);
	$this->assertSame($gen, "a = '0' AND b = 'aaa'");
	$gen = unroll(["a" => false, "b" => "aaa", "c" => "bbb"], WHERE_OR, []);
	$this->assertSame($gen, "a = '0' OR b = 'aaa' OR c = 'bbb'");
	$gen = unroll(["a", "b", "c"], SELECT, ["b"]);
	$this->assertSame($gen, "a,c");
	$gen = unroll(["a", "b", "c"], SELECT, ["b"], ["c"]);
	$this->assertSame($gen, "c");
	$gen = unroll(["a" => "A", "b" => "B", "c" => "C"], SELECT, ["b"], ["c"]);
	$this->assertSame($gen, "c");
	try
	{
	    unroll(["a"], "!", []);
	}
	catch (Exception $e)
	{
	    $this->assertSame($e->getMessage(), "Invalid parameter type for unroll function");
	}
    }

    public function testUpdatetable()
    {
	clear_table("room");
	$out = add_room("Enterprise", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Entreprise"]);
	$this->assertFalse($out->is_error());
	$out = add_room("Voyager", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Voyageur"]);
	$this->assertFalse($out->is_error());

	$ret = @update_table("room", $lel, [], []);
	$this->assertSame($ret->label, "MissingCodeName");
	$this->assertSame($ret->details, "");
	$ret = @update_table("room!", "good_id", [], []);
	$this->assertSame($ret->label, "InvalidTableName");
	$this->assertSame($ret->details, "room!");
	$ret = @update_table("room", "!", [], []);
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");
	$ret = update_table("room", "Enterprise", ["fr_name" => "X", "capacity" => 20], ["fr_name"]);
	$cmp = db_select_one("* FROM room WHERE codename = 'Enterprise'");
	$this->assertSame($ret->value, $cmp);
	$rooms = fetch_rooms("Enterprise", true);
	$this->assertFalse($rooms->is_error());
	$rooms = $rooms->value;
	$this->assertSame($rooms["capacity"], 20);
    }

    public function testMarkAsDeleted()
    {
	clear_table("room");
	$out = add_room("Enterprise", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Entreprise"]);
	$this->assertFalse($out->is_error());
	$out = add_room("Voyager", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Voyageur"]);
	$this->assertFalse($out->is_error());
	$rooms = fetch_rooms(-1, true);
	$rooms = $rooms->value;
	$this->assertSame(count($rooms), 2);
	mark_as_deleted("room", 1);
	$rooms = fetch_rooms(-1, true);
	$rooms = $rooms->value;
	$this->assertSame(count($rooms), 1);
	$this->assertSame($rooms["Voyager"]["name"], "Voyageur");

	$ret = @mark_as_deleted("room", $lel);
	$this->assertSame($ret->label, "MissingCodeName");
	$this->assertSame($ret->details, "");
	$ret = mark_as_deleted("room", "!");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");

	// Pas de declaration de champ de nom de code: conservation du nom de code intact si il y en a un,
	// et bien sur, possibiltié d'utiliser mark_as_deleted sur des tables sans nom de code.
	$ret = mark_as_deleted("room", 2, "", true);
	$this->assertSame($ret->is_error(), false);
	$ret = $ret->value;
	$this->assertSame($ret["id"], 2);
	$this->assertSame($ret["deleted"], 1);
	$this->assertSame($ret["codename"], "Voyager");
    }

    public function testClickable()
    {
	ob_start();
	clickable("xxx");
	$out = ob_get_contents();
	ob_end_clean();
	$this->assertSame($out, 'onclick="location.href=\'xxx\';" onmouseover="" style="cursor: pointer; "');
    }

    public function testGenerateActivityCode()
    {
	$this->assertNotNull(generate_activity_code("Mordanis", "lel", "code"));
    }

    public function testCheckId()
    {
	clear_table("room");
	$out = add_room("Enterprise", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Entreprise"]);
	$this->assertFalse($out->is_error());
	$this->assertSame(check_id("room", 1), true);
	$this->assertSame(check_id("room", 2), false);
	$this->assertSame(check_id("!", 1), false);
	$out = add_room("Voyager", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Voyageur"]);
	$this->assertFalse($out->is_error());
	$this->assertSame(check_id("room", 2), true);
    }

    public function testResolveCodename()
    {
	$this->assertSame(resolve_codename("!lulz", "flop"), "InvalidParameter");
	$this->assertSame(resolve_codename("flop", "!lulz"), "InvalidParameter");
	$this->assertSame(resolve_codename("flop", "flup", "!lulz"), "InvalidParameter");
	$this->AddRealUser();
	$this->assertSame(resolve_codename("user", "Mordanis", "codename"), 1);
	clear_table("room");
	$out = add_room("Enterprise", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Entreprise"]);
	$this->assertFalse($out->is_error());
	$ret = resolve_codename("room", "Enterprise");
	$this->assertSame($ret->value, 1);
	$ret = resolve_codename("room", 1);
	$this->assertSame($ret->value, 1);
	$ret = resolve_codename("room", 2);
	$this->assertSame($ret->label, "NotAnId");

	$out = add_room("Voyager", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Entreprise"]);
	$ret = resolve_codename("room", ["Enterprise", "Voyager"], "codename", true);
	$this->assertSame($ret->value[0]["id"], 1);
	$this->assertSame($ret->value[1]["id"], 2);
	$this->assertSame($ret->value[0]["codename"], "Enterprise");
	$this->assertSame($ret->value[1]["codename"], "Voyager");
    }

    public function testCheckCodeName()
    {
	clear_table("room");
	$out = add_room("Enterprise", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Entreprise"]);
	$this->assertFalse($out->is_error());
	$out = add_room("Defiant", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Défiant"]);
	$this->assertFalse($out->is_error());
	$out = add_room("Voyager", 10, "./res/room.png", "./res/room.dab", ["fr_name" => "Voyageur"]);
	$this->assertFalse($out->is_error());
	$ret = check_codename("room", "Enterprise");
	$this->assertSame($ret, []);
	$ret = check_codename("room", "MilleniumFalcon");
	$this->assertSame($ret, ["MilleniumFalcon"]);
	$ret = check_codename("room", "-MilleniumFalcon");
	$this->assertSame($ret, ["MilleniumFalcon"]);
	$ret = check_codename("room", ["Enterprise", "-Voyager", "MilleniumFalcon"], "codename");
	$this->assertSame($ret, ["MilleniumFalcon"]);
	$ret = check_codename("room", ["Enterprise", "-Voyager", "-MilleniumFalcon"], "codename");
	$this->assertSame($ret, ["MilleniumFalcon"]);
	$ret = check_codename("room", ["Enterprise", "70", 42, "-MilleniumFalcon"], "codename");
	$this->assertSame($ret, ["MilleniumFalcon"]);
    }

    public function testSplitSymbol()
    {
	$this->assertSame(split_symbols("abc;def;ghi", ";")->value, ["abc", "def", "ghi"]);
	$this->assertSame(split_symbols("abc;-def;ghi", ";")->value, ["abc", "-def", "ghi"]);
	$this->assertSame(split_symbols("   abc  ;  def ; -ghi    ", ";")->value, ["abc", "def", "-ghi"]);
	$this->assertSame(split_symbols("abc;  4def ;ghi", ";")->label, "InvalidParameter");
	$this->assertSame(split_symbols("abc;  4def ;ghi", ";")->details, "4def");
	$this->assertSame(split_symbols("1;-2;3;lel;  -hum")->value, [1, -2, 3, "lel", "-hum"]);
	$this->assertSame(@split_symbols($lel)->label, "MissingCodeName");
    }

    public function testFormatStr()
    {
	$this->assertSame(formatstr("a\nb\nc\n"), "a b c \n");
    }

    public function testIsBase64()
    {
	$this->assertSame(is_base64(base64_encode("abcdef")), true);
	$this->assertSame(is_base64("!"), false);
	@$this->assertSame(is_base64($lel), false);
    }

    public function testLog()
    {
	global $Database;
	global $User;

	try
	{
	    $User = NULL;
	    add_log(REPORT, "AAA", -1);
	}
	catch (Exception $e)
	{
	    $this->assertSame($e->getMessage(), "InvalidParameter");
	}

	$this->AddRealUser();
	$User = $this->user;
	try
	{
	    @add_log(REPORT, $lel, $lol);
	}
	catch (Exception $e)
	{
	    $this->assertSame($e->getMessage(), "InvalidParameter");
	}

	$User["id"] = 42;
	add_log(REPORT, "AAA");

	$x = $Database->query("SELECT id_user, message FROM log ORDER BY id DESC");
	$x = $x->fetch_assoc();
	$this->assertSame($x["id_user"], 42);
	$this->assertSame($x["message"], "AAA");
    }

    public function testTryGet()
    {
	$array = [
	    "a" => 0,
	    "b" => 1
	];
	$this->assertSame(try_get($array, "a"), 0);
	$this->assertSame(try_get($array, "d"), "");
	$this->assertSame(try_get($array, "a", "", 42), "");
	$array["id"] = 42;
	$this->assertSame(try_get($array, "a", "", 42), 0);
	$this->assertSame(try_get($array, "a", "", 50), "");
    }

    public function testIsSymbol()
    {
	$this->assertSame(is_symbol("!"), false);
	$this->assertSame(is_symbol("0abc"), false);
	$this->assertSame(is_symbol("abc0"), true);
	$this->assertSame(is_symbol("abc_42"), true);
	$this->assertSame(@is_symbol($lel), false);
    }

    public function testIsNumber()
    {
	$this->assertSame(is_number("42"), true);
	$this->assertSame(is_number("x42"), false);
	$this->assertSame(is_number("42x"), false);
	$this->assertSame(@is_number($lel), false);
    }

    public function testHourToTimestamp()
    {
	$this->assertSame(hour_to_timestamp("1:2:3"), 1 * 60 * 60 + 2 * 60 + 3);
	$this->assertSame(hour_to_timestamp("1:2"), 1 * 60 * 60 + 2 * 60);
	$this->assertSame(hour_to_timestamp("1"), 1 * 60 * 60);
	$this->assertSame(hour_to_timestamp(""), 0);

	$this->assertSame(day_to_timestamp("12"), -1);
	$this->assertSame(day_to_timestamp("2020-12-30") + 24 * 60 * 60, day_to_timestamp("2020-12-31"));

	$this->assertTrue(day_to_timestamp(date("Y-m-d", time())) - time() < 2);

	$this->assertTrue(date_to_timestamp(date("Y-m-d H:i:s", time())) - time() < 2);
    }
}
