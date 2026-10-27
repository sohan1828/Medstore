<?php
// Provider opens the SMS link, enters their token, confirms they are dispatching.
require_once __DIR__ . '/includes/lib.php';

$incidentId = (int)($_GET['incident'] ?? 0);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $st = db()->prepare("SELECT p.id FROM providers p
                         JOIN incident_attempts a ON a.provider_id = p.id AND a.incident_id = ?
                         WHERE p.api_token = SHA2(?, 256) AND p.active = 1");
    $st->execute([$incidentId, $token]);
    $prov = $st->fetch();

    if (!$prov) {
        $msg = 'Invalid token for this incident.';
    } else {
        $u = db()->prepare("UPDATE incidents SET status='acknowledged', acknowledged_by=?
                            WHERE id=? AND status='open'");
        $u->execute([$prov['id'], $incidentId]);
        if ($u->rowCount()) db()->prepare("UPDATE incident_attempts SET outcome='accepted' WHERE incident_id=? AND provider_id=? AND outcome='pending'")->execute([$incidentId, $prov['id']]);
        $msg = $u->rowCount() ? 'Acknowledged. Please dispatch now.' : 'Already handled.';
    }
}
?>
<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Acknowledge emergency</title>
<body style="font-family:system-ui;max-width:420px;margin:40px auto;padding:0 16px">
<h2>Emergency #<?= $incidentId ?></h2>
<?php if ($msg): ?><p><strong><?= htmlspecialchars($msg) ?></strong></p><?php endif; ?>
<form method="post">
  <input name="token" placeholder="Provider token" required style="width:100%;padding:10px;margin:8px 0">
  <button style="width:100%;padding:14px;font-size:1.1rem">I am dispatching</button>
</form>
</body>
