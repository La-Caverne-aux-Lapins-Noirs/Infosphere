<?php

require_once ("xtestcase.php");
require_once ("../pages/matter/add_matter.php");
require_once ("../pages/matter/fetch_matter.php");
require_once ("../pages/matter/print_matter.php");
require_once ("../pages/matter/print_header.php");

class MattersTest extends XTestCase
{
    public function testHandleRequest()
    {
	global $Dictionnary;
	global $Database;
	global $_POST;

	clear_table(["matter", "matter_school_year", "matter_medal", "medal", "school_year"]);

	$_POST["action"] = "add";
	$_POST["codename"] = "french";
	$_POST["credit"] = 42;
	$_POST["fr_name"] = "Français";
	$request = new Response;
	$LogMsg = "";
	require ("../pages/matter/handle_request.php");
	$this->assertSame($LogMsg, "MatterAdded");
	$this->assertFalse($request->is_error());

	$_POST["action"] = "add";
	$_POST["codename"] = "english";
	$_POST["credit"] = 42;
	$_POST["fr_name"] = "Anglais";
	$request = new Response;
	$LogMsg = "";
	require ("../pages/matter/handle_request.php");
	$this->assertSame($LogMsg, "MatterAdded");
	$this->assertFalse($request->is_error());

	$_POST["action"] = "delete";
	$_POST["id"] = "english";
	$request = new Response;
	$LogMsg = "";
	require ("../pages/matter/handle_request.php");
	$this->assertSame($LogMsg, "MatterDeleted");
	$this->assertFalse($request->is_error());

	$_POST["action"] = "delete";
	$_POST["id"] = "blob";
	$request = new Response;
	require ("../pages/matter/handle_request.php");
	$this->assertTrue($request->is_error());
	$this->assertSame($request->label, "BadCodeName");
	$this->assertSame($request->details, "blob");

	copy("./res/medal.png", "./medal.png");
	$m = try_insert("medal", "medalx", [], "./medal.png", "./dres/groups/",
			["name", "description"], ["fr_name" => "MédailleX", "fr_description" => "D"]);
	$this->assertSame($m->is_error(), false);
	copy("./res/medal.png", "./medal.png");
	$m = try_insert("medal", "medaly", [], "./medal.png", "./dres/groups/",
			["name", "description"], ["fr_name" => "MédailleY", "fr_description" => "D"]);
	$this->assertSame($m->is_error(), false);
	copy("./res/medal.png", "./medal.png");
	$m = try_insert("medal", "medalz", [], "./medal.png", "./dres/groups/",
			["name", "description"], ["fr_name" => "MédailleZ", "fr_description" => "D"]);
	$this->assertSame($m->is_error(), false);

	$_POST["action"] = "add_medal";
	unset($_POST["medal"]);
	$request = new Response;
	$LogMsg = "";
	require ("../pages/matter/handle_request.php");
	$this->assertSame($LogMsg, "MatterModified");
	$this->assertSame($request->label, "MissingId");

	$_POST["action"] = "add_medal";
	$_POST["medal"] = "!";
	$_POST["id"] = "french";
	$request = new Response;
	$LogMsg = "";
	require ("../pages/matter/handle_request.php");
	$this->assertSame($LogMsg, "MatterModified");
	$this->assertSame($request->label, "InvalidParameter");
	$this->assertSame($request->details, "!");

	$_POST["action"] = "add_medal";
	$_POST["medal"] = "medalx";
	$_POST["id"] = "blob";
	$request = new Response;
	$LogMsg = "";
	require ("../pages/matter/handle_request.php");
	$this->assertSame($LogMsg, "MatterModified");
	$this->assertSame($request->label, "BadCodeName");
	$this->assertSame($request->details, "blob");

	$_POST["action"] = "add_medal";
	$_POST["medal"] = "medalx;medaly;medalz";
	$_POST["id"] = "french";
	$request = new Response;
	$LogMsg = "";
	require ("../pages/matter/handle_request.php");
	$this->assertSame($LogMsg, "MatterModified");
	$this->assertFalse($request->is_error());

	$_POST["action"] = "add_medal";
	$_POST["medal"] = "-medaly";
	$_POST["id"] = "french";
	$request = new Response;
	$LogMsg = "";
	require ("../pages/matter/handle_request.php");
	$this->assertSame($LogMsg, "MatterModified");
	$this->assertFalse($request->is_error());

	$ret = db_select_all("* FROM matter_medal ORDER BY id_medal");
	$this->assertSame(count($ret), 2);
	$this->assertSame($ret[0]["id_matter"], 1);
	$this->assertSame($ret[0]["id_medal"], 1);
	$this->assertSame($ret[1]["id_matter"], 1);
	$this->assertSame($ret[1]["id_medal"], 3);

	$m = add_school_year("promo", 20, "1/2/2020");
	$this->assertSame($m->is_error(), false);
	$m = add_link("french", "promo", "matter", "school_year");
	$this->assertSame($m->is_error(), false);

	$_POST["action"] = "add_cycle";
	$_POST["id"] = "french";
	$request = new Response;
	require ("../pages/matter/handle_request.php");
	$this->assertSame($request->label, "MissingId");

	$_POST["action"] = "add_cycle";
	$_POST["id"] = "french";
	$_POST["cycle"] = "!";
	$request = new Response;
	require ("../pages/matter/handle_request.php");
	$this->assertSame($request->label, "InvalidParameter");
	$this->assertSame($request->details, "!");

	$_POST["action"] = "add_cycle";
	$_POST["id"] = "french";
	$_POST["cycle"] = "promo";
	$request = new Response;
	$LogMsg = "";
	require ("../pages/matter/handle_request.php");
	$this->assertSame($LogMsg, "MatterModified");
	$this->assertFalse($request->is_error());
    }

