<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/catalog.php';
secure_headers(); session_boot();

function fail(string $msg, int $code = 422): void {
    http_response_code($code);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/app.css">';
    echo '<div class="strip"><strong>Medical emergency?</strong><span><a href="tel:102">Call 102</a></span></div>';
    echo '<main class="wrap"><h1>We could not place the order</h1><div class="err">' . e($msg) . '</div>';
    echo '<p><a class="btn" href="checkout.php">Go back to checkout</a></p></main>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: checkout.php'); exit; }
csrf_check();

$name    = trim($_POST['name'] ?? '');
$phone   = preg_replace('/[\s-]/', '', $_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$type    = $_POST['delivery'] ?? '';
$lat = filter_var($_POST['lat'] ?? '', FILTER_VALIDATE_FLOAT);
$lng = filter_var($_POST['lng'] ?? '', FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) { $lat = $lng = null; }

if ($name === '' || mb_strlen($name) > 120) fail('Please enter your name.');
if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) fail('Please enter a valid phone number.');
if (mb_strlen($address) < 5 || mb_strlen($address) > 500) fail('Please enter your full address.');
if (!isset(DELIVERY_FEE[$type])) fail('Please choose a delivery option.');

// Cart: only ids and quantities are trusted from the browser. Prices come from our own catalog.
$raw = json_decode($_POST['cart'] ?? '[]', true);
if (!is_array($raw) || !$raw) fail('Your basket is empty.');
$byId = []; foreach (catalog() as $p) $byId[$p['id']] = $p;
$items = []; $subtotal = 0.0; $needsRx = false;
foreach ($raw as $id => $qty) {
    $id = (int)$id; $qty = (int)$qty;
    if ($qty < 1 || $qty > 20 || !isset($byId[$id])) fail('Something in your basket is no longer available. Please review it.');
    $p = $byId[$id];
    if ($p['controlled']) fail($p['name'] . ' cannot be sold online. Please visit the pharmacy in person.');
    $items[] = [$id, $qty, (float)$p['price']];
    $subtotal += $p['price'] * $qty;
    if ($p['rx']) $needsRx = true;
}

// Abuse guard: 5 orders per phone per hour
$c = db()->prepare('SELECT COUNT(*) c FROM orders WHERE phone = ? AND created_at > NOW() - INTERVAL 1 HOUR');
$c->execute([$phone]);
if ($c->fetch()['c'] >= 5) fail('Too many orders from this number. Please call us instead.', 429);

$file = null;
if ($needsRx) {
    try { $file = save_rx_upload($_FILES['rx'] ?? []); } catch (RuntimeException $x) { fail($x->getMessage()); }
}

$fee = (float)DELIVERY_FEE[$type];
$token = bin2hex(random_bytes(16));
try {
    $pdo = db(); $pdo->beginTransaction();
    foreach ($items as [$id, $qty, $price]) {     // lock rows so two buyers cannot take the last item
        $s = $pdo->prepare('SELECT stock FROM products WHERE id = ? AND active = 1 FOR UPDATE'); $s->execute([$id]);
        $row = $s->fetch();
        if (!$row || $row['stock'] < $qty) throw new RuntimeException('Sorry, "' . $byId[$id]['name'] . '" is out of stock or has too little left.');
        $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?')->execute([$qty, $id]);
    }
    $pdo->prepare('INSERT INTO orders (order_token, customer_name, phone, address, lat, lng, delivery_type, delivery_fee, subtotal, total, needs_rx, status)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$token, $name, $phone, $address, $lat, $lng, $type, $fee, $subtotal, $subtotal + $fee, (int)$needsRx, $needsRx ? 'rx_review' : 'approved']);
    $oid = (int)$pdo->lastInsertId();
    foreach ($items as [$id, $qty, $price])
        $pdo->prepare('INSERT INTO order_items (order_id, product_id, qty, unit_price) VALUES (?,?,?,?)')->execute([$oid, $id, $qty, $price]);
    if ($needsRx)
        $pdo->prepare('INSERT INTO prescriptions (order_id, file_name) VALUES (?,?)')->execute([$oid, $file]);
    $pdo->commit();
} catch (Throwable $x) {
    if (db()->inTransaction()) db()->rollBack();
    if ($file) @unlink(RX_DIR . '/' . $file);
    if ($x instanceof RuntimeException) fail($x->getMessage());
    error_log('order_place: ' . $x->getMessage());
    fail('Something went wrong on our side. Your order was not placed. Please try again, or call us.', 500);
}

audit(null, $oid, 'order_placed', $type . ($needsRx ? ' rx' : ''));
if ($type === 'emergency') send_sms(STAFF_ALERT_PHONE, "URGENT medicine order #$oid" . ($needsRx ? ' (prescription to review)' : ' (ready to pack)'));

header('Location: track.php?t=' . $token . '&new=1');
