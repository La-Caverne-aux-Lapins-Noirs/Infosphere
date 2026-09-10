<?php

require_once ("xtestcase.php");
require_once ("../tools/document_tasks.php");
require_once ("../tools/document_workflow.php");
require_once ("../tools/attendance_register.php");

class DocumentTaskTest extends XTestCase
{
    public function testPlanKeepsSlotSeparateFromSemanticRole()
    {
        $plan = document_task_plan_normalize([
            "Teacher_12" => [
                "Action" => "sign",
                "Role" => "Teacher",
                "RoleLabel" => "Formateur intervenant",
                "AssigneeUserId" => 12,
                "Required" => 1,
            ],
            "Teacher_13" => [
                "Action" => "sign",
                "Role" => "Teacher",
                "RoleLabel" => "Formateur intervenant",
                "AssigneeUserId" => 13,
                "Required" => 1,
            ],
        ]);

        $this->assertCount(2, $plan);
        $this->assertSame("Teacher", $plan["Teacher_12"]["role"]);
        $this->assertSame("Teacher", $plan["Teacher_13"]["role"]);
        $this->assertSame(12, $plan["Teacher_12"]["id_assignee_user"]);
        $this->assertSame(13, $plan["Teacher_13"]["id_assignee_user"]);
    }

    public function testPrintActionIsAcceptedByGenericDocumentTasks()
    {
        $this->assertTrue(document_task_action_is_valid("print"));
        $plan = document_task_plan_normalize([
            "Postal" => [
                "Action" => "print",
                "Role" => "Print",
                "RoleLabel" => "Impression / envoi postal",
            ],
        ]);
        $this->assertArrayHasKey("Postal", $plan);
        $this->assertSame("print", $plan["Postal"]["action"]);
        $this->assertSame("Print", $plan["Postal"]["role"]);
    }

    public function testAttendanceRegisterCampaignDeduplicatesTeachersButNotRoles()
    {
        $plan = attendance_register_campaign_signature_task_plan(
            ["Id" => 20, "Identity" => "Director Test"],
            [
                ["Id" => 20, "Identity" => "Director Test", "Role" => "Encadrant de projet"],
                ["Id" => 30, "Identity" => "Teacher A", "Role" => "Formateur"],
                ["Id" => 30, "Identity" => "Teacher A", "Role" => "Formateur"],
                ["Id" => 31, "Identity" => "Teacher B", "Role" => "Chef de laboratoire"],
            ]
        );

        $this->assertArrayHasKey("Director", $plan);
        $this->assertArrayHasKey("Teacher_20", $plan);
        $this->assertArrayHasKey("Teacher_30", $plan);
        $this->assertArrayHasKey("Teacher_31", $plan);
        $this->assertCount(4, $plan);
        $this->assertSame("Director", $plan["Director"]["Role"]);
        $this->assertSame("Teacher", $plan["Teacher_20"]["Role"]);
    }

    public function testAttendanceRegisterLeafHasOnlyStudentTask()
    {
        $plan = attendance_register_student_signature_task_plan(["Id" => 10, "Identity" => "Student Test"]);
        $this->assertCount(1, $plan);
        $this->assertArrayHasKey("Student", $plan);
        $this->assertSame("Student", $plan["Student"]["Role"]);
    }

    public function testDynamicTeacherTasksBecomeDistinctSignatureSlots()
    {
        $plan = document_task_plan_normalize([
            "Teacher_30" => [
                "Action" => "sign", "Role" => "Teacher",
                "RoleLabel" => "Formateur intervenant", "AssigneeUserId" => 30,
            ],
            "Teacher_31" => [
                "Action" => "sign", "Role" => "Teacher",
                "RoleLabel" => "Formateur intervenant", "AssigneeUserId" => 31,
            ],
        ]);
        $state = document_workflow_signature_state_apply_task_plan([], $plan);

        $this->assertArrayHasKey("Teacher_30", $state);
        $this->assertArrayHasKey("Teacher_31", $state);
        $this->assertSame("Teacher", $state["Teacher_30"]["task_role"]);
        $this->assertSame(30, $state["Teacher_30"]["signatory_user_id"]);
        $this->assertSame(31, $state["Teacher_31"]["signatory_user_id"]);
    }

    public function testExpiredTasksDoNotCountAsOutstandingObligations()
    {
        $progress = document_task_progress([
            ["required" => 1, "task_action" => "sign", "status" => "completed"],
            ["required" => 1, "task_action" => "sign", "status" => "pending"],
            ["required" => 1, "task_action" => "sign", "status" => "expired"],
        ], "sign");

        $this->assertSame(1, $progress["completed"]);
        $this->assertSame(2, $progress["total"]);
        $this->assertCount(1, $progress["pending"]);
    }
}
