<?php

require_once __DIR__.'/../tools/machine_health_view.php';

function history_check($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

class HealthHistoryCursor
{
    private $index;
    private $end = 1440;
    private $payload;
    function __construct($latest)
    {
        $this->index = $latest ? 1440 : 0;
        $this->payload = json_encode(['kind' => 'workstation', 'online' => true,
            'response_ms' => 30, 'metrics' => tc_metrics_normalize([
                'cpu_percent' => 40, 'memory' => ['used_percent' => 50],
                'disks' => array_fill(0, 32, ['path' => '/'.str_repeat('data', 60),
                    'total_bytes' => 1000, 'free_bytes' => 600]),
                'temperatures' => array_fill(0, 64, ['label' => str_repeat('sensor', 10), 'celsius' => 45]),
            ])]);
    }
    function fetch_assoc()
    {
        if ($this->index > $this->end) return null;
        return ['node_key' => 'desk:aa:bb:cc:dd:ee:01',
            'observed_at' => time() - (1440 - $this->index++) * 120, 'payload' => $this->payload];
    }
    function free() {}
}

class HealthHistoryDatabase
{
    public $latest_query = '';
    function real_escape_string($value) { return addslashes($value); }
    function query($sql, ...$options)
    {
        if (!str_starts_with($sql, 'SELECT')) return true;
        $this->latest_query = $sql;
        return new HealthHistoryCursor(str_contains($sql, 'MAX(observed_at)'));
    }
}

$Database = new HealthHistoryDatabase;
$before = memory_get_usage();
$rows = machine_health_fetch(['desk:aa:bb:cc:dd:ee:01'], 0, true)['desk:aa:bb:cc:dd:ee:01'];
$memory = memory_get_usage() - $before;
history_check(count($rows) === 1441, 'all two-minute points over 48 hours are kept');
history_check(isset($rows[0]['plot']) && !isset($rows[0]['metrics']), 'older reports are compacted');
history_check(count(end($rows)['metrics']['temperatures']) === 64, 'the latest sensor details remain complete');
history_check((float)machine_health_plot_data($rows)[0]['cpu'] === 40.0, 'compaction preserves graph values');
history_check($memory < 12 * 1024 * 1024, 'history must not retain every expanded sensor report');
$latest = machine_health_fetch(['desk:aa:bb:cc:dd:ee:01'], 0, false, true);
history_check(count($latest['desk:aa:bb:cc:dd:ee:01']) === 1, 'initial inventory only loads the latest state');
history_check(str_contains($Database->latest_query, 'MAX(observed_at)'), 'latest state selected by SQL');
echo 'machine_health_history: OK (1441 points, 64 sensors, 32 mounts, '.round($memory / 1024 / 1024, 1)." MiB)\n";
