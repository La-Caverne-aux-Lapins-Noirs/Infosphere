<?php /////////////// RUN ALBEDO

@unlink("/tmp/albedo");
$cmd = shell_exec("crontab -l | cut -d ' ' -f 6-");
system($cmd);
$now = is_file("/tmp/albedo") ? file_get_contents("/tmp/albedo") : "";
echo $now;
unset($cmd);
unset($cur);
unset($now);
