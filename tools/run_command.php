<?php

function run_command(string $command): array
{
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"],  // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes);

    if (!is_resource($process)) {
        errorlog("Unable to execute command : ".$command);
        return ([
            'stdout' => NULL,
            'stderr' => NULL,
            'exit_code' => NULL,
        ]);
    }

    fclose($pipes[0]); // no stdin

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return ([
        'stdout' => $stdout ?? '',
        'stderr' => $stderr ?? '',
        'exit_code' => $exitCode,
    ]);
}
