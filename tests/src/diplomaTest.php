<?php

require_once ("xtestcase.php");

class DiplomaTest extends XTestCase
{
    public function testSafeFilenameKeepsReadableCertificationName()
    {
        $this->assertSame(
            "Développeur-web-full stack.png",
            diploma_safe_filename(" Développeur/web:full stack. ")
        );
    }

    public function testFontValidationChecksTypeSignatureAndSize()
    {
        $ttf = "\x00\x01\x00\x00".str_repeat("x", 8);
        $otf = "OTTO".str_repeat("x", 8);
        $this->assertTrue(diploma_font_is_valid("school.ttf", $ttf));
        $this->assertTrue(diploma_font_is_valid("school.otf", $otf));
        $this->assertFalse(diploma_font_is_valid("school.txt", $ttf));
        $this->assertFalse(diploma_font_is_valid("school.ttf", "invalid"));
    }

    public function testSchoolDefaultLeavesCertificationAndRecipientToOverrides()
    {
        $configuration = diploma_school_default_configuration([
            "codename" => "demo-school",
            "name" => "Demo School",
            "is_school" => 1,
        ]);
        $this->assertStringContainsString("document_logo.png", $configuration);
        $this->assertStringContainsString("diploma_title_font.dab", $configuration);
        $this->assertStringNotContainsString("Main =", $configuration);
        $this->assertStringNotContainsString("Recipient", $configuration);
    }
}
