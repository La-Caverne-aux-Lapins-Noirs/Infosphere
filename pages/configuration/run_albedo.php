<?php /////////////// RUN ALBEDO

system("rm -f /tmp/albedo");
$cmd = shell_exec("crontab -l | cut -d ' ' -f 6-");
system($cmd);
echo $now = shell_exec("cat /tmp/albedo");
unset($cmd);
unset($cur);
unset($now);
