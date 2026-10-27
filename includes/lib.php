<?php
require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function haversine_km(float $la1, float $lo1, float $la2, float $lo2): float {
    $dla = deg2rad($la2 - $la1); $dlo = deg2rad($lo2 - $lo1);
    $a = sin($dla / 2) ** 2 + cos(deg2rad($la1)) * cos(deg2rad($la2)) * sin($dlo / 2) ** 2;
    return 2 * 6371 * asin(min(1, sqrt($a)));
}

/** Rough arrival estimate: straight-line distance x1.3 for roads, at 30 km/h city speed. Always shown as an estimate. */
function eta_minutes(float $km): int { return max(1, (int)ceil(($km * 1.3) / 30 * 60)); }

/** Fare estimate range in Rs for a trip of $km straight-line km. An estimate only: the operator sets the real fare. */
function fare_range(float $km): array {
    $mid = max(FARE_MIN, FARE_BASE + FARE_PER_KM * $km * 1.3);
    return [(int)(round($mid * 0.85 / 10) * 10), (int)(round($mid * 1.2 / 10) * 10)];
}

/** Nearest available ambulance with a fresh GPS fix that has not been offered this incident yet. */
function nearest_ambulance(float $lat, float $lng, int $incidentId): ?array {
    $sql = "SELECT a.*, (6371 * ACOS(LEAST(1,
              COS(RADIANS(:lat1)) * COS(RADIANS(a.lat)) * COS(RADIANS(a.lng) - RADIANS(:lng)) +
              SIN(RADIANS(:lat2)) * SIN(RADIANS(a.lat))))) AS km
            FROM ambulances a
            WHERE a.active = 1 AND a.status = 'available' AND a.lat IS NOT NULL
              AND a.last_seen > NOW() - INTERVAL " . (int)GPS_FRESH_SECONDS . " SECOND
              AND a.id NOT IN (SELECT ambulance_id FROM incident_attempts WHERE incident_id = :iid AND ambulance_id IS NOT NULL)
            HAVING km <= " . (int)MAX_AMBULANCE_KM . "
            ORDER BY km ASC LIMIT 1";
    $st = db()->prepare($sql);
    $st->execute([':lat1' => $lat, ':lat2' => $lat, ':lng' => $lng, ':iid' => $incidentId]);
    return $st->fetch() ?: null;
}

/** Nearest active provider (phone flow) not yet tried for this incident. */
function nearest_provider(float $lat, float $lng, int $incidentId): ?array {
    $sql = "SELECT p.*, (6371 * ACOS(LEAST(1,
              COS(RADIANS(:lat1)) * COS(RADIANS(p.lat)) * COS(RADIANS(p.lng) - RADIANS(:lng)) +
              SIN(RADIANS(:lat2)) * SIN(RADIANS(p.lat))))) AS km
            FROM providers p
            WHERE p.active = 1
              AND p.id NOT IN (SELECT provider_id FROM incident_attempts WHERE incident_id = :iid AND provider_id IS NOT NULL)
            ORDER BY km ASC LIMIT 1";
    $st = db()->prepare($sql);
    $st->execute([':lat1' => $lat, ':lat2' => $lat, ':lng' => $lng, ':iid' => $incidentId]);
    return $st->fetch() ?: null;
}

/**
 * Offer the incident to the next best responder and restart the 30 second clock.
 * 1) nearest online ambulance (live GPS, accepts in the driver page)
 * 2) otherwise nearest provider by SMS (no live tracking)
 * Returns false when nobody is left to try.
 */
function dispatch_next(array $inc): bool {
    $iid = (int)$inc['id'];
    db()->prepare("UPDATE incident_attempts SET outcome='expired' WHERE incident_id = ? AND outcome = 'pending'")->execute([$iid]);

    $a = nearest_ambulance((float)$inc['lat'], (float)$inc['lng'], $iid);
    if ($a) {
        db()->prepare('INSERT INTO incident_attempts (incident_id, ambulance_id, notified_at) VALUES (?,?,NOW())')->execute([$iid, $a['id']]);
        db()->prepare('UPDATE incidents SET ack_deadline = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?')->execute([ACK_SECONDS, $iid]);
        send_sms($a['driver_phone'], 'Emergency request ' . round($a['km'], 1) . ' km away. Open your driver page now to accept: ' . SITE_URL . '/driver.php');
        return true;
    }

    $p = nearest_provider((float)$inc['lat'], (float)$inc['lng'], $iid);
    if (!$p) return false;
    db()->prepare('INSERT INTO incident_attempts (incident_id, provider_id, notified_at) VALUES (?,?,NOW())')->execute([$iid, $p['id']]);
    db()->prepare('UPDATE incidents SET ack_deadline = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?')->execute([ACK_SECONDS, $iid]);
    $maps = "https://maps.google.com/?q={$inc['lat']},{$inc['lng']}";
    send_sms($p['phone'], "EMERGENCY ({$inc['emergency_type']}). Patient phone {$inc['phone']}. Location {$maps}. Acknowledge: " . SITE_URL . "/provider_ack.php?incident={$iid}");
    return true;
}

/** Plug a real SMS gateway here (Sparrow SMS, Twilio, etc.). */
function send_sms(string $to, string $msg): void { error_log("[SMS to $to] $msg"); }

/** Plug a voice-call provider (e.g. Twilio Voice) here. */
function place_fallback_call(array $inc): void { error_log("[CALL] fallback for incident {$inc['id']} at {$inc['lat']},{$inc['lng']}"); }
