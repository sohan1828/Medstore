<?php
require_once __DIR__ . '/../includes/security.php';
$a = require_driver_api();

$o = db()->prepare("SELECT i.id, i.emergency_type, i.lat, i.lng, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), i.ack_deadline)) AS left_s
    FROM incidents i JOIN incident_attempts t ON t.incident_id = i.id AND t.ambulance_id = ? AND t.outcome = 'pending'
    WHERE i.status = 'open' AND i.ack_deadline > NOW() LIMIT 1");
$o->execute([$a['id']]); $o = $o->fetch();
$offer = null;
if ($o) {   // before accepting, the driver only sees the type and distance, not the exact address
    $km = $a['lat'] !== null ? round(haversine_km((float)$a['lat'], (float)$a['lng'], (float)$o['lat'], (float)$o['lng']), 1) : null;
    $offer = ['incident_id' => (int)$o['id'], 'type' => $o['emergency_type'], 'km' => $km, 'seconds_left' => (int)$o['left_s']];
}
$j = db()->prepare("SELECT i.id, i.status, i.emergency_type, i.phone, i.lat, i.lng, i.patient_name, i.address_text,
        h.name AS dname, h.phone AS dphone
    FROM incidents i LEFT JOIN providers h ON h.id = i.destination_id
    WHERE i.ambulance_id = ? AND i.status IN ('acknowledged','arrived') LIMIT 1");
$j->execute([$a['id']]); $j = $j->fetch();
$job = $j ? ['incident_id' => (int)$j['id'], 'status' => $j['status'], 'type' => $j['emergency_type'],
             'phone' => preg_replace('/[^0-9+]/', '', $j['phone']), 'patient_name' => $j['patient_name'], 'address' => $j['address_text'],
             'lat' => $j['lat'] !== null ? (float)$j['lat'] : null, 'lng' => $j['lng'] !== null ? (float)$j['lng'] : null,
             'destination' => $j['dname'] ? ['name' => $j['dname'], 'phone' => preg_replace('/[^0-9+]/', '', $j['dphone'])] : null] : null;

json_out(['status' => $a['status'], 'driver' => $a['driver_name'], 'plate' => $a['plate'], 'offer' => $offer, 'job' => $job]);
