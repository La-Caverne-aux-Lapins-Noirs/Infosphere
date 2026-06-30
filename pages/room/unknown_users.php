<?php
if (!in_array("codename", db_select_rows("room_desk_user")))
    return ;

$unknown_room_users_title = isset($Dictionnary["UnknownRoomUsers"]) ? $Dictionnary["UnknownRoomUsers"] : "Connexions sans poste identifié";
$no_unknown_room_users = isset($Dictionnary["NoUnknownRoomUsers"]) ? $Dictionnary["NoUnknownRoomUsers"] : "Aucun utilisateur connecté sur un poste non identifié.";
$unknown_room_users_notice = isset($Dictionnary["UnknownRoomUsersNotice"]) ? $Dictionnary["UnknownRoomUsersNotice"] : "Utilisateurs remontés par des postes qui ne sont encore affectés à aucune salle.";
$locked_title = isset($Dictionnary["Locked"]) ? $Dictionnary["Locked"] : "Verrouillé";
$ip_address_label = isset($Dictionnary["IpAddress"]) ? $Dictionnary["IpAddress"] : "IP";

$unknown_users = db_select_all("\n    room_desk_user.codename as username,\n    room_desk_user.distant,\n    room_desk_user.locked,\n    room_desk_user.last_update,\n    room_desk.codename as desk_codename,\n    room_desk.ip as desk_ip,\n    room.codename as room_codename,\n    room.".$Language."_name as room_name\n    FROM room_desk_user\n    LEFT JOIN room_desk ON room_desk.id = room_desk_user.id_room_desk\n    LEFT JOIN room ON room.id = room_desk.id_room\n    WHERE room_desk_user.id_user IS NULL\n      AND TIMESTAMPDIFF(SECOND, room_desk_user.last_update, NOW()) < 60 * 5\n      AND LOWER(room_desk_user.codename) NOT IN ('root', 'technocore')\n      AND (room_desk.id IS NULL OR room_desk.id_room IS NULL OR room_desk.id_room = -1)\n    ORDER BY room.codename, room_desk.codename, room_desk_user.codename\n");
?>
<div class="room_unknown_users_box" style="background-color: rgba(0, 0, 0, 0.7);">
    <h2><?=$unknown_room_users_title; ?></h2>
    <?php if (!count($unknown_users)) { ?>
        <p class="room_unknown_users_empty">
            <?=$no_unknown_room_users; ?>
        </p>
    <?php } else { ?>
        <p class="room_unknown_users_notice">
            <?=$unknown_room_users_notice; ?>
        </p>
        <ul class="room_unknown_users_list">
            <?php foreach ($unknown_users as $unknown_user) { ?>
                <li>
                    <b><?=htmlentities($unknown_user["username"]); ?></b>
                    <?php if ((int)$unknown_user["distant"]) { ?>
                        <span class="room_unknown_user_flag">SSH</span>
                    <?php } ?>
                    <?php if ((int)$unknown_user["locked"]) { ?>
                        <span title="<?=$locked_title; ?>">&#128274;</span>
                    <?php } ?>
                    <br />
                    <span>
                        <?php
                        $room_label = strlen((string)$unknown_user["room_name"]) ? $unknown_user["room_name"] : $unknown_user["room_codename"];
                        if (!strlen((string)$room_label))
                            $room_label = isset($Dictionnary["UnknownDesks"]) ? $Dictionnary["UnknownDesks"] : "Poste non identifié";
                        ?>
                        <?=htmlentities($room_label); ?>
                        <?php if (strlen((string)$unknown_user["desk_codename"])) { ?>
                            — <?=htmlentities((string)$unknown_user["desk_codename"]); ?>
                        <?php } ?>
                    </span>
                    <?php if (strlen((string)$unknown_user["desk_ip"])) { ?>
                        <br />
                        <span><?=$ip_address_label; ?>: <?=htmlentities((string)$unknown_user["desk_ip"]); ?></span>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>
