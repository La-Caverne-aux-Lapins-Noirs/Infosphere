<?php

require ("activities.php");

$Tab = [
    "GET" => [
	"" => [
	    "am_i_director,am_i_cycle_director,am_i_teacher",
	    "DisplayActivity"
	],
	"admin" => [
	    "am_i_teacher",
	    "DisplayActivityAdmin"
	],
	"export" => [
	    "is_teacher_for_activity",
	    "ExportActivityDescription"
	],
    ],
    "POST" => [
	"" => [
	    "am_i_director,am_i_cycle_director",
	    "AddActivity"
	],
	"medal" => [
	    "is_teacher_for_activity",
	    "AddMedal"
	],
	"ressource" => [
	    "is_teacher_for_activity",
	    "AddRessource"
	],
	"subject" => [
	    "is_teacher_for_activity",
	    "SetSubject"
	],
	"preaccess" => [
	    "is_teacher_for_activity",
	    "SetActivityPreaccessQuiz"
	],
	"preaccess_attempt" => [
	    "logged_in",
	    "StartActivityPreaccessAttempt"
	],
	"satisfaction_quiz" => [
	    "is_teacher_for_activity",
	    "SetActivitySatisfactionQuiz"
	],
	"satisfaction_attempt" => [
	    "logged_in",
	    "StartActivitySatisfactionAttempt"
	],
	"rubric_quiz" => [
	    "is_teacher_for_activity",
	    "SetActivityRubricQuiz"
	],
	"rubric_attempt" => [
	    "is_assistant_for_activity",
	    "StartActivityRubricAttempt"
	],
	"wallpaper" => [
	    "is_teacher_for_activity",
	    "AddMood"
	],
	"icon" => [
	    "is_teacher_for_activity",
	    "AddMood"
	],
	"intro" => [
	    "is_teacher_for_activity",
	    "AddMood"
	],
	"mood" => [
	    "is_teacher_for_activity",
	    "AddMood"
	],
	"instantiate" => [
	    "is_director_for_activity",
	    "Instantiate",
	],
	"duplicate" => [
	    "am_i_teacher",
	    "DuplicateActivity",
	],
	"scrum" => [
	    "is_teacher_for_activity",
	    "GenerateActivityScrum",
	],
	"import" => [
	    "am_i_director,am_i_cycle_director",
	    "ImportActivityDescription",
	],
    ],
    "PUT" => [
	"" => [
	    "is_teacher_for_activity",
	    "EditActivity"
	],
	"template_link" => [
	    "is_teacher_for_activity",
	    "EditTemplateLink"
	],
	"reset_template_link" => [
	    "is_teacher_for_activity",
	    "ResetTemplateLink"
	],
	"move" => [
	    "is_teacher_for_activity",
	    "MoveActivity",
	],
	"cycle" => [
	    "is_director_for_activity",
	    "SetActivityLink"
	],
	"teacher" => [
	    "am_i_director,am_i_cycle_director",
	    "SetActivityLink"
	],
	"support" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"medal" => [
	    "is_teacher_for_activity",
	    "EditMedal"
	],
	"scale" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"mcq" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"satisfaction" => [
	    "is_teacher_or_director_for_activity",
	    "SetActivityLink"
	],
	"skill" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"software" => [
	    "is_teacher_for_activity",
	    "SetSoftware"
	],
	"subject" => [
	    "is_assistant_for_activity",
	    "GetSubjectDir"
	],
	"ressource" => [
	    "is_assistant_for_activity",
	    "GetRessourceDir"
	],
	"mood" => [
	    "is_assistant_for_activity",
	    "GetMoodDir"
	],
	"registration" => [
	    "everybody", // Autorisation trop complexe pour etre ici.
	    "SetActivityRegistration",
	],
	"pickup" => [
	    "is_teacher_for_activity",
	    "PickupActivity",
	],
	"todolist" => [
	    "is_teacher_for_activity",
	    "EditTodoList",
	],
    ],
    "DELETE" => [
	"" => [
	    "am_i_director,am_i_cycle_director",
	    "DeleteActivity"
	],
	"subject" => [
	    "is_teacher_for_activity",
	    "SetSubject"
	],
	"preaccess" => [
	    "is_teacher_for_activity",
	    "SetActivityPreaccessQuiz"
	],
	"satisfaction_quiz" => [
	    "is_teacher_for_activity",
	    "SetActivitySatisfactionQuiz"
	],
	"rubric_quiz" => [
	    "is_teacher_for_activity",
	    "SetActivityRubricQuiz"
	],
	"medal" => [
	    "is_teacher_for_activity",
	    "AddMedal"
	],
	"cycle" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"teacher" => [
	    "am_i_director,am_i_cycle_director",
	    "SetActivityLink"
	],
	"laboratory" => [
	    "is_director_for_activity",
	    "SetActivityLink"
	],
	"support" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"support_asset" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"support_category" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"activity" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"scale" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"mcq" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"satisfaction" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"skill" => [
	    "is_teacher_for_activity",
	    "SetActivityLink"
	],
	"software" => [
	    "is_teacher_for_activity",
	    "RemoveSoftware"
	],
	"ressource" => [
	    "is_teacher_for_activity",
	    "RemoveRessource"
	],
	"mood" => [
	    "is_teacher_for_activity",
	    "RemoveMood"
	],
	"registration" => [
	    "everybody", // Autorisation trop complexe pour etre ici.
	    "SetActivityRegistration",
	],
    ]
];

