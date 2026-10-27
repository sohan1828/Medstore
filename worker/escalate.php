<?php
// Run on the server, NOT in the browser:
//   php worker/escalate.php daemon     (long-lived, via systemd/supervisor)
//   php worker/escalate.php            (cron every minute; loops ~55s)
require_once __DIR__ . '/../includes/lib.php';

$end = time() + (($argv[1] ?? '') === 'daemon' ? 10 * 365 * 86400 : 55);

while (time() < $end) {
    $st = db()->query("SELECT i.*, (SELECT COUNT(*) FROM incident_attempts a WHERE a.incident_id = i.id) AS attempts
                       FROM incidents i
                       WHERE i.status = 'open' AND i.ack_deadline <= NOW()");
    foreach ($st->fetchAll() as $inc) {
        // Claim atomically so two workers never double-escalate
        $lock = db()->prepare("UPDATE incidents SET ack_deadline = DATE_ADD(NOW(), INTERVAL ? SECOND)
                               WHERE id = ? AND status = 'open' AND ack_deadline <= NOW()");
        $lock->execute([ACK_SECONDS, $inc['id']]);
        if (!$lock->rowCount()) continue;

        if ((int)$inc['attempts'] >= MAX_ATTEMPTS || !dispatch_next($inc)) {
            db()->prepare("UPDATE incidents SET status='call_fallback' WHERE id=?")->execute([$inc['id']]);
            place_fallback_call($inc);
        }
    }
    sleep(3);
}
