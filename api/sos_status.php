<?php
require_once __DIR__ . '/../includes/lib.php';

$share = isset($_GET['share']);                       // read-only family link
$key   = $share ? (string)$_GET['share'] : (string)($_GET['token'] ?? '');
$col   = $share ? 'i.share_token' : 'i.public_token';
$extra = $share ? ' AND i.created_at > NOW() - INTERVAL 24 HOUR' : '';

$st = db()->prepare("SELECT i.id, i.status, i.lat, i.lng, i.ambulance_id, i.acknowledged_by, i.destination_id, i.share_token,
        GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), i.ack_deadline)) AS left_s,
        (SELECT COUNT(*) FROM incident_attempts a WHERE a.incident_id = i.id) AS attempts
     FROM incidents i WHERE $col = ? $extra");
$st->execute([$key]); $i = $st->fetch();
if (!$i) json_out(['error' => 'Not found'], 404);

$hasLoc = $i['lat'] !== null;
$out = ['status' => $i['status'], 'attempts' => (int)$i['attempts'], 'seconds_left' => (int)$i['left_s'],
        'patient' => $hasLoc ? ['lat' => (float)$i['lat'], 'lng' => (float)$i['lng']] : null,
        'ambulance' => null, 'provider' => null, 'destination' => null,
        'share_url' => $share ? null : SITE_URL . '/follow.php?s=' . $i['share_token']];
$digits = fn($s) => preg_replace('/[^0-9+]/', '', (string)$s);

if ($i['destination_id']) {
    $q = db()->prepare('SELECT name, phone FROM providers WHERE id = ?'); $q->execute([$i['destination_id']]); $h = $q->fetch();
    if ($h) $out['destination'] = ['name' => $h['name'], 'phone' => $digits($h['phone'])];
}

if (in_array($i['status'], ['acknowledged', 'arrived'], true)) {
    if ($i['ambulance_id']) {
        $q = db()->prepare('SELECT driver_name, driver_phone, plate, lat, lng, TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS age FROM ambulances WHERE id = ?');
        $q->execute([$i['ambulance_id']]); $a = $q->fetch();
        if ($a) {
            $km = $eta = null;
            if ($hasLoc && $a['lat'] !== null) { $km = haversine_km((float)$i['lat'], (float)$i['lng'], (float)$a['lat'], (float)$a['lng']); $eta = eta_minutes($km); }
            $out['ambulance'] = ['driver_name' => $a['driver_name'], 'driver_phone' => $digits($a['driver_phone']), 'plate' => $a['plate'],
                                 'lat' => $a['lat'] !== null ? (float)$a['lat'] : null, 'lng' => $a['lng'] !== null ? (float)$a['lng'] : null,
                                 'seen_age_s' => $a['age'] !== null ? (int)$a['age'] : null,
                                 'km' => $km !== null ? round($km, 2) : null, 'eta_min' => $eta];
        }
    } elseif ($i['acknowledged_by']) {
        $q = db()->prepare('SELECT name, phone FROM providers WHERE id = ?'); $q->execute([$i['acknowledged_by']]); $p = $q->fetch();
        if ($p) $out['provider'] = ['name' => $p['name'], 'phone' => $digits($p['phone'])];
    }
}
json_out($out);
