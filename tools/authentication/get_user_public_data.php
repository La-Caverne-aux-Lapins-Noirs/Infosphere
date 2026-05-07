<?php

function get_user_public_data(&$usr)
{
    get_user_promotions($usr);
    get_user_children($usr);
    get_user_laboratories($usr);
    get_user_school($usr, true);
    $usr["todo"] = db_select_all("id, content FROM user_todolist WHERE id_user = {$usr["id"]} ORDER BY id ASC");
}
