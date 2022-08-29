<?php

function DisplayMedals($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $medal = fetch_medal();
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($medal, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    require ("./pages/medal/list_medal.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddMedal($id, $data, $method, $output, $module)
{

}

function AddRessource($id, $data, $method, $output, $module)
{

}

function GetRessourceDir($id, $data, $method, $output, $module)
{

}

function DeleteMedal($id, $data, $method, $output, $module)
{

}

function RemoveRessource($id, $data, $method, $output, $module)
{

}

