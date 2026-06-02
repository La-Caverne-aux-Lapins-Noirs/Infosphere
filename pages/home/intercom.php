<?php
require_once ("./tools/intercom.php");

if (!function_exists("home_intercom_dictionary"))
{
function home_intercom_dictionary($key, $fallback)
{
    global $Dictionnary;

    if (isset($Dictionnary[$key]))
        return ($Dictionnary[$key]);
    return ($fallback);
}
}

if (!function_exists("home_intercom_context_title"))
{
function home_intercom_context_title($context)
{
    global $Dictionnary;

    if (isset($context["label"]))
        return ($context["label"]);
    if (function_exists("intercom_common_channel_name") && $context["misc_type"] == "common")
        return (intercom_common_channel_name($context["id_misc"]));
    if (function_exists("intercom_context_name"))
        return (intercom_context_name($context["misc_type"], $context["id_misc"]));
    return ($context["misc_type"]."#".$context["id_misc"]);
}
}

if (!function_exists("home_intercom_url"))
{
function home_intercom_url($context, $id_subject)
{
    return ("index.php?".http_build_query([
        "p" => "IntercomMenu",
        "table" => $context["misc_type"],
        "id" => (int)$context["id_misc"],
        "ref" => (int)$id_subject,
    ]));
}
}

if (!function_exists("home_intercom_add_context"))
{
function home_intercom_add_context(&$contexts, $misc_type, $id_misc, $kind, $label = NULL)
{
    $id_misc = (int)$id_misc;
    $key = $misc_type."/".$id_misc;
    if (isset($contexts[$key]))
        return ;
    $contexts[$key] = [
        "misc_type" => $misc_type,
        "id_misc" => $id_misc,
        "kind" => $kind,
    ];
    if ($label !== NULL)
        $contexts[$key]["label"] = $label;
}
}

