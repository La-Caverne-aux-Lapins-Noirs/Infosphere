<?php

function prepare_export($x)
{
    return (base64_encode(json_encode($x, JSON_UNESCAPED_SLASHES)));
}

