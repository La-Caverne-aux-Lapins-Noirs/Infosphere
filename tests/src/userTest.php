<?php

require_once ("xtestcase.php");
require_once ("../pages/groups/fetch_groups.php");
require_once ("../pages/users/fetch_users.php");

class UsersTest extends XTestCase
{
    public function testRequest()
    {
	global $Database;
	global $_POST;

	clear_table("user");
	$this->AddRealUser();
	$_POST["action"] = "set_status";
	$_POST["user"] = "Mordanis";
	$_POST["authority"] = 5;
	require ("../pages/users/handle_request.php");
	$this->assertSame($request->is_error(), false);
	$ret = db_select_one("* FROM user");
	$this->assertSame($ret["authority"], 5);

	$_POST["action"] = "delete";
	$_POST["user"] = "Mordanis";
	unset($_POST["authority"]);
	require ("../pages/users/handle_request.php");
	$this->assertSame($request->is_error(), false);
	$ret = db_select_one("* FROM user");
	$this->assertSame($ret["authority"], -1);

	$Database->query("UPDATE user SET codename = 'Mordanis', authority = 6 WHERE id = 1");
	$ret = fetch_users();
	$this->assertSame($ret["Mordanis"]["codename"], "Mordanis");
	$this->assertSame($ret["Mordanis"]["id"], 1);
	$this->assertFalse(isset($ret["Mordanis"]["cycle"]));

	clear_table("user");
    }
}
