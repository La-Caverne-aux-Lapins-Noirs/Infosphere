<?php

require_once __DIR__."/machine_health.php";

function machine_health_escape($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}

function machine_health_assets()
{
    static $done = false;
    if ($done) return;
    $done = true;
    echo "<style>".file_get_contents(__DIR__."/../style/structure/machine_health.css")."</style>";
}

function machine_health_hours()
{
    $hours = (int)($_GET["health_hours"] ?? 48);
    return (in_array($hours, [1, 6, 12, 24, 48], true) ? $hours : 48);
}

function machine_health_ranges()
{
    machine_health_assets();
    echo '<nav class="mh-ranges" aria-label="Période des graphes">Historique : ';
    foreach ([1, 6, 12, 24, 48] as $hours)
    {
        $query = $_GET;
        $query["health_hours"] = $hours;
        echo '<a href="?'.machine_health_escape(http_build_query($query)).'"'.
            ($hours === machine_health_hours() ? ' aria-current="true"' : '').'>'.$hours.' h</a> ';
    }
    echo '</nav>';
}

function machine_health_badge(array $state)
{
    $text = $state["label"].($state["score"] !== NULL ? ' · '.$state["score"].'/100' : '');
    return ('<span class="mh-badge mh-'.machine_health_escape($state["status"]).'">'.
        machine_health_escape($text).'</span>');
}

function machine_health_plot_data(array $rows)
{
    $out = [];
    foreach ($rows as $sample)
    {
        $out[] = $sample["plot"] ?? machine_health_sample_values($sample);
    }
    return ($out);
}

function machine_health_chart(array $points, array $series, $unit, $max, $title)
{
    $end = time();
    $start = $end - machine_health_hours() * 3600;
    $points = array_values(array_filter($points, static fn($point) => $point["t"] >= $start));
    $max = max(1, $max);
    $escape = "machine_health_escape";
    echo '<figure class="mh-chart"><figcaption>'.$escape($title).'</figcaption>';
    echo '<svg viewBox="0 0 620 165" role="img" aria-label="'.$escape($title).'">';
    echo '<title>'.$escape($title).' · '.machine_health_hours().' heures</title>';
    foreach ([0, 0.5, 1] as $ratio)
    {
        $y = 130 - 110 * $ratio;
        echo '<path d="M48 '.$y.' H610" class="mh-gridline"/>';
        echo '<text x="42" y="'.($y + 4).'" text-anchor="end">'.$escape(round($max * $ratio)).'</text>';
    }
    $has_values = false;
    foreach ($series as $key => [$label, $color])
    {
        $path = '';
        $previous = NULL;
        $dots = '';
        $stride = max(1, (int)ceil(count($points) / 100));
        foreach ($points as $index => $point)
        {
            $value = $point[$key] ?? NULL;
            if ($value === NULL) { $previous = NULL; continue; }
            $has_values = true;
            $x = round(48 + 562 * ($point["t"] - $start) / ($end - $start), 2);
            $y = round(130 - 110 * max(0, min(1, $value / $max)), 2);
            $connected = $previous !== NULL && $point["t"] - $previous <= 360;
            $path .= $connected && $key === 'up' ? ' H'.$x.' V'.$y
                : ($connected ? ' L' : ' M').$x.','.$y;
            $previous = $point["t"];
            if ($index % $stride === 0 || $index === count($points) - 1)
                $dots .= '<circle cx="'.$x.'" cy="'.$y.'" r="4" class="mh-hit">'.
                    '<title>'.$escape(date('d/m H:i', $point["t"]).' · '.$label.' : '.round($value, 1).' '.$unit).
                    '</title></circle>';
        }
        echo '<path d="'.$path.'" fill="none" stroke="'.$color.'" stroke-width="1.8"/>'.$dots;
    }
    if (!$has_values) echo '<text x="325" y="78" text-anchor="middle">Aucune mesure disponible</text>';
    echo '<text x="48" y="155">'.$escape(date('d/m H:i', $start)).'</text>';
    echo '<text x="610" y="155" text-anchor="end">'.$escape(date('d/m H:i', $end)).'</text></svg>';
    echo '<div class="mh-legend">';
    foreach ($series as [$label, $color])
        echo '<span><i style="background:'.$color.'"></i>'.$escape($label).' ('.$escape($unit).')</span>';
    echo '</div></figure>';
}

function machine_health_bytes($bytes)
{
    if ($bytes === NULL) return ('—');
    foreach (['o', 'Kio', 'Mio', 'Gio', 'Tio'] as $unit)
    {
        if ($bytes < 1024 || $unit === 'Tio') break;
        $bytes /= 1024;
    }
    return (round($bytes, 1).' '.$unit);
}