    public function testAddAndFetch()
    {
	clear_table(["matter", "matter_school_year", "matter_medal", "medal", "school_year"]);

	$ret = @add_matter($lel, 10, []);
	$this->assertSame($ret->label, "MissingCodeName");
	$ret = @add_matter("!", 10, []);
	$this->assertSame($ret->label, "InvalidParameter");
	$ret = @add_matter("french", $lel, []);
	$this->assertSame($ret->label, "MissingCredit");
	$ret = @add_matter("french", -5, []);
	$this->assertSame($ret->label, "BadCredit");
	$this->assertSame($ret->details, -5);

	$ret = @add_matter("french", 10, []);
	$this->assertSame($ret->label, "MissingField");
	$this->assertSame($ret->details, "Français 'name'");

	$ret = @add_matter("french", 10, ["fr_name" => "Francais"]);
	$this->assertSame($ret->is_error(), false);

	$ret = @add_matter("french", 10, ["fr_name" => "Francais"]);
	$this->assertSame($ret->label, "CodeNameAlreadyUsed");
	$this->assertSame($ret->details, "french");

	copy("./res/medal.png", "./medal.png");
	$m = try_insert("medal", "medalx", [], "./medal.png", "./dres/groups/",
			["name", "description"], ["fr_name" => "Médaille", "fr_description" => "D"]);
	$this->assertSame($m->is_error(), false);
	$m = add_link("french", "medalx", "matter", "medal");
	$this->assertSame($m->is_error(), false);

	$m = add_school_year("promo", 20, "1/2/2020");
	$this->assertSame($m->is_error(), false);
	$m = add_link("french", "promo", "matter", "school_year");
	$this->assertSame($m->is_error(), false);

	$ret = @add_matter("english", 10, ["fr_name" => "Anglais"]);
	$this->assertSame($ret->is_error(), false);

	$matter = fetch_matter("french");
	$this->assertFalse($matter->is_error());
	$matter = $matter->value;
	$this->assertSame($matter["codename"], "french");
	$this->assertSame($matter["name"], "Francais");
	$this->assertSame(count($matter["medals"]), 1);
	$this->assertSame($matter["medals"][0]["codename"], "medalx");
	$this->assertSame($matter["medals"][0]["name"], "Médaille");
	$this->assertSame(count($matter["cycles"]), 1);
	$this->assertSame($matter["cycles"][0]["codename"], "promo");

	$matter = fetch_matter();
	$this->assertFalse($matter->is_error());
	$matter = $matter->value;
	$this->assertSame(count($matter), 2);
	$this->assertSame($matter[0]["codename"], "french");
	$this->assertSame($matter[1]["codename"], "english");

	ob_start();
	print_header();
	print_matter($matter[0], 0);
	print_matter($matter[1], 1);
	ob_end_clean();
    }
}
