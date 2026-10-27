<?php
require_once __DIR__ . '/../includes/security.php';
secure_headers(); session_boot();
$me = require_staff();
$rows = db()->query("SELECT id, customer_name, delivery_type, status, needs_rx, total, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS age
                     FROM orders
                     WHERE status IN ('rx_review','approved','packed','out_for_delivery')
                     ORDER BY (delivery_type = 'emergency') DESC, created_at ASC LIMIT 200")->fetchAll();
$names = ['rx_review'=>'Prescription to review','approved'=>'Ready to pack','packed'=>'Packed','out_for_delivery'=>'Out for delivery'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Orders</title><link rel="stylesheet" href="../assets/app.css"><meta http-equiv="refresh" content="15"></head><body>
<main class="wrap wide">
<div class="line"><h1>Open orders</h1>
  <form method="post" action="logout.php"><?= csrf_field() ?><span class="small muted"><?= e($me['username']) ?> (<?= e($me['role']) ?>) </span><button class="btn ghost" type="submit">Sign out</button></form></div>
<?php if (in_array($me['role'], ['owner', 'dispatcher'], true)): ?><p><a class="btn red" href="control.php">Open the emergency control room</a></p><?php endif; ?>
<p class="small muted">Emergency orders are listed first. This page refreshes every 15 seconds.</p>
<?php if (!$rows): ?><div class="box">No open orders.</div><?php else: ?>
<table><tr><th>Order</th><th>Customer</th><th>Stage</th><th>Waiting</th><th>Total</th></tr>
<?php foreach ($rows as $r): ?>
<tr class="<?= $r['delivery_type'] === 'emergency' ? 'emg' : '' ?>">
  <td><a href="order.php?id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a>
      <?php if ($r['delivery_type'] === 'emergency'): ?><span class="badge emg">Emergency</span><?php endif; ?></td>
  <td><?= e($r['customer_name']) ?></td>
  <td><?= e($names[$r['status']]) ?></td>
  <td><?= (int)$r['age'] ?> min</td>
  <td>Rs <?= number_format($r['total']) ?></td></tr>
<?php endforeach; ?></table><?php endif; ?>
</main></body></html>
