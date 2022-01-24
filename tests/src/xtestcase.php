<?php

use PHPUnit\Framework\TestCase;

class XTestCase extends TestCase
{
    public $user = NULL;

    public function AddRealUser()
    {
	global $User;

	if ($this->user)
	    return (["User" => $this->user, "Error" => ""]);

	$return = try_subscribe("Mordanis", "mordanis@commodore.com", "TheMightyPassword789", "TheLol");
	$this->assertSame($return["Error"], "PasswordDoesNotMatch");

	$return = try_subscribe("Mordanis", "mordanis@commodore.com", "TheMightyPassword789", "TheMightyPassword789");
	if ($return["Error"] == "LoginAndMailUsed" || $return["Error"] == "LoginUsed" || $return["Error"] == "MailUsed")
	    $return = get_login_info("Mordanis", "TheMightyPassword789", true);
	$User = $this->user = $return["User"];
	$User["authority"] = 6;
	return ($return);
    }

    public function testAddRealUser()
    {
	$ret = $this->AddRealUser();
	$ret2 = $this->AddRealUser();
	$this->assertSame($ret, $ret2);
    }
}
