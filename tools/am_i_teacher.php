<?php

function am_i_teacher($teacher)
{
    return (retrieve_authority($teacher) >= TEACHER);
}
