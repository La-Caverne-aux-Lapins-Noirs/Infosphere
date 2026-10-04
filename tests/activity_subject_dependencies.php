<?php

require_once (__DIR__."/../tools/activity_subject_dependencies.php");

$root = sys_get_temp_dir()."/infosphere-subject-deps-".getmypid();
$activity = $root."/activity";
$corrections = $root."/corrections";
@mkdir($activity, 0770, true);
@mkdir($corrections."/functions/string", 0770, true);
@mkdir($corrections."/programs/print", 0770, true);

file_put_contents($activity."/configuration.dab", <<<'DAB'
{Exercises
  @push "functions/string/sample_exercise.dab"
}
DAB
);

file_put_contents($corrections."/functions/string/sample_exercise.dab", <<<'DAB'
= [Array
  [Eval
    @insert "functions/string/sample.dab"
  ]
]
DAB
);

file_put_contents($corrections."/functions/string/sample.dab", <<<'DAB'
[Document
  Demo = @insert c ($, EOF) "programs/print/demo.c"
  Dynamic = @insert c ($, EOF) "test_" # Name # ".c"
]
DAB
);

$status = activity_subject_dependencies($activity."/configuration.dab", 32, $corrections);
assert($status["available"] === true);
assert(count($status["missing"]) === 1);
assert($status["missing"][0]["requested_path"] === "programs/print/demo.c");
assert($status["missing"][0]["source"] === "functions/string/sample.dab");
assert($status["dynamic"] === 1);

file_put_contents($corrections."/programs/print/demo.c", "int demo(void) { return (0); }\n");
$status = activity_subject_dependencies($activity."/configuration.dab", 32, $corrections);
assert(count($status["missing"]) === 0);
assert(count($status["resolved"]) === 3);
assert($status["dynamic"] === 1);

$delete = function($path) use (&$delete) {
    if (is_dir($path))
    {
        foreach (array_diff(scandir($path), [".", ".."]) as $entry)
            $delete($path."/".$entry);
        rmdir($path);
    }
    else
        unlink($path);
};
$delete($root);

echo "activity_subject_dependencies ok\n";
