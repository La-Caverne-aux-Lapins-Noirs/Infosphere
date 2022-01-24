<?php

function am_i_assistant($teacher)
{
    return (retrieve_authority($teacher) >= ASSISTANT);
}
