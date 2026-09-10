<?php

function student_correspondence_cycle_result($id_user, $id_cycle)
{
    $id_user = (int)$id_user;
    $id_cycle = (int)$id_cycle;
    if ($id_user <= 0 || $id_cycle <= 0)
        return (NULL);

    $profile = new FullProfile;
    if (!$profile->build($id_user, ["profile", "laboratory", "teacher"]))
        return (NULL);

    foreach ($profile->sublayer as $cycle)
    {
        if ((int)$cycle->id !== $id_cycle)
            continue ;
        if (!$cycle->done)
            return (NULL);
        $flames = max(0, (int)round((float)$cycle->acquired_credit));
        return ([
            "id_cycle" => $id_cycle,
            "codename" => (string)$cycle->codename,
            "flames" => $flames,
            "kind" => student_correspondence_result_kind($flames),
        ]);
    }
    return (NULL);
}

function student_correspondence_result_kind($flames)
{
    $flames = max(0, (int)$flames);
    if ($flames > 120)
        return ("exceptional");
    if ($flames >= 100)
        return ("objective");
    if ($flames >= 50)
        return ("encouragement");
    return ("support");
}

function student_correspondence_result_labels()
{
    return ([
        "exceptional" => "Féliciter > 120",
        "objective" => "Féliciter 100–120",
        "encouragement" => "Encourager 50–99",
        "support" => "Encourager 0–49",
    ]);
}
