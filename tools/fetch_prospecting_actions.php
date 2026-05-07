<?php

function fetch_prospecting_actions($id)
{
    return (new ValueResponse(db_select_all("
	prospection.*, action.name, action.consequence, user.codename FROM prospection
	LEFT JOIN action ON action.id = prospection.id_action
	LEFT JOIN user ON user.id = id_prospector
	WHERE id_user = $id ORDER BY action_date ASC
    ")));
}