function machine_health_render($title, array $rows, $kind)
{
    machine_health_assets();
    $sample = $rows ? end($rows) : NULL;
    $state = machine_health_evaluate($sample);
    $metrics = $sample["metrics"] ?? [];
    $escape = "machine_health_escape";
    ?>
    <section class="mh-card mh-<?=$escape($state["status"]); ?>">
        <header><h3><?=$escape($title); ?></h3><?=machine_health_badge($state); ?></header>
        <p class="mh-muted">
            Dernier relevé : <?=$sample ? $escape(date('d/m/Y H:i:s', $sample["observed_at"])) : 'aucun'; ?>.
            <?php if ($kind !== 'local' && ($sample["online"] ?? NULL) !== NULL) { ?>
                <?=$sample["online"] ? 'En ligne.' : ($kind === 'workstation' ? 'Sans signal récent.' : 'Injoignable.'); ?>
            <?php } ?>
        </p>
        <?php if ($state["reasons"]) { ?>
            <ul class="mh-reasons"><?php foreach ($state["reasons"] as $reason) { ?>
                <li><?=$escape($reason); ?></li>
            <?php } ?></ul>
        <?php } ?>
        <?php if (($sample["issue"] ?? '') === 'telemetry_unavailable') { ?>
            <p>Distrans répond au ping ; ses mesures matérielles sont indisponibles. Vérifier sa mise à jour.</p>
        <?php } ?>
        <?php if ($state["missing"]) { ?>
            <p class="mh-muted">Mesures manquantes : <?=$escape(implode(', ', $state["missing"])); ?>.
                Elles sont exclues du calcul.</p>
        <?php } ?>
        <dl class="mh-values">
            <div><dt>CPU</dt><dd><?=$escape(isset($metrics["cpu_percent"]) ? $metrics["cpu_percent"].' %' : '—'); ?></dd></div>
            <div><dt>RAM utilisée</dt><dd><?=$escape(isset($metrics["memory"]["used_percent"])
                ? $metrics["memory"]["used_percent"].' % · '.machine_health_bytes($metrics["memory"]["used_bytes"]).
                  ' / '.machine_health_bytes($metrics["memory"]["total_bytes"]) : '—'); ?></dd></div>
            <div><dt>Allumé depuis</dt><dd><?=$escape(isset($metrics["uptime_seconds"])
                ? round($metrics["uptime_seconds"] / 3600, 1).' h' : '—'); ?></dd></div>
            <?php if ($kind !== 'local') { ?>
                <div><dt><?=$kind === 'workstation' ? 'Liaison Persoc → Distrans' : 'Réponse Infosphère → Distrans'; ?></dt>
                    <dd><?=$escape(isset($sample["response_ms"]) ? $sample["response_ms"].' ms' : '—'); ?></dd></div>
            <?php } ?>
        </dl>
        <div class="mh-sensors">
            <?php foreach ($metrics["disks"] ?? [] as $disk) { ?>
                <span>Disque <code><?=$escape($disk["path"]); ?></code> : <?=$escape($disk["used_percent"]); ?> % utilisés,
                    <?=$escape(machine_health_bytes($disk["free_bytes"])); ?> libres /
                    <?=$escape(machine_health_bytes($disk["total_bytes"])); ?></span>
            <?php } ?>
            <?php foreach ($metrics["temperatures"] ?? [] as $temp) { ?>
                <span><?=$escape($temp["label"]); ?> : <strong><?=$escape($temp["celsius"]); ?> °C</strong>
                    <small>(alerte <?=$escape($temp["warning"]); ?> °C, critique <?=$escape($temp["critical"]); ?> °C)</small></span>
            <?php } ?>
        </div>
        <div class="mh-charts">
            <?php
            $points = machine_health_plot_data($rows);
            $start = time() - machine_health_hours() * 3600;
            $points = array_values(array_filter($points, static fn($point) => $point['t'] >= $start));
            machine_health_chart($points, ['cpu' => ['CPU', '#68b8ff'], 'ram' => ['RAM', '#d7a1ff'],
                'disk' => ['Disque le plus rempli', '#ffcf70']], '%', 100, 'Utilisation des ressources');
            $temperatures = array_filter(array_column($points, 'temp'), static fn($v) => $v !== NULL);
            machine_health_chart($points, ['temp' => ['Capteur le plus chaud', '#ff937e']], '°C',
                max(100, $temperatures ? ceil(max($temperatures) / 10) * 10 : 100), 'Températures');
            $series = ['score' => ['Santé estimée', '#8ee09c']];
            if ($kind !== 'local') $series['up'] = ['Présence', '#a8adbb'];
            machine_health_chart($points, $series, '/100', 100, 'Santé et disponibilité');
            if ($kind !== 'local')
            {
                $responses = array_filter(array_column($points, 'rtt'), static fn($v) => $v !== NULL);
                machine_health_chart($points, ['rtt' => ['Temps de réponse SSH', '#73e1de']], 'ms',
                    max(1000, $responses ? ceil(max($responses) / 500) * 500 : 1000), 'Temps de réponse');
            }
            ?>
        </div>
        <p class="mh-muted">Indice estimé par moyenne géométrique des marges disponibles.
            Les périodes sans mesure restent vides sur les graphes.
            <?php if ($kind === 'workstation') { ?>Le temps de liaison est l'aller-retour SSH du rapport Persoc précédent.<?php } ?>
        </p>
    </section>
    <?php
}
