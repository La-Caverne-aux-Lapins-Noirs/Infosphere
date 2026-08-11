<?php

function subscribe_jury($first_name, $last_name, $mail, $phone)
{
    return (subscribe_named_user($first_name, $last_name, $mail, $phone, false, true, "jury"));
}
