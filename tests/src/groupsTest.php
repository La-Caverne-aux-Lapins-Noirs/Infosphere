<?php

require_once ("xtestcase.php");
require_once ("../pages/groups/fetch_groups.php");

class GroupsTest extends XTestCase
{
    public function testParameters()
    {
	global $_GET;
	global $group;

	$_GET["a"] = 42;
	require ("../pages/groups/handle_parameters.php");
	$this->assertSame($group, NULL);

	unset($_GET);
	require ("../pages/groups/handle_parameters.php");
	$this->assertSame(count($fetch->value), 0);
    }

    public function testHandleRequest()
    {
	clear_table("user");
	clear_table("laboratory");
	$this->AddRealUser();

	global $_POST;
	global $LogMsg;
	global $request;

	$_POST["action"] = "add_group";
	$_POST["codename"] = "new_group";
	$_FILES["icon"]["name"] = "./icon.png";
	$_FILES["icon"]["tmp_name"] = $_FILES["icon"]["name"];
        $_POST["fr_name"] = "Nouveau groupe";
        $_POST["fr_description"] = "Nouvelle description";
	copy("./res/medal.png", "./icon.png");
	require ("../pages/groups/handle_request.php");
	$this->assertSame($LogMsg, "GroupAdded");
	$this->assertSame($request->is_error(), false);

	$_POST["action"] = "add_member";
	$_POST["laboratory"] = "new_group";
	$_POST["user"] = "Mordanis";
	require ("../pages/groups/handle_request.php");
	$this->assertSame($LogMsg, "MembersAdded");
	$this->assertSame($request->is_error(), false);

	$ret = fetch_groups(-1, true);
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value["new_group"]["codename"], "new_group");
	$this->assertSame(count($ret->value["new_group"]["user"]), 1);
	$this->assertSame($ret->value["new_group"]["user"]["Mordanis"]["codename"], "Mordanis");

	$_POST["action"] = "remove_member";
	$_POST["id_laboratory"] = "new_group";
	$_POST["logins"] = "Mordanis";
	require ("../pages/groups/handle_request.php");
	$this->assertSame($LogMsg, "MembersRemoved");
	$this->assertSame($request->is_error(), false);

	$ret = fetch_groups(-1, false);
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value[0]["codename"], "new_group");
	$this->assertSame(count($ret->value[0]["user"]), 0);

	$ret = fetch_groups("new_group", false);
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value["codename"], "new_group");
	$this->assertSame(count($ret->value["user"]), 0);

	$_POST["action"] = "delete_group";
	$_POST["id_laboratory"] = "new_group";
	require ("../pages/groups/handle_request.php");
	$this->assertSame($LogMsg, "GroupDeleted");
	$this->assertSame($request->is_error(), false);

	$ret = fetch_groups(-1, false);
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value, []);
    }
}


