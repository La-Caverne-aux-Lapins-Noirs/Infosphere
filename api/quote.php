<?php

function RenderHomeQuote($id, $data, $method, $output, $module)
{
    global $User;

    ob_start();
    require ("./pages/home/quote.phtml");
    return (new ValueResponse([
        "content" => ob_get_clean()
    ]));
}

function VoteHomeQuote($id, $data, $method, $output, $module)
{
    $vote = (int)($data["vote"] ?? 0);
    $result = famous_quote_set_user_vote($id, $vote);

    if (!$result["ok"])
        bad_request("Vote invalide.");
    return (RenderHomeQuote($id, $data, $method, $output, $module));
}

$Tab = [
    "GET" => [
        "" => [
            "everybody",
            "RenderHomeQuote",
        ],
    ],
    "POST" => [
        "vote" => [
            "logged_in",
            "VoteHomeQuote",
        ],
    ],
];
