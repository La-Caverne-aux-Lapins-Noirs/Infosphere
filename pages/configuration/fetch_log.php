<?php

function fetch_log($page = 0)
{
    return (db_select_all("
             log.id_user as id_user,
             user.codename as user,
             log.log_date as date,
             log.type as type,
             log.message as message,
             log.ip as ip,
             log.id as id
      FROM log
      LEFT OUTER JOIN user ON log.id_user = user.id
      WHERE (log.id_user != 1 || log.type != 0)
      ORDER BY id DESC
      LIMIT $page, 50
    "));
}
