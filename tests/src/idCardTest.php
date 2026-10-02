<?php

require_once ("xtestcase.php");

class IdCardTest extends XTestCase
{
    public function testSafeFilenameKeepsReadableSchoolCode()
    {
        $this->assertSame(
            "carte_etudiante-efrits.png",
            id_card_safe_filename(" carte_etudiante/efrits. ")
        );
    }

    public function testSchoolYearStringSwitchesInSeptember()
    {
        $this->assertSame("2025-2026", id_card_school_year_string(strtotime("2026-05-10 12:00:00")));
        $this->assertSame("2026-2027", id_card_school_year_string(strtotime("2026-09-10 12:00:00")));
    }

    public function testStudyYearNormalization()
    {
        $this->assertSame("3", id_card_normalize_study_year("03"));
        $this->assertSame("5", id_card_normalize_study_year(5));
        $this->assertSame("", id_card_normalize_study_year(""));
        $this->assertNull(id_card_normalize_study_year("0"));
        $this->assertNull(id_card_normalize_study_year("EF3"));
        $this->assertNull(id_card_normalize_study_year("21"));
    }

    public function testAddressSplitUsesLastLineAsCity()
    {
        $parts = id_card_split_address("32a Avenue Pierre Sémard\n94200 Ivry-sur-Seine");
        $this->assertSame("32a Avenue Pierre Sémard", $parts["address"]);
        $this->assertSame("94200 Ivry-sur-Seine", $parts["city"]);
    }

    public function testSkipSlotParserSupportsRanges()
    {
        $this->assertSame([1, 2, 4, 5, 6], id_card_parse_skip_slots("1, 2, 4-6"));
    }


    public function testQuotedTextKeepsApostrophesAndAccents()
    {
        $config = id_card_parse_configuration_text(
            "[Card\n".
            "  [StudentCardTitle\n".
            "    Text = \"CARTE D'ÉTUDIANT\"\n".
            "  ]\n".
            "]\n"
        );
        $this->assertSame("CARTE D'ÉTUDIANT", $config["Card"]["StudentCardTitle"]["Text"]);
    }

    public function testDefaultConfigurationContainsBackgroundAndSheet()
    {
        $configuration = id_card_school_default_configuration([
            "codename" => "demo-school",
            "name" => "Demo School",
        ]);
        $this->assertStringContainsString("id_card_background.png", $configuration);
        $this->assertStringContainsString("[Sheet", $configuration);
        $this->assertStringContainsString("[StudentPhoto", $configuration);
        $this->assertStringContainsString("[StudentCardTitle", $configuration);
        $this->assertStringContainsString("Text = \"CARTE D'ÉTUDIANT\"", $configuration);
        $this->assertStringContainsString("[StudentName", $configuration);
        $this->assertStringContainsString("[SchoolYear", $configuration);
        $this->assertStringContainsString("[Course", $configuration);
        $this->assertStringContainsString("Prefix = \"Année \"", $configuration);
        $this->assertStringContainsString("[SchoolName", $configuration);
        $this->assertStringContainsString("[SchoolAddress", $configuration);
        $this->assertStringContainsString("[SchoolCity", $configuration);
    }
}
