<?php
// Setup check. Open http://localhost/medstore/check.php  (works from this computer only). DELETE before going live.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Local access only.'); }
error_reporting(E_ALL); ini_set('display_errors', '1');
require_once __DIR__ . '/config.php';

$rows = [];
$add = function (string $name, bool $ok, string $fix = '') use (&$rows) { $rows[] = [$name, $ok, $fix]; };

$add('PHP version ' . PHP_VERSION . ' (needs 7.4 or newer, 8.1+ recommended)', version_compare(PHP_VERSION, '7.4.0', '>='), 'Install a newer XAMPP.');
foreach (['pdo_mysql' => 'database', 'fileinfo' => 'prescription upload checks', 'mbstring' => 'text handling', 'session' => 'logins'] as $ext => $why)
    $add("PHP extension $ext ($why)", extension_loaded($ext), "In xampp\\php\\php.ini remove the ';' before extension=$ext, then restart Apache.");
$add('Folder for prescriptions is writable (' . RX_DIR . ')', is_dir(RX_DIR) ? is_writable(RX_DIR) : @mkdir(RX_DIR, 0750, true), 'Create the folder and allow Apache to write to it.');

$dbOk = false; $dbMsg = '';
try { $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $dbOk = true; }
catch (Throwable $e) { $dbMsg = $e->getMessage(); }
$fix = 'Start MySQL in the XAMPP control panel, open http://localhost/phpmyadmin, click Import, choose schema.sql, then check DB_USER / DB_PASS in config.php. Error: ' . $dbMsg;
$add('Database connection', $dbOk, $fix);
if ($dbOk) {
    foreach (['products','orders','staff','providers','ambulances','incidents'] as $t) {
        try { $n = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); $add("Table $t exists ($n rows)", true); }
        catch (Throwable $e) { $add("Table $t exists", false, 'Import schema.sql in phpMyAdmin.'); }
    }
}
$add('Shop homepage works without a database (USE_SAMPLE_DATA is ' . (USE_SAMPLE_DATA ? 'true' : 'false') . ')', true);
?>
<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup check</title><style>body{font-family:system-ui;max-width:760px;margin:24px auto;padding:0 16px}li{margin:10px 0}.ok{color:#1d6b3a}.no{color:#c8102e;font-weight:700}small{display:block;color:#51615e}</style>
<h1>Setup check</h1><ul>
<?php foreach ($rows as [$n, $ok, $fix]): ?>
<li class="<?= $ok ? 'ok' : 'no' ?>"><?= $ok ? 'OK' : 'PROBLEM' ?>: <?= htmlspecialchars($n) ?><?php if (!$ok): ?><small><?= htmlspecialchars($fix) ?></small><?php endif; ?></li>
<?php endforeach; ?></ul>
<p>Your pages: <a href="index.php">Home</a> | <a href="sos.php">SOS</a> | <a href="checkout.php">Checkout</a> | <a href="staff/login.php">Staff</a> | <a href="driver.php">Driver</a></p>
