<?php
// La page ne charge que les index JSON (petits); les rapports sont recuperes a l'ouverture.
$evaluation_teams = [];
if (isset($activity->user_team['id']))
    $evaluation_teams[] = $activity->user_team;
if ($activity->is_teacher || is_admin())
    $evaluation_teams = $activity->team;
$evaluation_total = 0;
$evaluation_official = 0;
$evaluation_last = null;
$evaluation_entries = [];
foreach ($evaluation_teams as $evaluation_team)
{
    if (!isset($evaluation_team['id'])) continue;
    $team_id = (int)$evaluation_team['id'];
    foreach (evaluation_history_list((int)$activity->id, $team_id) as $entry)
    {
        $entry['team_id'] = $team_id;
        $entry['team_name'] = $evaluation_team['team_name'] ?? ('Équipe '.$team_id);
        $evaluation_entries[] = $entry;
        $evaluation_total++;
        if ($entry['official']) $evaluation_official++;
        if ($evaluation_last === null || strcmp($entry['date'], $evaluation_last) > 0)
            $evaluation_last = $entry['date'];
    }
}
usort($evaluation_entries, fn($a, $b) => strcmp($b['id'], $a['id']));
// Comparer uniquement des evaluations successives de la meme equipe.
$evaluation_previous = [];
foreach (array_reverse($evaluation_entries) as &$evaluation_entry)
{
    $tid = $evaluation_entry['team_id'];
    $rate = $evaluation_entry['summary']['rate'] ?? null;
    $prior = $evaluation_previous[$tid] ?? null;
    $evaluation_entry['delta'] = $rate !== null && $prior !== null ? $rate - $prior : null;
    if ($rate !== null) $evaluation_previous[$tid] = $rate;
}
unset($evaluation_entry);

