<?php

chdir(__DIR__);
$Language = "fr";
require_once ("language.php");
require_once ("tools/index.php");
load_constants();

$token = isset($_GET["token"]) ? strtolower(trim($_GET["token"])) : "";
if (($CalendarUser = calendar_feed_user_from_token($token)) == NULL)
{
    http_response_code(404);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Calendrier inconnu ou desactive.\n";
    exit ;
}

$User = $CalendarUser;
$Localisation = calendar_feed_timezone();
$content = calendar_feed_render($User);
$etag = '"'.sha1($content).'"';

if (isset($_SERVER["HTTP_IF_NONE_MATCH"]) && trim($_SERVER["HTTP_IF_NONE_MATCH"]) === $etag)
{
    http_response_code(304);
    header("ETag: $etag");
    exit ;
}

header("Content-Type: text/calendar; charset=utf-8");
header("Content-Disposition: inline; filename=infosphere.ics");
header("Cache-Control: private, max-age=300");
header("ETag: $etag");
echo $content;
