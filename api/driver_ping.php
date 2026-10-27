<?php
require_once __DIR__ . '/../includes/security.php';
$a = require_driver_api();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);
csrf_check_header();
$in  = json_decode(file_get_contents('php://input'), true) ?? [];
$lat = filter_var($in['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($in['lng'] ?? null, FILTER_VALIDATE_FLOAT);
$acc = (int)($in['acc'] ?? 0);
if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) json_out(['error' => 'Bad location'], 422);
// Location is only stored while the driver is on duty
db()->prepare("UPDATE ambulances SET lat=?, lng=?, accuracy_m=?, last_seen=NOW() WHERE id=? AND status IN ('available','busy')")
    ->execute([$lat, $lng, $acc > 0 ? $acc : null, $a['id']]);
json_out(['ok' => true]);
