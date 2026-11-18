<?php
if (!isset($albedo) || $albedo != 1)
    return ;

if (!empty($HandOk) && !exam_schedule_publish())
    add_log(REPORT, "Unable to publish the exam schedule to Distrans.", 1);

if (!empty($HandOk))
{
    exam_workflow_collect_due();
    exam_workflow_start_worker();
}

// S'interesse aux activités terminées dans les 2 dernières heures
// Place les absents et les médailles échouées.
$begin = db_form_date(now() - 60 * 60 * 2);
$end = db_form_date(now());
$sessions = db_select_all("
      *, id as id_session
      FROM session
      WHERE end_date >= '$begin' AND end_date < '$end'
");

if (!empty($Configuration->Properties["self_signing"]))
    require_once (__DIR__."/albedo/self_signing.php");
require_once (__DIR__."/albedo/pickup.php");
require_once (__DIR__."/albedo/automatic_evaluation.php");
require_once (__DIR__."/albedo/deploy_exam.php");
require_once (__DIR__."/albedo/appointment_slots.php");
require_once (__DIR__."/albedo/session_signin_sheet.php");
