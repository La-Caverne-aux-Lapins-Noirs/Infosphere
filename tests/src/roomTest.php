<?php

require_once ("xtestcase.php");
require_once ("../pages/rooms_admin/add_room.php");
require_once ("../pages/rooms_admin/display_room.php");
require_once ("../pages/fetch_rooms.php");

class RoomTest extends XTestCase
{
    public function testRoom()
    {
	global $Database;

	clear_table("room");
	$lng = ["fr_name" => "Espace profond 9"];

	$ret = @add_room($lel, "", "", "", $lng);
	$this->assertSame($ret->label, "MissingCodeName");
	$ret = add_room("-!@", "", "", "", $lng);
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!@");
	$ret = add_room("DeepSpaceNine", "", "", "", $lng);
	$this->assertSame($ret->label, "MissingCapacity");
	$ret = add_room("DeepSpaceNine", "ABC", "", "", $lng);
	$this->assertSame($ret->label, "InvalidCapacity");

	$ret = add_room("DeepSpaceNine", 42, "./res/room.png", "./res/room.dab", $lng);
	$Database->query("UPDATE room_desk SET status = 1, id_user = 1 WHERE codename = 'Promenade'");
	$this->assertSame($ret->is_error(), false);
	$this->assertNotNull($rooms = fetch_rooms(-1, true));
	$rooms = $rooms->value;
	$this->assertSame($rooms["DeepSpaceNine"]["occupied"], 1);
	$this->assertSame($rooms["DeepSpaceNine"]["capacity"], 42);
	$this->assertSame($rooms["DeepSpaceNine"]["name"], "Espace profond 9");
	$this->assertSame($rooms["DeepSpaceNine"]["id"], 1);
	$this->assertSame($rooms["DeepSpaceNine"]["desk"][1]["id_user"], 1);
	$this->assertSame($rooms["DeepSpaceNine"]["desk"][1]["user"], "Mordanis");

	$ret = add_room("DeepSpaceNine", 42, "./res/room.png", "./res/room.dab", $lng);
	$this->assertSame($ret->label, "CodeNameAlreadyUsed");
	$this->assertSame($ret->details, "DeepSpaceNine");
	$ret = @add_room("DeepSpaceNineX", 42, $lel, $lel, []);
	@$this->assertSame($ret->label, "MissingField");
	@$this->assertSame($ret->details, "Français 'name'");

	$ret = fetch_rooms("!");
	$this->assertTrue($ret->is_error());

	$_GET["a"] = "DeepSpaceNine";
	require ("../pages/rooms/handle_parameters.php");
	$this->assertSame($fetch->value["codename"], "DeepSpaceNine");
	$this->assertSame($sort, false);

	ob_start();
	display_room(fetch_rooms("DeepSpaceNine"));
	ob_end_clean();
    }

    public function testHandleRequest()
    {
	global $ErrorMsg;
	global $LogMsg;
	global $_FILES;
	global $_POST;
	global $User;

	clear_table("room");
	$_POST["action"] = "add";
	$_POST["codename"] = "DeepSpaceNine";
	$_POST["capacity"] = 42;
	$_POST["fr_name"] = "Espace profond 9";
	$_FILES["map"]["tmp_name"] = "./res/room.png";
	$_FILES["conf"]["tmp_name"] = "./res/room.dab";
	$request = "";
	$ErrorMsg = "";
	$LogMsg = "";
	require ("../pages/rooms_admin/handle_request.php");
	$this->assertSame($request->is_error(), false);
	$this->assertSame($LogMsg, "RoomAdded");
	$this->assertNotNull($rooms = fetch_rooms(-1, true));
	$rooms = $rooms->value;
	$this->assertSame($rooms["DeepSpaceNine"]["capacity"], 42);
	$this->assertSame($rooms["DeepSpaceNine"]["name"], "Espace profond 9");
	$this->assertSame($rooms["DeepSpaceNine"]["id"], 1);

	$_POST["action"] = "delete";
	$_POST["id"] = "DeepSpaceNine";
	unset($_FILES);
	$request = "";
	$ErrorMsg = "";
	require ("../pages/rooms_admin/handle_request.php");
	$this->assertSame($LogMsg, "RoomDeleted");
	$this->assertSame($request->is_error(), false);

	$this->assertNotNull($rooms = fetch_rooms(-1, true));
	$rooms = $rooms->value;
	$this->assertSame(count($rooms), 0);

	$_POST["action"] = "delete";
	$_POST["id"] = "!";
	$request = "";
	require ("../pages/rooms_admin/handle_request.php");
	$this->assertTrue($request->is_error());

	clear_table("room");
	$_POST["action"] = "delete";
	$_POST["id"] = 1;
	$request = "";
	require ("../pages/rooms_admin/handle_request.php");
	$this->assertSame($request->label, "CannotDeleteNoRoom");

	$_POST["action"] = "delete";
	$_POST["id"] = "no_room";
	$request = "";
	require ("../pages/rooms_admin/handle_request.php");
	$this->assertSame($request->label, "CannotDeleteNoRoom");
    }
}
