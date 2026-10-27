<?php
require_once __DIR__ . '/../includes/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);
$in = json_decode(file_get_contents('php://input'), true) ?? [];

$q = db()->prepare("SELECT i.id, i.phone, i.emergency_type, a.plate FROM incidents i LEFT JOIN ambulances a ON a.id = i.ambulance_id
                    WHERE i.public_token = ? AND i.status IN ('open','needs_staff','call_fallback','acknowledged','arrived')");
$q->execute([$in['token'] ?? '']); $i = $q->fetch();
if (!$i) json_out(['error' => 'Request not found'], 404);

$hid = ($in['hospital_id'] ?? null) === null || $in['hospital_id'] === '' ? null : (int)$in['hospital_id'];
$h = null;
if ($hid !== null) {
    $s = db()->prepare("SELECT id, name, phone FROM providers WHERE id = ? AND type = 'hospital' AND active = 1");
    $s->execute([$hid]); $h = $s->fetch();
    if (!$h) json_out(['error' => 'Unknown hospital'], 422);
}
db()->prepare('UPDATE incidents SET destination_id = ? WHERE id = ?')->execute([$h['id'] ?? null, $i['id']]);

if ($h) {   // give the hospital a head start
    $who = $i['plate'] ? "Ambulance {$i['plate']} is bringing" : 'An ambulance has been requested for';
    send_sms($h['phone'], "$who a patient ({$i['emergency_type']}). Contact: {$i['phone']}.");
}
json_out(['ok' => true]);
