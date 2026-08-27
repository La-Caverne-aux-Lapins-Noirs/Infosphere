<div class="matter_preaccess_lock">
    <b>🔒 <?=$Dictionnary["QuizMatterPreaccessLockedTitle"] ?? "Inscription verrouillée"; ?></b>
    <p><?=$Dictionnary["QuizMatterPreaccessLockedHelp"] ?? "Cette matière reste visible, mais les questionnaires de pré-accès doivent être réussis avant l'inscription."; ?></p>
    <div class="matter_preaccess_list">
        <?php foreach ($matter_preaccess_status["quizzes"] as $qstatus) {
            $quiz = $qstatus["quiz"];
            $loaded = questionnaire_load_model($quiz);
            $name = $loaded["ok"] ? $loaded["model"]["name"] : $quiz["codename"];
        ?>
            <div class="matter_preaccess_item <?=$qstatus["passed"] ? "passed" : "pending"; ?>">
                <span><?=$qstatus["passed"] ? "✓" : "○"; ?> <?=htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span>
                <?php if (!$qstatus["passed"] && is_array($qstatus["attempt"])) { ?>
                    <a href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$qstatus["attempt"]["id"]; ?>"><?=$Dictionnary["QuizAttemptResume"] ?? "Reprendre"; ?></a>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php if ($matter_preaccess_status["next"] !== NULL && !is_array($matter_preaccess_status["next"]["attempt"])) { ?>
        <form method="post" action="/api/module/<?=(int)$matter->id; ?>/preaccess_attempt" onsubmit="return silent_submitf(this, {after_success: function(result, msg, content) { if (content) window.location.href = content; }});">
            <button type="submit" class="modulebutton" style="width:100%; min-height:48px;"><?=$Dictionnary["QuizPreaccessStart"] ?? "Commencer le questionnaire préalable"; ?></button>
        </form>
    <?php } ?>
</div>
<style>
.matter_preaccess_lock { text-align:left; padding:8px; }
.matter_preaccess_lock p { margin:6px 0; font-size:small; }
.matter_preaccess_list { display:grid; gap:4px; margin:8px 0; }
.matter_preaccess_item { display:flex; justify-content:space-between; gap:8px; align-items:center; }
.matter_preaccess_item.passed { opacity:.7; }
</style>