?>
<style>
.evaluation-summary { height:auto;overflow:visible;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:10px 0; }
.evaluation-summary > div {height:auto;padding:10px;border-radius:8px;background:rgba(128,128,128,.12);min-width:0;}
.evaluation-summary strong {display:block;font-size:1.5em;}
.evaluation-body {overflow-y:auto;min-height:0;}
.evaluation-history-row {margin:9px 0;border:1px solid rgba(128,128,128,.3);border-radius:8px;overflow:hidden;}
.evaluation-history-row.official {border-left:5px solid #c89732;}
.evaluation-history-row:not(.official) {border-left:5px solid #6386a8;}
.evaluation-history-row summary {cursor:pointer;display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px;}
.evaluation-history-row summary small {opacity:.8;}
.evaluation-history-detail {padding:12px;border-top:1px solid rgba(128,128,128,.2);}
.evaluation-report-section dl {display:grid;grid-template-columns:minmax(120px,1fr) 2fr;gap:5px 12px;}
.evaluation-report-section dt {opacity:.8;}.evaluation-report-section dd {margin:0;overflow-wrap:anywhere;}
@media(max-width:600px) {.evaluation-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
</style>
<div class="full_box_with_title final_box">
    <h4>Résultats des évaluations</h4>
    <div class="evaluation-body">
    <div class="evaluation-summary">
        <div><small>Évaluations</small><strong><?=$evaluation_total?></strong></div>
        <div><small>Officielles</small><strong><?=$evaluation_official?></strong></div>
        <div><small>Dernière évaluation</small><strong style="font-size:1em"><?=$evaluation_last ? htmlspecialchars(date('d/m/Y H:i', strtotime($evaluation_last)), ENT_QUOTES) : '—'?></strong></div>
    </div>
    <?php if (!$evaluation_entries): ?>
        <p>Aucune évaluation archivée pour cette activité.</p>
    <?php endif; ?>
    <?php foreach ($evaluation_entries as $entry):
        $key = 'evaluation:'.(int)$activity->id.':'.$entry['team_id'].':'.$entry['id'];
        $url = 'index.php?p=ActivityMenu&a='.(int)$activity->id.'&t='.$entry['team_id'].'&evaluation_detail='.rawurlencode($entry['id']);
    ?>
        <details class="evaluation-history-row <?=$entry['official'] ? 'official' : ''?>"
            data-evaluation-key="<?=htmlspecialchars($key, ENT_QUOTES)?>"
            data-evaluation-url="<?=htmlspecialchars($url, ENT_QUOTES)?>">
            <summary>
                <strong><?=$entry['official'] ? '● Officielle' : '● Entraînement'?></strong>
                <span><?=htmlspecialchars(date('d/m/Y H:i', strtotime($entry['date'])), ENT_QUOTES)?></span>
                <?php if (count($evaluation_teams) > 1): ?>
                    <small><?=htmlspecialchars($entry['team_name'], ENT_QUOTES)?></small>
                <?php endif; ?>
                <small><?=htmlspecialchars($entry['origin'], ENT_QUOTES)?></small>
                <?php if (isset($entry['comparison'])): ?>
                    <small style="color:#28794e">+<?=count($entry['comparison']['new_success'] ?? [])?> validations</small>
                    <small style="color:#ae4e42"><?=count($entry['comparison']['regressions'] ?? [])?> régressions</small>
                    <small><?=count($entry['comparison']['persistent_failures'] ?? [])?> problèmes persistants</small>
                <?php endif; ?>
                <?php if (isset($entry['summary']['counts'])): ?>
                    <small><?= (int)$entry['summary']['counts']['Success'] ?> réussites / <?= (int)$entry['summary']['evaluated'] ?> contrôles</small>
                    <?php if ($entry['summary']['rate'] !== null): ?>
                        <strong><?= (int)$entry['summary']['rate'] ?> %</strong>
                        <?php if (($entry['delta'] ?? null) !== null): ?>
                            <small><?= $entry['delta'] > 0 ? '+' : '' ?><?= (int)$entry['delta'] ?> points</small>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </summary>
            <div class="evaluation-history-detail" data-evaluation-content="1">Ouvrir pour charger le rapport…</div>
        </details>
    <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('details[data-evaluation-key]').forEach(function (element) {
        var key = element.dataset.evaluationKey;
        try { element.open = localStorage.getItem(key) === 'open'; } catch (e) {}
        element.addEventListener('toggle', function () {
            try { localStorage.setItem(key, element.open ? 'open' : 'closed'); } catch (e) {}
            if (!element.open || element.dataset.evaluationLoaded === '1') return;
            element.dataset.evaluationLoaded = '1';
            var target = element.querySelector('[data-evaluation-content]');
            target.textContent = 'Chargement du rapport…';
            fetch(element.dataset.evaluationUrl, {credentials: 'same-origin'})
                .then(function (response) { if (!response.ok) throw new Error(response.status); return response.text(); })
                .then(function (html) {
                    target.innerHTML = html;
                    // Chaque exercice conserve son etat dans cette evaluation seulement.
                    var exercises = target.querySelectorAll('details.evaluation-exercise-toggle');
                    exercises.forEach(function (exercise, index) {
                        var title = exercise.querySelector('summary strong');
                        var name = title ? title.textContent : String(index);
                        var exerciseKey = key + ':exercise:' + index + ':' + name;
                        try {
                            var state = localStorage.getItem(exerciseKey);
                            if (state === 'closed') exercise.open = false;
                            if (state === 'open') exercise.open = true;
                        } catch (e) {}
                        exercise.addEventListener('toggle', function () {
                            try { localStorage.setItem(exerciseKey, exercise.open ? 'open' : 'closed'); }
                            catch (e) {}
                        });
                    });
                })
                .catch(function () { target.textContent = 'Rapport indisponible.'; element.dataset.evaluationLoaded = '0'; });
        });
        if (element.open) element.dispatchEvent(new Event('toggle'));
    });
})();
</script>
