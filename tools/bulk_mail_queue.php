<?php
// Persistent queue; processed from Albedo, never inside the HTTP request.
function bulk_mail_queue_dir()
{
    return dirname(__DIR__).'/dres/bulk_mail_queue';
}

function bulk_mail_enqueue($school, $kind, $subject, $plain, $html, $recipients, $author)
{
    $dir = bulk_mail_queue_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) return false;
    $id = bin2hex(random_bytes(12));
    $job = ['school' => (int)$school, 'kind' => $kind, 'author' => (int)$author,
        'subject' => $subject, 'plain' => $plain, 'html' => $html,
        'recipients' => array_values($recipients), 'sent' => [], 'failed' => [],
        'next_at' => time(), 'previous_domain' => '', 'created' => time()];
    $tmp = tempnam($dir, '.new-');
    if (!$tmp) return false;
    $ok = file_put_contents($tmp, "<?php http_response_code(404); exit; ?>\n".json_encode($job, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX) !== false;
    if ($ok) $ok = chmod($tmp, 0600) && rename($tmp, "$dir/$id.php");
    if (!$ok) @unlink($tmp);
    return $ok ? $id : false;
}

// Atomic write: never overwrite the only copy with partially written JSON.
function bulk_mail_save_job($path, $job)
{
    $dir = dirname($path);
    $tmp = tempnam($dir, '.work-');
    if ($tmp === false) return false;
    $data = "<?php http_response_code(404); exit; ?>\n".
        json_encode($job, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $ok = file_put_contents($tmp, $data) !== false;
    if ($ok) $ok = chmod($tmp, 0600) && rename($tmp, $path);
    if (!$ok) @unlink($tmp);
    return $ok;
}

function bulk_mail_has_pending()
{
    foreach (glob(bulk_mail_queue_dir().'/*.php') ?: [] as $path)
    {
        $job = json_decode((string)preg_replace('/^<\?php[^\n]*\n/', '',
            (string)@file_get_contents($path)), true);
        if (is_array($job) && (!empty($job['recipients']) || !empty($job['history_pending']))) return true;
    }
    return false;
}

// Record actual Mailgun acceptance in the existing prospecting timeline.
// The campaign identifier prevents duplicate actions when a worker resumes.
function bulk_mail_record_prospect_action($job, $job_id, $address)
{
    global $Database;
    if (($job['kind'] ?? '') !== 'prospect') return true;
    $school = (int)($job['school'] ?? 0);
    $author = (int)($job['author'] ?? 0);
    $action = db_select_one("id FROM action WHERE name = 'MailSent'");
    if (!$action || !isset($action['id'])) return false;
    $id_action = (int)$action['id'];
    $mail = $Database->real_escape_string(strtolower(trim($address)));
    // The mail address is the original selected recipient; only active
    // prospects belonging to the campaign's school may be matched.
    $users = db_select_all("user.id FROM user JOIN user_school us ON us.id_user = user.id
        WHERE us.id_school = $school AND LOWER(user.mail) = '$mail'
        AND user.profile_status = 'prospect' AND user.deleted IS NULL");
    $token = '[bulk-mail:'.$job_id.':'.hash('sha256', strtolower(trim($address))).']';
    $subject = trim((string)($job['subject'] ?? ''));
    $comment = 'Courriel collectif envoyé : '.$subject.' '.$token;
    $comment = $Database->real_escape_string($comment);
    $escaped_token = $Database->real_escape_string($token);
    foreach ($users ?: [] as $user)
    {
        $id = (int)$user['id'];
        $existing = db_select_one("id FROM prospection WHERE id_user = $id
            AND comment LIKE '%$escaped_token%'");
        if ($existing) continue;
        if (!$Database->query("INSERT INTO prospection (id_user, id_prospector, id_action, comment)
            VALUES ($id, $author, $id_action, '$comment')")) return false;
    }
    return true;
}

// One email only. Worker calls repeatedly, sleeps until the shared deadline.
// An in-flight address is persisted BEFORE send_mail: a crash leaves it in
// "uncertain" instead of silently resending a possibly delivered message.
function bulk_mail_process_jobs($limit = 1)
{
    $dir = bulk_mail_queue_dir();
    if (!is_dir($dir)) return 0;
    $lock = @fopen($dir.'/.worker.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return 0;
    $count = 0;
    $nextFile = $dir.'/.next_at';
    if ((int)@file_get_contents($nextFile) > time())
    {
        flock($lock, LOCK_UN); fclose($lock); return 0;
    }
    $files = glob($dir.'/*.php') ?: [];
    sort($files);
    foreach ($files as $path)
    {
        if ($count >= $limit) break;
        $job = json_decode((string)preg_replace('/^<\?php[^\n]*\n/', '',
            (string)@file_get_contents($path)), true);
        if (!is_array($job)) continue;
        $job_id = basename($path, '.php');
        if (!empty($job['history_pending']))
        {
            foreach ($job['history_pending'] as $index => $pending)
            {
                if (bulk_mail_record_prospect_action($job, $job_id, $pending))
                    unset($job['history_pending'][$index]);
            }
            $job['history_pending'] = array_values($job['history_pending']);
            if (!bulk_mail_save_job($path, $job)) break;
        }
        if (empty($job['recipients'])) continue;
        $previous = trim((string)@file_get_contents($dir.'/.previous_domain'));
        $chosen = 0;
        foreach ($job['recipients'] as $i => $mail)
        {
            $domain = strtolower(substr(strrchr($mail, '@') ?: '', 1));
            if ($domain !== $previous) { $chosen = $i; break; }
        }
        $address = $job['recipients'][$chosen];
        array_splice($job['recipients'], $chosen, 1);
        $job['uncertain'][] = $address;
        // If persistence fails, do not transmit anything.
        if (!bulk_mail_save_job($path, $job)) break;
        $domain = strtolower(substr(strrchr($address, '@') ?: '', 1));
        try
        {
            $result = send_mail($address, $job['subject'], $job['plain'], NULL,
                NULL, true, NULL, NULL, $job['html']);
            $successful = !$result->is_error();
        }
        catch (Throwable $e) { $successful = false; }
        $job['uncertain'] = array_values(array_filter($job['uncertain'],
            function ($item) use ($address) { return $item !== $address; }));
        $job[$successful ? 'sent' : 'failed'][] = $address;
        // Timed from end of previous transmission, not from Albedo tick.
        $next = time() + random_int(75, 225);
        $job['next_at'] = $next;
        if ($successful && ($job['kind'] ?? '') === 'prospect')
            $job['history_pending'][] = $address;
        if (!bulk_mail_save_job($path, $job)) break;
        if ($successful && ($job['kind'] ?? '') === 'prospect'
            && bulk_mail_record_prospect_action($job, $job_id, $address))
        {
            $job['history_pending'] = array_values(array_filter($job['history_pending'],
                function ($item) use ($address) { return $item !== $address; }));
            bulk_mail_save_job($path, $job);
        }
        file_put_contents($dir.'/.previous_domain', $domain, LOCK_EX);
        file_put_contents($nextFile, (string)$next, LOCK_EX);
        $count++;
    }
    flock($lock, LOCK_UN);
    fclose($lock);
    return $count;
}

// Albedo starts a detached CLI worker. Never block the periodic Albedo job.
function bulk_mail_start_worker()
{
    if (!bulk_mail_has_pending()) return;
    $php = PHP_BINARY;
    if (!is_executable($php) || !function_exists('exec')) return;
    $worker = __DIR__.'/process_bulk_mail_queue.php';
    // Redirect ALL streams: the shell must not keep the parent pipes open.
    $command = 'nohup '.escapeshellarg($php).' '.escapeshellarg($worker).
        ' </dev/null >/dev/null 2>&1 &';
    @exec($command);
}
