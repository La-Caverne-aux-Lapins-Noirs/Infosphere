<?php

require_once ("xtestcase.php");
require_once ("../pages/users/fetch_users.php");

class UserTest extends XTestCase
{
    public function testFetchUser()
    {
	global $Database;

	clear_table("user");
	clear_table("school_year");
	clear_table("user_school_year");
	$return = subscribe("User1", "a@b.c", "TheMightyPassword789");
	$this->assertSame($return["Error"], "");
	$return = subscribe("User2", "a@c.c", "TheMightyPassword789");
	$this->assertSame($return["Error"], "");
	$return = subscribe("User3", "a@d.c", "TheMightyPassword789");
	$this->assertSame($return["Error"], "");
	update_table("user", "User2", ["authority" => -1]);
	$this->assertSame($return["Error"], "");

	$get = fetch_users();
	$this->assertSame(count($get), 2);
	$this->assertSame($get["User1"]["id"], 1);
	$this->assertSame($get["User3"]["id"], 3);
	$Database->query("
           INSERT INTO
           school_year (id, codename, cycle, done)
           VALUES (1, 'year0', 0, 0)
	");
	global $User;

	$User["id"] = 42;
	add_link("User3", "year0", "user", "school_year");
	$get = fetch_users(["codename", "cycle"]);
	$this->assertSame(count($get), 2);
	$this->assertSame(isset($get["User3"]["authority"]), false);
	$this->assertSame(isset($get["User3"]["codename"]), true);
	$this->assertSame(count($get["User3"]["cycle"]), 1);
	$this->assertSame($get["User3"]["cycle"]["year0"]["id"], 1);
    }

    public function testSubscribe()
    {
	clear_table("user");
	$return = subscribe("M", "mordanis@commodore.com", "TheMightyPassword789");
	$this->assertSame($return["Error"], "BadLogin");

	$return = subscribe("Mordanis", "mordanis@commodore.com", "The");
	$this->assertSame($return["Error"], "BadPassword");

	$return = subscribe("Mordanis", "mordanis@commodore.com", "TheMightyPassword");
	$this->assertSame($return["Error"], "BadPassword");

	$return = subscribe("Mordanis", "mordaniscommodore.com", "TheMightyPassword789");
	$this->assertSame($return["Error"], "BadMail");

	$return = $this->AddRealUser();
	$return = $this->AddRealUser();
	$this->assertSame($return["Error"], "");
	$this->assertSame($return["User"]["id"], 1);
	$this->assertSame($return["User"]["codename"], "Mordanis");
	$this->assertSame($return["User"]["mail"], "mordanis@commodore.com");

	$return = subscribe("Damdoshi", "mordanis@commodore.com", "TheMightyPassword789");
	$this->assertSame($return["Error"], "MailUsed");

	$return = subscribe("Mordanis", "damdoshi@commodore.com", "TheMightyPassword789");
	$this->assertSame($return["Error"], "LoginUsed");

	$return = subscribe("Mordanis", "mordanis@commodore.com", "TheMightyPassword789");
	$this->assertSame($return["Error"], "LoginAndMailUsed");

	$return = subscribe("Mordanis", "mordanis@commodore.com", "TheMightyPassword789");
	$this->assertSame($return["Error"], "LoginAndMailUsed");
    }

    public function testGetLoginInfo()
    {
	global $Database;

	clear_table("user");
	$this->AddRealUser();

	$return = get_login_info("Mordanis", "TheMightyPassword789", true);
	$this->assertSame($return["User"]["codename"], "Mordanis");
	$this->assertSame($return["Error"], "");

	$q = $Database->query("SELECT local_salt FROM user WHERE codename = 'Mordanis'");
	$q = $q->fetch_assoc();
	$q = base64_decode($q["local_salt"]);
	$q = hash_method($q."TheMightyPassword789");
	$return = get_login_info("Mordanis", $q, false);
	$this->assertSame($return["User"]["codename"], "Mordanis");

	$return = get_login_info("Damdoshi", "", true);
	$this->assertSame($return["Error"], "UnknownLogin");

	$Database->query("UPDATE user SET authority = -1 WHERE codename = 'Mordanis'");
	$return = get_login_info("Mordanis", $q, false);
	$this->assertSame($return["Error"], "BannedAccount");

	$Database->query("UPDATE user SET authority = 0 WHERE codename = 'Mordanis'");
	$return = get_login_info("Mordanis", "TheMighty", true);
	$this->assertSame($return["Error"], "InvalidPassword");
    }

    public function testRegeneratePassword()
    {
	$usr["id"] = 42;
	$ret = regenerate_password($usr, "TheNewPassword123");
	$this->assertSame($ret["Error"], "UnknownId");

	$usr = $this->AddRealUser();
	$ret = regenerate_password($usr["User"], "TheNewPassword123");
	$this->assertNotNull($final_pass = $ret["Password"]);
    }

    public function testSetUserAttribute()
    {
	$this->assertSame(set_user_attributes(NULL, NULL), ["User" => NULL, "Error" => "InvalidParameter"]);

	$usr = $this->AddRealUser();
	$new["password"] = "TheNewpassword123";
	$new["first_name"] = "Jason Brillante";
	$new["mail"] = "mordanis@atari.com";
	$ret = set_user_attributes($usr["User"], $new);
	$this->assertSame($ret["Error"], "");
	$this->assertSame($ret["User"]["codename"], "Mordanis");
	$this->assertSame($ret["User"]["mail"], "mordanis@atari.com");
	$this->assertSame($ret["User"]["first_name"], "Jason Brillante");

	$new["mail"] = "mordanisatari.com";
	$ret = set_user_attributes($usr["User"], $new);
	$this->assertSame($ret, ["User" => NULL, "Error" => "BadMail"]);

	// On restore
	$new["password"] = "TheMightyPassword789";
	$new["first_name"] = "Jason Brillante";
	$new["mail"] = "mordanis@commodore.com";
	$ret = set_user_attributes($usr["User"], $new);
	$this->assertSame($ret["Error"], "");
	$this->assertSame($ret["User"]["codename"], "Mordanis");
	$this->assertSame($ret["User"]["mail"], "mordanis@commodore.com");

	$ret = set_user_attributes($usr["User"], []);
	$this->assertSame($ret, ["User" => $usr["User"], "Error" => ""]);

	$ret = set_user_data("lel", ["authority" => 4, "salt" => "x"]);
	$this->assertSame($ret->label, "BadCodeName");
	$this->assertSame($ret->details, "lel");
	$ret = @set_user_data("Mordanis", $lel);
	$this->assertSame($ret->label, "MissingParameter");
	$ret = db_select_one("* FROM user WHERE codename = 'Mordanis'");
	$this->assertNotEquals($ret['salt'], "x");
    }
}
