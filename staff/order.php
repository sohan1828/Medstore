<?php
require_once __DIR__ . '/../includes/security.php';
secure_headers(); session_boot();
$me = require_staff();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$load = function () use ($id) { $q = db()->prepare('SELECT * FROM orders WHERE id = ?'); $q->execute([$id]); return $q->fetch(); };
$o = $load();
if (!$o) { http_response_code(404); exit('Order not found.'); }

$canRx = in_array($me['role'], RX_APPROVER_ROLES, true);
$next  = ['approved' => ['packed', 'Mark as packed'], 'packed' => ['out_for_delivery', 'Send out for delivery'], 'out_for_delivery' => ['delivered', 'Mark as delivered']];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    try {
        $pdo = db();
        if (in_array($a, ['approve_rx', 'reject_rx', 'photo_rx'], true)) {
            if (!$canRx) throw new RuntimeException('Only a pharmacist or the owner can review prescriptions.');
            if ($o['status'] !== 'rx_review') throw new RuntimeException('This order is not waiting for a prescription review.');
            $rx = $pdo->prepare("SELECT id FROM prescriptions WHERE order_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
            $rx->execute([$id]); $rx = $rx->fetch();
            if (!$rx) throw new RuntimeException('No prescription found for this order.');
            $note = substr(trim($_POST['note'] ?? ''), 0, 255);
            $pdo->beginTransaction();
            if ($a === 'approve_rx') {
                $pn = substr(trim($_POST['prescriber_name'] ?? ''), 0, 120);
                $pr = substr(trim($_POST['prescriber_reg'] ?? ''), 0, 60);
                $vm = $_POST['method'] ?? '';
                if ($pn === '' || $pr === '' || !in_array($vm, ['document_checked', 'registry_checked', 'prescriber_phoned'], true) || empty($_POST['confirm']))
                    throw new RuntimeException("Fill in the prescriber's name, registration number and how you checked it, and tick the confirmation.");
                $pdo->prepare("UPDATE prescriptions SET status='approved', prescriber_name=?, prescriber_reg_no=?, verification_method=?, reviewed_by=?, reviewed_at=NOW(), note=? WHERE id=?")
                    ->execute([$pn, $pr, $vm, $me['id'], $note, $rx['id']]);
                $pdo->prepare("UPDATE orders SET status='approved' WHERE id=? AND status='rx_review'")->execute([$id]);
                audit($me['id'], $id, 'rx_approved', "$vm; $pn; $pr");
            } elseif ($a === 'photo_rx') {
                $pdo->prepare("UPDATE prescriptions SET status='need_better_photo', reviewed_by=?, reviewed_at=NOW(), note=? WHERE id=?")->execute([$me['id'], $note, $rx['id']]);
                $pdo->prepare("UPDATE orders SET status='need_better_photo' WHERE id=? AND status='rx_review'")->execute([$id]);
                audit($me['id'], $id, 'rx_need_photo', $note);
            } else {
                if ($note === '') throw new RuntimeException('Write a short reason so the customer knows why.');
                $pdo->prepare("UPDATE prescriptions SET status='rejected', reviewed_by=?, reviewed_at=NOW(), note=? WHERE id=?")->execute([$me['id'], $note, $rx['id']]);
                $pdo->prepare("UPDATE orders SET status='rejected' WHERE id=? AND status='rx_review'")->execute([$id]);
                $pdo->prepare('UPDATE products p JOIN order_items i ON i.product_id = p.id SET p.stock = p.stock + i.qty WHERE i.order_id = ?')->execute([$id]);
                audit($me['id'], $id, 'rx_rejected', $note);
            }
            $pdo->commit();
        } elseif ($a === 'advance') {
            if (!isset($next[$o['status']])) throw new RuntimeException('This order cannot move forward from here.');
            $u = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND status = ?');
            $u->execute([$next[$o['status']][0], $id, $o['status']]);
            if ($u->rowCount()) audit($me['id'], $id, 'status_' . $next[$o['status']][0]);
        }
        header('Location: order.php?id=' . $id . '&ok=1'); exit;
    } catch (RuntimeException $x) {
        if (db()->inTransaction()) db()->rollBack();
        $err = $x->getMessage(); $o = $load();
    }
}

