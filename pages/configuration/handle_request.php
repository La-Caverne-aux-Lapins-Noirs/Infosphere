<?php

if ($_POST["action"] == "execute_query")
{
    $execution = configuration_execute_operation_by_id($_POST["operation"] ?? -1, $outfile);
    $result = $execution["result"];
}