if (!function_exists("home_intercom_user_contexts"))
{
function home_intercom_user_contexts()
{
    global $User;
    global $Language;

    $uid = (int)$User["id"];
    $field = $Language."_name";
    $contexts = [];

    // Messages privés du profil courant.
    home_intercom_add_context(
        $contexts,
        "user",
        $uid,
        "private",
        home_intercom_dictionary("HomeIntercomPrivateMessages", "Messages privés")
    );

    // Annonces générales.
    home_intercom_add_context(
        $contexts,
        "common",
        1,
        "announcement",
        function_exists("intercom_common_channel_name")
            ? intercom_common_channel_name(1)
            : home_intercom_dictionary("HomeIntercomGeneralAnnouncements", "Annonces et mises à jour")
    );

    // Salons école accessibles.
    $schools = db_select_all("\n        id, codename, $field as name\n        FROM school\n        WHERE deleted IS NULL OR deleted = 0\n        ORDER BY id ASC\n    ");
    foreach ($schools as $school)
    {
        $id_school = (int)$school["id"];
        if (!function_exists("intercom_school_access") || intercom_school_access($id_school))
            home_intercom_add_context(
                $contexts,
                "school",
                $id_school,
                "announcement",
                home_intercom_dictionary("HomeIntercomSchoolAnnouncements", "Annonces école")
            );
        if (function_exists("intercom_school_staff_access") && intercom_school_staff_access($id_school))
            home_intercom_add_context(
                $contexts,
                "school_staff",
                $id_school,
                "announcement",
                home_intercom_dictionary("HomeIntercomStaffAnnouncements", "Annonces équipe")
            );
    }

    // Annonces des cycles où l'utilisateur est inscrit.
    foreach (db_select_all("\n        DISTINCT cycle.id,\n        COALESCE(NULLIF(cycle.$field, ''), cycle.codename) as name\n        FROM user_cycle\n        LEFT JOIN cycle ON cycle.id = user_cycle.id_cycle\n        WHERE user_cycle.id_user = $uid\n          AND cycle.id IS NOT NULL\n          AND (cycle.deleted IS NULL OR cycle.deleted = 0)\n        ORDER BY COALESCE(NULLIF(cycle.$field, ''), cycle.codename)\n    ") as $cycle)
        home_intercom_add_context(
            $contexts,
            "cycle",
            $cycle["id"],
            "announcement",
            home_intercom_dictionary("HomeIntercomCycleAnnouncements", "Annonces de cycle")." — ".$cycle["name"]
        );

    // Annonces des matières qui concernent l'utilisateur.
    // On vise le modèle de matière, pour garder le même salon d'année en année.
    foreach (db_select_all("\n        DISTINCT target.id as id,\n        COALESCE(\n            NULLIF(target.$field, ''),\n            NULLIF(matter_source.$field, ''),\n            NULLIF(source.$field, ''),\n            target.codename\n        ) as name\n        FROM user_cycle\n        LEFT JOIN activity_cycle ON activity_cycle.id_cycle = user_cycle.id_cycle\n        LEFT JOIN activity as source ON source.id = activity_cycle.id_activity\n        LEFT JOIN activity as matter_source\n          ON matter_source.id = CASE\n              WHEN source.parent_activity IS NOT NULL AND source.parent_activity != -1\n              THEN source.parent_activity\n              ELSE source.id\n          END\n        LEFT JOIN activity as target\n          ON target.id = CASE\n              WHEN matter_source.id_template IS NOT NULL AND matter_source.id_template != -1\n              THEN matter_source.id_template\n              ELSE matter_source.id\n          END\n        WHERE user_cycle.id_user = $uid\n          AND source.id IS NOT NULL\n          AND matter_source.id IS NOT NULL\n          AND target.id IS NOT NULL\n          AND (target.parent_activity IS NULL OR target.parent_activity = -1)\n          AND (source.deleted IS NULL OR source.deleted = 0)\n          AND (matter_source.deleted IS NULL OR matter_source.deleted = 0)\n          AND (target.deleted IS NULL OR target.deleted = 0)\n        UNION\n        SELECT DISTINCT target.id as id,\n        COALESCE(\n            NULLIF(target.$field, ''),\n            NULLIF(matter_source.$field, ''),\n            NULLIF(source.$field, ''),\n            target.codename\n        ) as name\n        FROM user_team\n        LEFT JOIN team ON team.id = user_team.id_team\n        LEFT JOIN activity as source ON source.id = team.id_activity\n        LEFT JOIN activity as matter_source\n          ON matter_source.id = CASE\n              WHEN source.parent_activity IS NOT NULL AND source.parent_activity != -1\n              THEN source.parent_activity\n              ELSE source.id\n          END\n        LEFT JOIN activity as target\n          ON target.id = CASE\n              WHEN matter_source.id_template IS NOT NULL AND matter_source.id_template != -1\n              THEN matter_source.id_template\n              ELSE matter_source.id\n          END\n        WHERE user_team.id_user = $uid\n          AND source.id IS NOT NULL\n          AND matter_source.id IS NOT NULL\n          AND target.id IS NOT NULL\n          AND (target.parent_activity IS NULL OR target.parent_activity = -1)\n          AND (source.deleted IS NULL OR source.deleted = 0)\n          AND (matter_source.deleted IS NULL OR matter_source.deleted = 0)\n          AND (target.deleted IS NULL OR target.deleted = 0)\n        ORDER BY name\n    ") as $activity)
        home_intercom_add_context(
            $contexts,
            "activity",
            $activity["id"],
            "announcement",
            home_intercom_dictionary("HomeIntercomActivityAnnouncements", "Annonces de matière")." — ".$activity["name"]
        );

    return ($contexts);
}
}

if (!function_exists("home_intercom_visible_message_clause"))
{
function home_intercom_visible_message_clause($message_alias = "msg")
{
    // Les messages masqués par modération ne doivent pas déclencher la home.
    return ("\n        NOT EXISTS (\n            SELECT hidden.id\n            FROM message_report hidden\n            WHERE hidden.id_message = $message_alias.id\n              AND hidden.status = -1\n            LIMIT 1\n        )\n    ");
}
}

