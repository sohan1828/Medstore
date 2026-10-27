<?php
require_once __DIR__ . '/../includes/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);

$in     = json_decode(file_get_contents('php://input'), true) ?? [];
$digits = fn($s) => preg_replace('/[^0-9+]/', '', (string)$s);
$phone  = $digits($in['phone'] ?? '');
$type   = in_array($in['type'] ?? '', ['heart','stroke','choking','bleeding','accident','general'], true) ? $in['type'] : 'general';
$other  = !empty($in['for_other']) ? 1 : 0;
$pname  = substr(trim((string)($in['patient_name'] ?? '')), 0, 120) ?: null;
$pphone = $digits($in['patient_phone'] ?? '') ?: null;
$addr   = substr(trim((string)($in['address'] ?? '')), 0, 500);

$hasLoc = isset($in['lat'], $in['lng']) && $in['lat'] !== '' && $in['lng'] !== '';
$lat = $hasLoc ? filter_var($in['lat'], FILTER_VALIDATE_FLOAT) : null;
$lng = $hasLoc ? filter_var($in['lng'], FILTER_VALIDATE_FLOAT) : null;
if ($hasLoc && ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180)) json_out(['error' => 'Invalid location'], 422);
if (strlen($phone) < 7) json_out(['error' => 'A valid phone number is required'], 422);
if (!$hasLoc && strlen($addr) < 5) json_out(['error' => 'Share your location or type the address'], 422);

// Abuse guard: max 3 requests per phone per 10 minutes
$st = db()->prepare('SELECT COUNT(*) c FROM incidents WHERE phone = ? AND created_at > NOW() - INTERVAL 10 MINUTE');
$st->execute([$phone]);
if ($st->fetch()['c'] >= 3) json_out(['error' => 'Too many requests. Call 102 directly.'], 429);

$token = bin2hex(random_bytes(16)); $share = bin2hex(random_bytes(16));
db()->prepare("INSERT INTO incidents (public_token, share_token, phone, for_other, patient_name, patient_phone, address_text, lat, lng, emergency_type, status, ack_deadline)
               VALUES (?,?,?,?,?,?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL ? SECOND))")
    ->execute([$token, $share, $phone, $other, $pname, $pphone, $addr ?: null, $lat, $lng, $type, $hasLoc ? 'open' : 'needs_staff', ACK_SECONDS]);
$id  = (int)db()->lastInsertId();
$inc = ['id' => $id, 'phone' => $phone, 'lat' => $lat, 'lng' => $lng, 'emergency_type' => $type];

if (!$hasLoc) {   // no GPS: a person in the control room must take over
    send_sms(STAFF_ALERT_PHONE, "EMERGENCY needs a person: incident #$id, $type. Call $phone. Address: $addr");
    $status = 'needs_staff';
} elseif (dispatch_next($inc)) {
    $status = 'open';
} else {
    db()->prepare("UPDATE incidents SET status='call_fallback' WHERE id=?")->execute([$id]);
    send_sms(STAFF_ALERT_PHONE, "EMERGENCY nobody available: incident #$id, $type. Call $phone.");
    place_fallback_call($inc);
    $status = 'call_fallback';
}
json_out(['token' => $token, 'status' => $status, 'share_url' => SITE_URL . '/follow.php?s=' . $share]);
