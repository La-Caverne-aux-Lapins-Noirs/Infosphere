<?php

if ($_POST["action"] == "calendar_feed_enable")
    calendar_feed_set_token($User, false);
else if ($_POST["action"] == "calendar_feed_regenerate")
    calendar_feed_set_token($User, true);
else if ($_POST["action"] == "calendar_feed_disable")
    calendar_feed_disable($User);
