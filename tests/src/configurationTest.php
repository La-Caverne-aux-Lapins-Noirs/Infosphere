<?php

require_once ("xtestcase.php");
require_once ("../tools/configuration.php");

class ConfigurationTest extends XTestCase
{
    public function testCtor()
    {
	$c = new CConfiguration;
	$this->assertSame($c->MedalsDir, "./dres/medals/");
	$this->assertSame($c->GroupsDir, "./dres/groups/");
	$this->assertSame($c->AvatarsDir, "./dres/avatars/");
	$this->assertSame($c->ELearningDir, "./dres/elearning/");
	$this->assertSame($c->StudentFilesDir, "./dres/student_files/");
    }
}

