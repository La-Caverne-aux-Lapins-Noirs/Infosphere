<?php

function bad_request()
{
    http_response_code(400);
    debug_packet();
    die();
}

function authentication_required()
{
    http_response_code(401);
    debug_packet();
    die();
}

function forbidden()
{
    http_response_code(403);
    debug_packet();
    die();
}

function not_found()
{
    http_response_code(404);
    debug_packet();
    die();
}

function not_allowed()
{
    http_response_code(405);
    debug_packet();
    die();
}
