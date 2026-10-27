<?php
require_once __DIR__ . '/../includes/security.php';
secure_headers(); session_boot();
$me = require_staff();
$q = db()->prepare('SELECT file_name, order_id FROM prescriptions WHERE id = ?'); $q->execute([(int)($_GET['id'] ?? 0)]); $r = $q->fetch();
$path = $r ? realpath(RX_DIR . '/' . basename($r['file_name'])) : false;
if (!$path || strpos($path, realpath(RX_DIR)) !== 0 || !is_file($path)) { http_response_code(404); exit('Not found.'); }
audit($me['id'], (int)$r['order_id'], 'rx_viewed');
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($path));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('Content-Disposition: inline');
readfile($path);
