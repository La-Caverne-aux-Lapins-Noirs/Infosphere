<?php

function score_color_table($score)
{
    if ($score < -10)
	return ("255, 0, 0, 1.0");
    if ($score < 0)
	return ("255, 0, 0, ".($score < -10 ? 1.0 : 1.0 - (-$score / 10.0)));
    if ($score <= +3)
 	return ("0, ".((255 / 3) * $score).", 0, 0.25");
    return ("0, 255, 0, 0.75");
}

