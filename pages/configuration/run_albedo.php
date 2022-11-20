<?php

$cmd = shell_exec("crontab -l | cut -d ' ' -f 6-");
system($cmd);
unset($cmd);

