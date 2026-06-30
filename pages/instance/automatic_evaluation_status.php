<?php
if (!isset($activity) || !($activity->registered || $activity->is_assistant))
    return ;
$model = activity_automatic_evaluation_status_model($activity);
if (!$model["visible"])
    return ;
$auto_eval_id = "automatic_evaluation_status_".(int)$activity->id;
$next = $model["next_timestamp"];
$browser_next = isset($model["browser_next_timestamp"]) ? $model["browser_next_timestamp"] : $next;
?>
<div
    id="<?=$auto_eval_id; ?>"
    class="automatic_evaluation_status"
    data-next-timestamp="<?=$browser_next !== NULL ? (int)$browser_next : 0; ?>"
    data-refresh-url="/api/instance/<?=$activity->id; ?>/automatic_evaluation"
>
    <h4>Moulinette automatique</h4>
    <div class="automatic_evaluation_status_content">
        <?php if ($model["active"] && $next !== NULL) { ?>
            <?php if ($model["due_count"] > 0) { ?>
                <b>En attente du prochain passage d'Albedo</b>
                <br />
                <span class="automatic_evaluation_status_detail">
                    Fréquence : toutes les <?=$model["frequency"]; ?> minutes.
                </span>
            <?php } else { ?>
                <b><?=human_date($next, false, false, true); ?></b>
                <span
                    class="automatic_evaluation_countdown"
                    data-next-timestamp="<?=(int)$browser_next; ?>"
                ></span>
                <br />
                <span class="automatic_evaluation_status_detail">
                    Fréquence : toutes les <?=$model["frequency"]; ?> minutes.
                </span>
            <?php } ?>
        <?php } else { ?>
            <span class="automatic_evaluation_status_detail">
                <?=$model["reason"]; ?>
                <?php if ($next !== NULL) { ?>
                    <br />Premier passage possible : <?=human_date($next, false, false, true); ?>.
                <?php } ?>
            </span>
        <?php } ?>
    </div>
</div>