$items = db()->prepare('SELECT p.name, p.requires_rx, i.qty FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = ?');
$items->execute([$id]); $items = $items->fetchAll();
$rxs = db()->prepare('SELECT * FROM prescriptions WHERE order_id = ? ORDER BY id DESC'); $rxs->execute([$id]); $rxs = $rxs->fetchAll();
$stage = ['rx_review'=>'Prescription to review','need_better_photo'=>'Waiting for a new photo','approved'=>'Ready to pack','packed'=>'Packed','out_for_delivery'=>'Out for delivery','delivered'=>'Delivered','rejected'=>'Rejected','cancelled'=>'Cancelled'];
$emg = $o['delivery_type'] === 'emergency';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order #<?= $id ?></title><link rel="stylesheet" href="../assets/app.css"></head><body>
<main class="wrap wide">
<p><a href="index.php">All orders</a></p>
<h1>Order #<?= $id ?> <?php if ($emg): ?><span class="badge emg">Emergency</span><?php endif; ?></h1>
<?php if (isset($_GET['ok'])): ?><div class="okmsg">Saved.</div><?php endif; ?>
<?php if ($err): ?><div class="err" role="alert"><?= e($err) ?></div><?php endif; ?>

<div class="row2">
 <div class="box"><h2>Customer</h2>
  <?= e($o['customer_name']) ?><br><a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a><br>
  <?= nl2br(e($o['address'])) ?>
  <?php if ($o['lat'] !== null): ?><br><a href="https://maps.google.com/?q=<?= (float)$o['lat'] ?>,<?= (float)$o['lng'] ?>" rel="noopener">Open location on map</a><?php endif; ?>
  <p class="small muted">Delivery target: <?= e(DELIVERY_TARGET[$o['delivery_type']]) ?></p></div>
 <div class="box"><h2>Items</h2>
  <?php foreach ($items as $i): ?><div class="line"><span><?= e($i['name']) ?> x <?= (int)$i['qty'] ?> <?php if ($i['requires_rx']): ?><span class="rx">Prescription</span><?php endif; ?></span></div><?php endforeach; ?>
  <p><b>Stage:</b> <?= e($stage[$o['status']]) ?><br><b>Total:</b> Rs <?= number_format($o['total']) ?> (pay on delivery)</p></div>
</div>

<?php if ($rxs): ?><div class="box rxview"><h2>Prescription</h2>
<?php foreach ($rxs as $k => $r): ?>
  <p class="small muted">Upload <?= count($rxs) - $k ?>, status: <?= e($r['status']) ?><?= $r['note'] ? ', note: ' . e($r['note']) : '' ?>
  <?php if ($r['prescriber_name']): ?><br>Checked by prescriber record: <?= e($r['prescriber_name']) ?>, reg. <?= e($r['prescriber_reg_no']) ?> (<?= e($r['verification_method']) ?>)<?php endif; ?></p>
  <?php if ($k === 0): ?>
    <?php if (substr($r['file_name'], -4) === '.pdf'): ?><p><a class="btn ghost" href="rx_file.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener">Open PDF</a></p>
    <?php else: ?><img src="rx_file.php?id=<?= (int)$r['id'] ?>" alt="Prescription uploaded by the customer"><?php endif; ?>
  <?php endif; ?>
<?php endforeach; ?></div><?php endif; ?>

<?php if ($o['status'] === 'rx_review'): ?>
  <?php if ($canRx): ?>
  <form method="post" class="box"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <h2>Review the prescription</h2>
    <p class="small muted">Check it is genuine, current, signed by a registered doctor, and matches the items above. Never approve a prescription you are unsure about. Reject it or ask for a new photo instead.</p>
    <div class="row2"><div><label for="pn">Prescriber's name</label><input id="pn" name="prescriber_name" type="text" maxlength="120"></div>
    <div><label for="pr">Prescriber's registration number</label><input id="pr" name="prescriber_reg" type="text" maxlength="60"></div></div>
    <label for="m">How did you check it?</label>
    <select id="m" name="method"><option value="">Choose</option>
      <option value="document_checked">Read the document: complete, signed, dated</option>
      <option value="registry_checked">Checked the doctor in the council registry</option>
      <option value="prescriber_phoned">Phoned the prescriber's clinic</option></select>
    <label for="nt">Note (required if rejecting)</label><input id="nt" name="note" type="text" maxlength="255">
    <label class="opt"><input type="checkbox" name="confirm" value="1"><span>I have checked this prescription and take responsibility for approving it.</span></label>
    <p><button class="btn" name="action" value="approve_rx" type="submit">Approve</button>
       <button class="btn ghost" name="action" value="photo_rx" type="submit">Ask for a better photo</button>
       <button class="btn red" name="action" value="reject_rx" type="submit">Reject</button></p>
  </form>
  <?php else: ?><div class="box">Waiting for a pharmacist or the owner to review the prescription. Call them if this is an emergency order.</div><?php endif; ?>
<?php endif; ?>

<?php if (isset($next[$o['status']])): ?>
  <form method="post" class="box"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn" name="action" value="advance" type="submit"><?= e($next[$o['status']][1]) ?></button></form>
<?php endif; ?>
</main></body></html>
