<?php

require_once ("xtestcase.php");
require_once ("../pages/fetch_activities.php");
require_once ("../pages/activities/manage_activity.php");

class ActivityTest extends XTestCase
{
    public function testActivities()
    {
	global $Database;
	global $ActivityType;
	global $User;

	clear_table("user");
	clear_table("activity");
	clear_table("activity_medal");
	clear_table("medal");
	load_constants();
	$this->AddRealUser();

	$lng = [
	    "fr_name" => "module parent",
	    "fr_description" => "description module parent"
	];
	$ret = add_activity("!", $lng, $ActivityType["Module"]);
	$this->assertSame($ret->label, "InvalidParameter");

	$_POST["action"] = "add";
	$_POST["codename"] = "module";
	$_POST["type"] = "Module";
	$_POST["min_team_size"] = 1;
	$_POST["parent_activity"] = "";
	$_POST["fr_name"] = $lng["fr_name"];
	$_POST["fr_description"] = $lng["fr_description"];
	require ("../pages/activities/handle_request.php");
	$this->assertSame($LogMsg, "ActivityAdded");
	$this->assertSame($request->is_error(), false);
	$ret = db_select_one("enabled FROM activity WHERE codename = 'module'");
	$this->assertSame($ret["enabled"], 0);

	$_POST["action"] = "enable";
	$_POST["codename"] = "module";
	require ("../pages/activities/handle_request.php");
	$this->assertSame($LogMsg, "ActivityModified");
	$this->assertSame($request->is_error(), false);
	$ret = db_select_one("enabled FROM activity WHERE codename = 'module'");
	$this->assertSame($ret["enabled"], 1);

	$_POST["action"] = "disable";
	$_POST["codename"] = "module";
	require ("../pages/activities/handle_request.php");
	$this->assertSame($LogMsg, "ActivityModified");
	$this->assertSame($request->is_error(), false);
	$ret = db_select_one("enabled FROM activity WHERE codename = 'module'");
	$this->assertSame($ret["enabled"], 0);

	$lng = [
	    "fr_name" => "activité",
	    "fr_description" => "description activité"
	];
	$ret = add_activity("module", $lng, $ActivityType["Module"]);
	$this->assertSame($ret->label, "CodeNameAlreadyUsed");

	$ret = add_activity("tp", $lng, $ActivityType["Debate"], 1, "!");
	$this->assertSame($ret->label, "InvalidParameter");

	$ret = @add_activity("tp", $lng, $lel, 1, "module");
	$this->assertSame($ret->label, "MissingActivityType");

	$ret = @add_activity("tp", $lng, 50, 1, "module");
	$this->assertSame($ret->label, "BadActivityType");

	$ret = @add_activity("tp", $lng, "blob", 1, "module");
	$this->assertSame($ret->label, "BadActivityType");

	$ret = @add_activity("tp", $lng, "Debate", $lel, "module");
	$this->assertSame($ret->label, "MissingTeamSize");

	$ret = @add_activity("tp", $lng, "Debate", 42, "module");
	$this->assertSame($ret->label, "BadTeamSize");

	$ret = add_activity("tp", $lng, "Debate", 1, "module");
	$this->assertSame($ret->is_error(), false);

	$lng = [
	    "fr_name" => "médaille",
	    "fr_description" => "description médaille"
	];
	copy("./res/medal.png", "./medal.png");
	$ret = try_insert("medal", "the_medal", [], "./medal.png", "./dres/medals/",
			  ["name", "description"], $lng);
	$this->assertSame($ret->is_error(), false);

	$_POST["action"] = "add_medal";
	$_POST["codename"] = "tp";
	$_POST["medal"] = "the_medal";
	require ("../pages/activities/handle_request.php");
	$this->assertSame($LogMsg, "ActivityModified");
	$this->assertSame($request->is_error(), false);

	$ret = fetch_activities();
	$this->assertSame($ret->is_error(), false);
	$ret = $ret->value;
	$this->assertSame(count($ret), 1);
	$this->assertSame($ret[0]["codename"], "module");
	$this->assertSame($ret[0]["name"], "module parent");
	$this->assertSame($ret[0]["medal"], []);
	$this->assertSame($ret[0]["activity"][0]["codename"], "tp");
	$this->assertSame($ret[0]["activity"][0]["description"], "description activité");
	$this->assertSame($ret[0]["activity"][0]["activity"], []);
	$this->assertSame($ret[0]["activity"][0]["medal"][0]["codename"], "the_medal");

	$_POST["action"] = "delete";
	$_POST["codename"] = "module";
	require ("../pages/activities/handle_request.php");
	$this->assertSame($LogMsg, "ActivityDeleted");
	$this->assertSame($request->is_error(), false);
	$ret = db_select_one("codename, deleted FROM activity WHERE id = 1");
	$this->assertSame($ret["deleted"], 1);
	$this->assertTrue($ret["codename"] != "module");
	$this->assertTrue(!!strstr($ret["codename"], "module"));
    }
}

