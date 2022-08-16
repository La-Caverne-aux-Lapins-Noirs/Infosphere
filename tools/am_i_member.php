<?php

function am_i_member($teacher)
{
    return (retrieve_authority($teacher) >= 0);
}
