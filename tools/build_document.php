<?php

function build_document(string $output_name, string $conf_file, array $dyndata): void {
    // Remplace ~ par le vrai HOME si présent
    $conf_file = str_replace('~', getenv('HOME'), $conf_file);

    $values = "";
    foreach ($dyndata as $key => $data) {
        $values .= " -m " . escapeshellarg("$key=$data");
    }

    $command = "docbuilder -i " . escapeshellarg($conf_file) . $values . " -o " . escapeshellarg($output_name);
    
    print_r("Command : " . $command . "\n");
    print_r(shell_exec($command));
}

build_document("/tmp/test_output.pdf", "~/DocBuilder/examples/activity.dab", []);
