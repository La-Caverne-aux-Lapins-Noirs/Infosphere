<?php

require_once ("xtestcase.php");

class PersocDeadlistTest extends XTestCase
{
    public function testParseKeepsOrderAndDeduplicates()
    {
        $parsed = persoc_deadlist_parse_text(
            "# blacklist\nBOb.Cat\nalice.bunny\nbob.cat\n1.2.3.4,remote\n2001:db8::1\n"
        );
        $this->assertSame([
            "bob.cat",
            "alice.bunny",
            "1.2.3.4",
            "2001:db8::1",
        ], $parsed["entries"]);
        $this->assertSame([], $parsed["invalid"]);
    }

    public function testParserRejectsUrlsAndInvalidHosts()
    {
        $parsed = persoc_deadlist_parse_text(
            "https://example.org/\nbad host\n-valid.example\nvalid.example\n"
        );
        $this->assertSame(["valid.example"], $parsed["entries"]);
        $this->assertCount(3, $parsed["invalid"]);
        $this->assertSame(1, $parsed["invalid"][0]["line"]);
    }

    public function testDistransResponseAcceptsListAndCsv()
    {
        $this->assertSame([
            "bob.cat",
            "alice.bunny",
        ], persoc_deadlist_entries_from_response([
            "result" => "ok",
            "deadlist" => [
                ["entry" => "bob.cat", "mode" => "remote"],
                ["entry" => "alice.bunny", "mode" => "remote"],
                ["entry" => "bob.cat", "mode" => "remote"],
            ],
        ]));

        $this->assertSame([
            "bob.cat",
            "alice.bunny",
        ], persoc_deadlist_entries_from_response([
            "ok" => true,
            "deadlist_csv" => "bob.cat,remote\nalice.bunny,remote\n",
        ]));
    }

    public function testDistransResponseStatusRecognition()
    {
        $this->assertTrue(persoc_deadlist_response_ok(["result" => "ok"]));
        $this->assertTrue(persoc_deadlist_response_ok(["ok" => true]));
        $this->assertFalse(persoc_deadlist_response_ok(["result" => "ko"]));
        $this->assertFalse(persoc_deadlist_response_ok(["status" => "error"]));
        $this->assertFalse(persoc_deadlist_response_ok(["ok" => false]));
        $this->assertFalse(persoc_deadlist_response_ok(["deadlist" => []]));
    }
    public function testStorageTextKeepsOrder()
    {
        $this->assertSame(
            "bob.cat\nalice.bunny\n1.2.3.4",
            persoc_deadlist_storage_text(["bob.cat", "alice.bunny", "1.2.3.4"])
        );
    }

}
