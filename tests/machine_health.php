<?php

require_once __DIR__.'/../tools/machine_health_view.php';

function mh_check($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

$tmp = sys_get_temp_dir().'/tc-metrics-'.bin2hex(random_bytes(5));
mkdir($tmp.'/proc/sys/kernel/random', 0700, true);
mkdir($tmp.'/sys/class/hwmon/hwmon0', 0700, true);
mkdir($tmp.'/sys/class/thermal/thermal_zone0', 0700, true);
file_put_contents($tmp.'/proc/sys/kernel/random/boot_id', 'boot-one');
file_put_contents($tmp.'/proc/stat', "cpu 100 0 100 700 100 0 0 0 0 0\ncpu0 1 2 3 4\n");
file_put_contents($tmp.'/proc/meminfo', "MemTotal: 1000 kB\nMemAvailable: 400 kB\nSwapTotal: 100 kB\nSwapFree: 75 kB\n");
file_put_contents($tmp.'/proc/uptime', '3600.5 5000');
file_put_contents($tmp.'/proc/loadavg', '1.25 0 0 0/0');
file_put_contents($tmp.'/sys/class/hwmon/hwmon0/name', 'coretemp');
file_put_contents($tmp.'/sys/class/hwmon/hwmon0/temp1_label', '<Core 0>');
file_put_contents($tmp.'/sys/class/hwmon/hwmon0/temp1_input', '42000');
file_put_contents($tmp.'/sys/class/hwmon/hwmon0/temp1_max', '80000');
file_put_contents($tmp.'/sys/class/hwmon/hwmon0/temp1_crit', '100000');
file_put_contents($tmp.'/sys/class/thermal/thermal_zone0/type', 'SoC');
file_put_contents($tmp.'/sys/class/thermal/thermal_zone0/temp', '95000');
try
{
    $first = tc_metrics_collect([], [], $tmp.'/proc', $tmp.'/sys');
    mh_check($first['cpu_percent'] === null, 'CPU needs a previous counter');
    mh_check($first['memory']['used_percent'] === 60.0, 'reclaimable cache is not used RAM');
    mh_check($first['memory']['swap_used_bytes'] === 25600.0, 'swap usage');
    mh_check($first['temperatures'][0]['celsius'] === 42.0, 'millidegrees conversion');
    mh_check(max(array_column($first['temperatures'], 'celsius')) === 95.0,
        'a thermal CPU sensor must not be hidden by unrelated hwmon sensors');
    file_put_contents($tmp.'/proc/stat', "cpu 180 0 120 800 100 0 0 0\ncpu0 1 2 3 4\n");
    $second = tc_metrics_collect($first, [], $tmp.'/proc', $tmp.'/sys');
    mh_check($second['cpu_percent'] === 50.0, 'CPU measured from counter deltas');
    file_put_contents($tmp.'/proc/sys/kernel/random/boot_id', 'boot-two');
    mh_check(tc_metrics_cpu($second, $tmp.'/proc')['cpu_percent'] === null, 'reboot resets the baseline');
    mh_check(tc_metrics_temperatures($tmp.'/no-sensors') === [], 'missing sensors stay unknown');
    mh_check(tc_metrics_number(INF) === null && tc_metrics_number(-1) === null, 'invalid metrics');
    mh_check(json_encode(tc_metrics_string('éé', 3)) !== false, 'UTF-8 label truncation remains encodable');
}
finally
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,
        FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file) : unlink($file);
    rmdir($tmp);
}

$now = time();
$healthy = ['kind' => 'server', 'online' => true, 'response_ms' => 40,
    'observed_at' => $now, 'metrics' => tc_metrics_normalize([
        'cpu_percent' => 40, 'memory' => ['used_percent' => 55],
        'disks' => [['path' => '/', 'total_bytes' => 1000, 'free_bytes' => 700]],
        'temperatures' => [['label' => '<Core>', 'celsius' => 45]],
    ])];
