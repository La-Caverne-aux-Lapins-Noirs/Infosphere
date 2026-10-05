<?php

function run_command_append_output(&$buffer, $chunk, $limit, &$truncated)
{
    if ($chunk === "" || $limit <= 0 || $truncated)
        return ;

    $remaining = $limit - strlen($buffer);
    if ($remaining <= 0)
    {
        $truncated = true;
        return ;
    }

    if (strlen($chunk) <= $remaining)
        $buffer .= $chunk;
    else
    {
        $buffer .= substr($chunk, 0, $remaining);
        $truncated = true;
    }
}

function run_command($command, $timeout = 300, $max_output = 8 * 1024 * 1024): array
{
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"],  // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes);

    if (!is_resource($process))
    {
        errorlog("Unable to execute command : ".(is_array($command)
            ? implode(" ", $command) : $command));
        return ([
            "stdout" => NULL,
            "stderr" => NULL,
            "exit_code" => NULL,
            "timed_out" => false,
        ]);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = "";
    $stderr = "";
    $stdout_truncated = false;
    $stderr_truncated = false;
    $open = [1 => true, 2 => true];
    $deadline = $timeout > 0 ? microtime(true) + $timeout : NULL;
    $timed_out = false;
    $known_exit_code = NULL;

    while ($open[1] || $open[2])
    {
        $read = [];
        if ($open[1])
            $read[] = $pipes[1];
        if ($open[2])
            $read[] = $pipes[2];

        $write = NULL;
        $except = NULL;
        if (count($read))
        {
            $ready = @stream_select($read, $write, $except, 0, 200000);
            if ($ready === false)
                $read = [];
        }

        foreach ($read as $stream)
        {
            $index = $stream === $pipes[1] ? 1 : 2;
            $chunk = fread($stream, 16384);
            if ($chunk !== false && $chunk !== "")
            {
                if ($index == 1)
                    run_command_append_output(
                        $stdout, $chunk, $max_output, $stdout_truncated
                    );
                else
                    run_command_append_output(
                        $stderr, $chunk, $max_output, $stderr_truncated
                    );
            }

            if (feof($stream))
            {
                fclose($stream);
                $open[$index] = false;
            }
        }

        $status = proc_get_status($process);
        if (!$status["running"] && $status["exitcode"] >= 0)
            $known_exit_code = $status["exitcode"];

        if ($deadline !== NULL && microtime(true) >= $deadline && $status["running"])
        {
            $timed_out = true;
            proc_terminate($process);
            usleep(100000);
            $status = proc_get_status($process);
            if ($status["running"])
                proc_terminate($process, 9);
            $deadline = NULL;
        }

        if (!$status["running"] && !count($read))
        {
            foreach ([1, 2] as $index)
                if ($open[$index] && feof($pipes[$index]))
                {
                    fclose($pipes[$index]);
                    $open[$index] = false;
                }
        }
    }

    $exit_code = proc_close($process);
    if ($exit_code == -1 && $known_exit_code !== NULL)
        $exit_code = $known_exit_code;
    if ($timed_out)
        $exit_code = 124;

    if ($stdout_truncated)
        $stdout .= "\n[stdout truncated]\n";
    if ($stderr_truncated)
        $stderr .= "\n[stderr truncated]\n";
    if ($timed_out)
        $stderr .= "\n[command timed out after ".(int)$timeout." seconds]\n";

    return ([
        "stdout" => $stdout,
        "stderr" => $stderr,
        "exit_code" => $exit_code,
        "timed_out" => $timed_out,
    ]);
}