if (!function_exists("home_intercom_unread_entries_for_context"))
{
function home_intercom_unread_entries_for_context($context, $limit = 8)
{
    global $User;

    $uid = (int)$User["id"];
    $misc_type = intercom_escape($context["misc_type"]);
    $id_misc = (int)$context["id_misc"];
    $limit = max(1, (int)$limit);
    $visible = home_intercom_visible_message_clause("msg");

    return (db_select_all("\n        root.id as id_subject,\n        root.title as title,\n        COALESCE(last_msg.message, root.message) as excerpt,\n        COALESCE(last_msg.post_date, root.post_date) as post_date,\n        COALESCE(last_author.id, root_author.id) as uid,\n        COALESCE(last_author.nickname, root_author.nickname) as nickname,\n        COALESCE(last_author.codename, root_author.codename) as ucodename,\n        COALESCE(last_msg.id, root.id) as last_message_id\n        FROM message as root\n        LEFT JOIN message_user\n          ON message_user.id_user = $uid\n         AND message_user.id_message = root.id\n        LEFT JOIN message as last_msg\n          ON last_msg.id = (\n              SELECT msg.id\n              FROM message as msg\n              WHERE (msg.id = root.id OR msg.id_message = root.id)\n                AND msg.id_user != $uid\n                AND $visible\n                AND (message_user.view_date IS NULL OR msg.post_date > message_user.view_date)\n              ORDER BY msg.post_date DESC, msg.id DESC\n              LIMIT 1\n          )\n        LEFT JOIN user as last_author ON last_author.id = last_msg.id_user\n        LEFT JOIN user as root_author ON root_author.id = root.id_user\n        WHERE root.misc_type = '$misc_type'\n          AND root.id_misc = $id_misc\n          AND root.id_message IS NULL\n          AND last_msg.id IS NOT NULL\n        ORDER BY last_msg.post_date DESC, last_msg.id DESC\n        LIMIT $limit\n    "));
}
}

if (!function_exists("home_intercom_unread_entries"))
{
function home_intercom_unread_entries()
{
    $entries = [];
    foreach (home_intercom_user_contexts() as $context)
    {
        foreach (home_intercom_unread_entries_for_context($context) as $entry)
        {
            $entry["context"] = $context;
            $entry["context_title"] = home_intercom_context_title($context);
            $entries[] = $entry;
        }
    }
    usort($entries, function ($a, $b) {
        return (date_to_timestamp($b["post_date"]) - date_to_timestamp($a["post_date"]));
    });
    return (array_slice($entries, 0, 20));
}
}

$home_intercom_entries = home_intercom_unread_entries();
?>
<h2><?=$Dictionnary["AnnouncementAndMessages"]; ?></h2>

<div class="home_intercom_announcements">
<?php if (!count($home_intercom_entries)) { ?>
    <p class="discrete"><?=home_intercom_dictionary("HomeIntercomNoUnread", "Aucun message non lu."); ?></p>
<?php } ?>
<?php foreach ($home_intercom_entries as $entry) { ?>
    <?php
    $context = $entry["context"];
    $title = @strlen($entry["title"]) ? $entry["title"] : $entry["context_title"];
    $excerpt = trim(strip_tags($entry["excerpt"]));
    if (strlen($excerpt) > 180)
        $excerpt = substr($excerpt, 0, 177)."...";
    ?>
    <a class="home_intercom_entry" href="<?=htmlentities(home_intercom_url($context, $entry["id_subject"])); ?>">
        <span class="home_intercom_context"><?=htmlentities($entry["context_title"]); ?></span>
        <strong><?=htmlentities($title); ?></strong>
        <span class="home_intercom_meta">
            <?=$Dictionnary["By"]; ?>
            <?=htmlentities(@strlen($entry["nickname"]) ? $entry["nickname"] : $entry["ucodename"]); ?>,
            <?=htmlentities($entry["post_date"]); ?>
        </span>
        <?php if (@strlen($excerpt)) { ?>
            <span class="home_intercom_excerpt"><?=htmlentities($excerpt); ?></span>
        <?php } ?>
    </a>
<?php } ?>
</div>

<style>
.home_intercom_announcements {
    max-height: 360px;
    overflow-y: auto;
    padding-right: 4px;
}
.home_intercom_entry {
    display: block;
    margin: 6px 0;
    padding: 8px 10px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.055);
    text-decoration: none;
}
.home_intercom_entry:hover {
    background: rgba(255, 255, 255, 0.11);
}
.home_intercom_context,
.home_intercom_meta,
.home_intercom_excerpt {
    display: block;
}
.home_intercom_context {
    font-size: 0.78em;
    opacity: 0.72;
    margin-bottom: 2px;
}
.home_intercom_meta {
    font-size: 0.78em;
    opacity: 0.65;
    margin-top: 2px;
}
.home_intercom_excerpt {
    font-size: 0.88em;
    opacity: 0.86;
    margin-top: 5px;
}
</style>
