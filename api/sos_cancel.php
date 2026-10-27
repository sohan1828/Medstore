<?php
require_once __DIR__ . '/../includes/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);
$in = json_decode(file_get_contents('php://input'), true) ?? [];
$q = db()->prepare("SELECT id, ambulance_id FROM incidents WHERE public_token = ? AND status IN ('open','needs_staff','call_fallback','acknowledged')");
$q->execute([$in['token'] ?? '']); $i = $q->fetch();
if (!$i) json_out(['error' => 'Nothing to cancel'], 404);
db()->prepare("UPDATE incidents SET status='cancelled' WHERE id = ?")->execute([$i['id']]);
if ($i['ambulance_id']) db()->prepare("UPDATE ambulances SET status='available' WHERE id = ? AND status='busy'")->execute([$i['ambulance_id']]);
json_out(['ok' => true]);
