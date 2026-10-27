<?php
require_once __DIR__ . '/../includes/lib.php';
$q = db()->prepare('SELECT lat, lng, destination_id FROM incidents WHERE public_token = ?');
$q->execute([$_GET['token'] ?? '']); $i = $q->fetch();
if (!$i) json_out(['error' => 'Not found'], 404);

$list = [];
foreach (db()->query("SELECT id, name, lat, lng FROM providers WHERE type = 'hospital' AND active = 1")->fetchAll() as $h) {
    $row = ['id' => (int)$h['id'], 'name' => $h['name'], 'km' => null, 'fare_lo' => null, 'fare_hi' => null];
    if ($i['lat'] !== null) {
        $km = haversine_km((float)$i['lat'], (float)$i['lng'], (float)$h['lat'], (float)$h['lng']);
        [$lo, $hi] = fare_range($km);
        $row['km'] = round($km, 1); $row['fare_lo'] = $lo; $row['fare_hi'] = $hi;
    }
    $list[] = $row;
}
usort($list, fn($a, $b) => ($a['km'] ?? 1e9) <=> ($b['km'] ?? 1e9) ?: strcmp($a['name'], $b['name']));
json_out(['hospitals' => array_slice($list, 0, 8), 'selected' => $i['destination_id'] ? (int)$i['destination_id'] : null]);
