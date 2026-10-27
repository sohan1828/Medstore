<?php
require_once __DIR__ . '/../includes/security.php';
$a = require_driver_api();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);
csrf_check_header();
$in  = json_decode(file_get_contents('php://input'), true) ?? [];
$act = $in['action'] ?? '';
$iid = (int)($in['incident_id'] ?? 0);
$me  = (int)$a['id'];
$pdo = db();

switch ($act) {
  case 'duty_on':
    $pdo->prepare("UPDATE ambulances SET status='available' WHERE id=? AND status='offline'")->execute([$me]);
    break;
  case 'duty_off':
    $pdo->prepare("UPDATE ambulances SET status='offline', lat=NULL, lng=NULL, last_seen=NULL WHERE id=? AND status='available'")->execute([$me]);
    break;
  case 'accept':
    $pdo->beginTransaction();
    $u = $pdo->prepare("UPDATE incidents SET status='acknowledged', ambulance_id=?, acknowledged_by=?, accepted_at=NOW()
        WHERE id=? AND status='open' AND ack_deadline > NOW()
          AND EXISTS (SELECT 1 FROM incident_attempts t WHERE t.incident_id = incidents.id AND t.ambulance_id = ? AND t.outcome = 'pending')");
    $u->execute([$me, $a['provider_id'], $iid, $me]);
    if (!$u->rowCount()) { $pdo->rollBack(); json_out(['error' => 'This request expired or was taken by someone else.'], 409); }
    $pdo->prepare("UPDATE incident_attempts SET outcome='accepted' WHERE incident_id=? AND ambulance_id=? AND outcome='pending'")->execute([$iid, $me]);
    $pdo->prepare("UPDATE ambulances SET status='busy' WHERE id=?")->execute([$me]);
    $pdo->commit();
    break;
  case 'decline':
    $d = $pdo->prepare("UPDATE incident_attempts SET outcome='declined' WHERE incident_id=? AND ambulance_id=? AND outcome='pending'");
    $d->execute([$iid, $me]);
    // make the 30 second clock run out now so the worker offers it to the next responder at once
    if ($d->rowCount()) $pdo->prepare("UPDATE incidents SET ack_deadline = NOW() WHERE id=? AND status='open'")->execute([$iid]);
    break;
  case 'arrived':
    $pdo->prepare("UPDATE incidents SET status='arrived', arrived_at=NOW() WHERE id=? AND ambulance_id=? AND status='acknowledged'")->execute([$iid, $me]);
    break;
  case 'complete':
    $c = $pdo->prepare("UPDATE incidents SET status='completed' WHERE id=? AND ambulance_id=? AND status IN ('acknowledged','arrived')");
    $c->execute([$iid, $me]);
    if ($c->rowCount()) $pdo->prepare("UPDATE ambulances SET status='available' WHERE id=?")->execute([$me]);
    break;
  default:
    json_out(['error' => 'Unknown action'], 422);
}
json_out(['ok' => true]);
