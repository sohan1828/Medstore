<?php
// Usage: php tools/create_ambulance.php <plate> "<driver name>" <driver phone> [provider_id]
// Prints the driver's private sign-in code ONCE. Give it to the driver; only a hash is stored.
if (PHP_SAPI !== 'cli') exit('CLI only.');
require_once __DIR__ . '/../includes/lib.php';
[$self, $plate, $name, $phone, $prov] = $argv + [null, null, null, null, null];
if (!$plate || !$name || !$phone) exit("Usage: php tools/create_ambulance.php <plate> \"<driver name>\" <driver phone> [provider_id]\n");
$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; $code = '';
for ($i = 0; $i < 12; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
db()->prepare('INSERT INTO ambulances (provider_id, plate, driver_name, driver_phone, token_hash) VALUES (?,?,?,?,?)')
    ->execute([$prov !== null ? (int)$prov : null, $plate, $name, $phone, hash('sha256', $code)]);
echo "Created ambulance $plate for $name.\nDriver sign-in code (shown once): $code\n";
