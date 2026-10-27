<?php
require_once __DIR__ . '/includes/security.php';
secure_headers(); session_boot();

$t = $_GET['t'] ?? $_POST['t'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $t)) { http_response_code(404); exit('Order not found.'); }
$q = db()->prepare('SELECT * FROM orders WHERE order_token = ?'); $q->execute([$t]); $o = $q->fetch();
if (!$o) { http_response_code(404); exit('Order not found.'); }

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $o['status'] === 'need_better_photo') {
    csrf_check();
    try {
        $file = save_rx_upload($_FILES['rx'] ?? []);
        $pdo = db(); $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO prescriptions (order_id, file_name) VALUES (?,?)')->execute([$o['id'], $file]);
        $pdo->prepare("UPDATE orders SET status='rx_review' WHERE id = ? AND status='need_better_photo'")->execute([$o['id']]);
        $pdo->commit();
        audit(null, (int)$o['id'], 'rx_reuploaded');
        header('Location: track.php?t=' . $t . '&up=1'); exit;
    } catch (RuntimeException $x) { $err = $x->getMessage(); if (db()->inTransaction()) db()->rollBack(); }
}

$labels = [
  'rx_review'         => ['A pharmacist is checking your prescription', 'This usually takes a few minutes.'],
  'need_better_photo' => ['We need a clearer photo of your prescription', 'Upload another photo below.'],
  'approved'          => ['Approved. We are packing your order', ''],
  'packed'            => ['Packed and waiting for the rider', ''],
  'out_for_delivery'  => ['On its way to you', 'Keep your phone nearby.'],
  'delivered'         => ['Delivered', 'We hope you feel better soon.'],
  'rejected'          => ['We could not approve this order', 'Please call us. Your payment was never taken.'],
  'cancelled'         => ['Cancelled', ''],
];
[$title, $sub] = $labels[$o['status']];
$items = db()->prepare('SELECT p.name, i.qty, i.unit_price FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = ?');
$items->execute([$o['id']]); $items = $items->fetchAll();
$refresh = !in_array($o['status'], ['need_better_photo', 'delivered', 'rejected', 'cancelled'], true);
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Your order</title><link rel="stylesheet" href="assets/app.css">
<?php if ($refresh): ?><meta http-equiv="refresh" content="20"><?php endif; ?>
</head><body>
<div class="strip"><strong>Medical emergency?</strong><span><a href="tel:102">Call 102</a> &nbsp; <a href="sos.php">Request an ambulance</a></span></div>
<main class="wrap">
<?php if (isset($_GET['new'])): ?><div class="okmsg">Order placed. Save this page's link to check on your order any time.</div><script>try{localStorage.removeItem('cart')}catch(e){}</script><?php endif; ?>
<?php if (isset($_GET['up'])): ?><div class="okmsg">Thank you. A pharmacist will look at the new photo now.</div><?php endif; ?>
<h1><?= e($title) ?></h1>
<p class="muted"><?= e($sub) ?></p>
<p><span class="badge <?= $o['delivery_type'] === 'emergency' ? 'emg' : '' ?>"><?= $o['delivery_type'] === 'emergency' ? 'Emergency delivery' : 'Normal delivery' ?></span>
   <span class="small muted">Order #<?= (int)$o['id'] ?>, <?= e(DELIVERY_TARGET[$o['delivery_type']]) ?></span></p>

<?php if ($o['status'] === 'need_better_photo'): ?>
<form method="post" enctype="multipart/form-data" class="box">
  <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($t) ?>">
  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
  <label for="rx">New prescription photo or PDF</label>
  <input id="rx" type="file" name="rx" accept="image/jpeg,image/png,image/webp,application/pdf" required>
  <p class="small muted">Lay it flat in good light and make sure the doctor's name, date and medicines are readable.</p>
  <button class="btn" type="submit">Send photo</button>
</form>
<?php endif; ?>

<div class="box"><h2>Items</h2>
<?php foreach ($items as $i): ?><div class="line"><span><?= e($i['name']) ?> x <?= (int)$i['qty'] ?></span><span>Rs <?= number_format($i['unit_price'] * $i['qty']) ?></span></div><?php endforeach; ?>
<div class="tot"><span>Delivery</span><span>Rs <?= number_format($o['delivery_fee']) ?></span></div>
<div class="tot big"><span>Total, pay on delivery</span><span>Rs <?= number_format($o['total']) ?></span></div></div>
</main></body></html>
