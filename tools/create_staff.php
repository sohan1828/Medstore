<?php
// Usage: php tools/create_staff.php <username> <owner|pharmacist|worker|dispatcher>
if (PHP_SAPI !== 'cli') exit('CLI only.');
require_once __DIR__ . '/../includes/lib.php';
[$self, $user, $role] = $argv + [null, null, null];
if (!$user || !in_array($role, ['owner', 'pharmacist', 'worker', 'dispatcher'], true)) exit("Usage: php tools/create_staff.php <username> <owner|pharmacist|worker|dispatcher>\n");
echo "Password (min 12 characters): ";
system('stty -echo 2>/dev/null'); $pw = trim(fgets(STDIN)); system('stty echo 2>/dev/null'); echo "\n";
if (strlen($pw) < 12) exit("Password too short.\n");
db()->prepare('INSERT INTO staff (username, password_hash, role) VALUES (?,?,?)')->execute([$user, password_hash($pw, PASSWORD_DEFAULT), $role]);
echo "Created $role '$user'.\n";
