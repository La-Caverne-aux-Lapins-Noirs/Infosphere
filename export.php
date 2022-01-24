<?php

$DatabaseFile = "database.json";
$ConfigurationFile = "infosphere.dab";

require_once ("tools/index.php");

if (!isset($_POST["data"]))
    exit ;
if (!isset($_POST["filename"]))
    $filename = "export";
else
    $filename = $_POST["filename"];
if (isset($_POST["raw"]))
    $raw = !!$_POST["raw"];
else
    $raw = false;

$data = base64_decode($_POST["data"]);
$data = json_decode($data, true);
if (isset($_POST["html"]))
{
?>
<style>
 @page
 {
     size: 21cm 29.7cm;    /* A4 */
     margin: 1cm 1cm 1cm 1cm;
 }
 *
 {
     margin: 0px 0px 0px 0px;
     padding: 0px 0px 0px 0px;
     border-collapse: collapse;
     border: 0px;
     clear: both;
     line-height: 0.5cm;
 }
 td
 {
     margin-left: 1cm;
     margin-right: 1cm;
     text-align: center;
     vertical-align: middle;
     position: relative;
 }
 td > div
 {
     width: 90%;
     height: 90%;
     border: 1px solid lightgray;
     font-size: small;
 }
</style>
<?php
}
export_csv($data, $filename, $raw);
exit;