mh_check(machine_health_evaluate($healthy)['score'] === 100.0, 'healthy margins');
$warm = $healthy;
$warm['metrics']['memory']['used_percent'] = 91;
$state = machine_health_evaluate($warm);
mh_check(abs($state['score'] - round(pow(50 * pow(100, 4), 0.2), 1)) < 0.01, 'geometric mean');
mh_check($state['bad'] && $state['status'] === 'warning', 'resource pressure unfolds the workstation');
$hot = $healthy;
$hot['metrics']['temperatures'][0]['celsius'] = 100;
mh_check(machine_health_evaluate($hot)['score'] === 0.0, 'a critical sensor cannot be averaged away');
$unknown = $healthy;
$unknown['metrics']['temperatures'] = [];
mh_check(in_array('Température', machine_health_evaluate($unknown)['missing']), 'missing temperature is explicit');
$off = ['kind' => 'workstation', 'online' => false, 'observed_at' => $now];
mh_check(!machine_health_evaluate($off)['bad'] && machine_health_evaluate($off)['score'] === null,
    'an off workstation is neutral');
$down = array_merge($off, ['kind' => 'server']);
mh_check(machine_health_evaluate($down)['bad'], 'a down server is an alert');
mh_check(machine_health_evaluate(array_merge($off, ['online' => null]))['status'] === 'unknown',
    'Distrans outage does not assert workstation downtime');
mh_check(machine_health_evaluate(array_merge($healthy, ['observed_at' => $now - 600]))['score'] === null,
    'stale observations cannot claim good health');
$plot = machine_health_plot_data([$healthy, $off, array_merge($off, ['online' => null])]);
mh_check($plot[1]['cpu'] === null && $plot[1]['up'] === 0 && $plot[2]['up'] === null, 'graph gaps');
ob_start();
machine_health_render('<script>machine</script>', [$hot], 'server');
$html = ob_get_clean();
mh_check(!str_contains($html, '<script>machine') && str_contains($html, '&lt;Core&gt;'), 'HTML escaping');
echo "machine_health: OK (Linux counters, cache, reboot, unknown sensors, geometric score, outages, graphs)\n";

// Optional real MariaDB integration. Uses a disposable schema, never an application database.
$socket = getenv('TECHNOCORE_TEST_MYSQL_SOCKET');
if (!$socket) exit(0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$Database = new mysqli('localhost', 'root', '', '', 0, $socket);
$schema = 'tc_health_test_'.getmypid();
$Database->query("CREATE DATABASE $schema");
$Database->select_db($schema);
function db_select_all($sql)
{
    global $Database;
    return $Database->query('SELECT '.$sql)->fetch_all(MYSQLI_ASSOC);
}
function hand_request($cmd, $code = true, $timeout = 0)
{
    global $healthy;
    return ['result' => 'ok', 'protocol' => 1, 'server' => $healthy['metrics'], 'machines' => [
        ['mac' => 'aa:bb:cc:dd:ee:01', 'online' => true, 'metrics' => $healthy['metrics'], 'response_ms' => 50],
        ['mac' => 'aa:bb:cc:dd:ee:02', 'online' => false],
    ]];
}
define('REPORT', 6);
function add_log(...$args) {}
try
{
    $Database->query('CREATE TABLE room_desk (id INT, mac VARCHAR(255))');
    $Database->query("INSERT INTO room_desk VALUES (1, 'aa:bb:cc:dd:ee:01'), (2, 'aa:bb:cc:dd:ee:02')");
    mh_check(machine_health_ready(), 'automatic schema creation');
    machine_health_store('infosphere', $healthy, $now - 49 * 3600);
    mh_check(machine_health_collect(['online' => true, 'response_ms' => 40]), 'real SQL collection');
    $rows = machine_health_fetch(['infosphere', 'distrans', 'desk:aa:bb:cc:dd:ee:01', 'desk:aa:bb:cc:dd:ee:02'], 0);
    mh_check(count($rows['infosphere']) === 1, '48-hour purge');
    mh_check($rows['desk:aa:bb:cc:dd:ee:01'][0]['online'] === true, 'Persoc sample imported');
    mh_check($rows['desk:aa:bb:cc:dd:ee:02'][0]['online'] === false, 'off workstation imported');
    mh_check(machine_health_collect(['online' => false]), 'outage persisted');
    $rows = machine_health_fetch(['distrans', 'desk:aa:bb:cc:dd:ee:01'], 0);
    mh_check(count($rows['distrans']) === 1, 'one record per bucket, retry-safe upsert');
    mh_check($rows['distrans'][0]['online'] === false, 'server outage state');
    mh_check($rows['desk:aa:bb:cc:dd:ee:01'][0]['online'] === null, 'upstream outage means unknown');
    mh_check(isset(machine_health_server_alerts()['distrans']), 'server alert from stored data');
    echo "machine_health: MariaDB OK (schema, import, purge, retries, outages, alerts)\n";
}
finally
{
    $Database->query("DROP DATABASE $schema");
}
