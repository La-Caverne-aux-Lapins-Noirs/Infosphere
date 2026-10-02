<?php

require_once ("xtestcase.php");

class FormFieldTest extends XTestCase
{
    public function testStableChoiceValues()
    {
        $field = form_field_definition([
            "type" => "radio",
            "choices" => ["C", "C++ moderne"],
            "choice_values" => ["c", "cpp"],
        ]);
        $this->assertTrue($field["valid"]);
        $this->assertSame($field["choice_values"], ["c", "cpp"]);
        $this->assertSame(form_field_choice_label($field, "cpp"), "C++ moderne");
        $this->assertSame(form_field_normalize_values($field, "cpp"), ["cpp"]);
        $this->assertSame(form_field_normalize_values($field, "C++ moderne"), []);
    }

    public function testSelectableFieldRequiresStableValues()
    {
        $field = form_field_definition([
            "type" => "radio",
            "choices" => ["Oui", "Non"],
        ]);
        $this->assertFalse($field["valid"]);
        $this->assertContains("choice_count", $field["errors"]);
        $this->assertSame($field["choice_values"], []);
    }

    public function testDuplicateStableValuesAreInvalid()
    {
        $field = form_field_definition([
            "type" => "checkbox",
            "choices" => ["A", "B"],
            "choice_values" => ["same", "same"],
        ]);
        $this->assertFalse($field["valid"]);
        $this->assertContains("duplicate_choice_value", $field["errors"]);
    }

    public function testCheckboxNormalizationAndRequired()
    {
        $field = [
            "type" => "checkbox",
            "required" => true,
            "choices" => ["C", "C++", "Rust"],
            "choice_values" => ["c", "cpp", "rust"],
        ];
        $this->assertSame(form_field_storage_value($field, ["cpp", "bad", "c", "cpp"]), ["cpp", "c"]);
        $this->assertTrue(form_field_missing_required($field, []));
        $this->assertFalse(form_field_missing_required($field, ["rust"]));
    }

    public function testScaleDefaults()
    {
        $field = form_field_definition(["type" => "scale"]);
        $this->assertSame($field["choices"], ["1", "2", "3", "4", "5"]);
        $this->assertSame($field["choice_values"], ["1", "2", "3", "4", "5"]);
    }


    public function testScaleWithCustomLabelsRequiresStableNumericValues()
    {
        $field = form_field_definition([
            "type" => "scale",
            "choices" => ["Faible", "Fort"],
        ]);
        $this->assertFalse($field["valid"]);
        $this->assertContains("choice_count", $field["errors"]);
    }

    public function testScaleRejectsNonNumericStableValues()
    {
        $field = form_field_definition([
            "type" => "scale",
            "choices" => ["Faible", "Fort"],
            "choice_values" => ["low", "high"],
        ]);
        $this->assertFalse($field["valid"]);
        $this->assertContains("scale_non_numeric", $field["errors"]);
    }

    public function testRendererEscapesLabelsAndUsesStableValue()
    {
        $html = form_field_render([
            "type" => "radio",
            "choices" => ["<C++>"],
            "choice_values" => ["cpp"],
        ], "cpp", ["name" => "answer[Language]"]);
        $this->assertStringContainsString('value="cpp"', $html);
        $this->assertStringContainsString('&lt;C++&gt;', $html);
        $this->assertStringContainsString('checked', $html);
        $this->assertStringNotContainsString('<C++>', $html);
    }

    public function testDabsicFlattenKeepsLists()
    {
        $flat = [];
        dabsic_form_flatten_values(["Form" => ["Languages" => ["c", "cpp"]]], "", $flat);
        $this->assertSame($flat["Form.Languages"], ["c", "cpp"]);
    }

    public function testDabsicGroupParserAcceptsFieldsNamedLikeMetadata()
    {
        $metadata = dabsic_form_empty_form_metadata();
        $metadata["groups"]["Facture"] = ["fields" => []];
        dabsic_form_parse_group_fields([
            "Invoice" => [
                "Reference" => ["Label" => "Numéro de facture"],
                "Label" => ["Label" => "Désignation"],
            ],
        ], "", "Facture", $metadata);

        $this->assertArrayHasKey("Invoice.Reference", $metadata["fields"]);
        $this->assertArrayHasKey("Invoice.Label", $metadata["fields"]);
        $this->assertArrayNotHasKey("Invoice", $metadata["fields"]);
        $this->assertSame("Désignation", $metadata["fields"]["Invoice.Label"]["label"]);
    }
}
