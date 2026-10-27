<?php
require_once __DIR__ . '/../includes/security.php';
secure_headers(); session_boot();
$me = require_staff(['owner', 'dispatcher']);
$err = ''; $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? ''; $iid = (int)($_POST['incident_id'] ?? 0);
    try {
        if ($a === 'assign') {
            $aid = (int)($_POST['ambulance_id'] ?? 0);
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT id, provider_id, driver_phone, plate FROM ambulances WHERE id = ? AND active = 1 AND status = 'available' FOR UPDATE");
            $q->execute([$aid]); $amb = $q->fetch();
            if (!$amb) throw new RuntimeException('That ambulance is no longer available.');
            $u = $pdo->prepare("UPDATE incidents SET status='acknowledged', ambulance_id=?, acknowledged_by=?, accepted_at=NOW() WHERE id=? AND status IN ('open','needs_staff','call_fallback')");
            $u->execute([$amb['id'], $amb['provider_id'], $iid]);
            if (!$u->rowCount()) throw new RuntimeException('Someone already handled this incident.');
            $pdo->prepare("UPDATE incident_attempts SET outcome='expired' WHERE incident_id=? AND outcome='pending'")->execute([$iid]);
            $pdo->prepare("INSERT INTO incident_attempts (incident_id, ambulance_id, notified_at, outcome) VALUES (?,?,NOW(),'accepted')")->execute([$iid, $amb['id']]);
            $pdo->prepare("UPDATE ambulances SET status='busy' WHERE id=?")->execute([$amb['id']]);
            $pdo->commit();
            audit($me['id'], null, 'incident_assigned', "incident $iid -> {$amb['plate']}");
            send_sms($amb['driver_phone'], 'The control room assigned you an emergency. Open your driver page now: ' . SITE_URL . '/driver.php');
        } elseif ($a === 'next') {
            $pdo->prepare("UPDATE incidents SET ack_deadline = NOW() WHERE id=? AND status='open'")->execute([$iid]);
            audit($me['id'], null, 'incident_offer_next', "incident $iid");
        } elseif ($a === 'close') {
            $to = ($_POST['to'] ?? '') === 'cancelled' ? 'cancelled' : 'completed';
            $q = $pdo->prepare('SELECT ambulance_id FROM incidents WHERE id = ?'); $q->execute([$iid]); $row = $q->fetch();
            $pdo->prepare("UPDATE incidents SET status=? WHERE id=? AND status IN ('open','needs_staff','call_fallback','acknowledged','arrived')")->execute([$to, $iid]);
            if ($row && $row['ambulance_id']) $pdo->prepare("UPDATE ambulances SET status='available' WHERE id=? AND status='busy'")->execute([$row['ambulance_id']]);
            audit($me['id'], null, 'incident_' . $to, "incident $iid");
        }
        header('Location: control.php'); exit;
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
}

$types = ['heart'=>'Heart attack / chest pain','stroke'=>'Stroke','choking'=>'Choking','bleeding'=>'Severe bleeding','accident'=>'Accident','general'=>'Other emergency'];
$incs = $pdo->query("SELECT i.*, TIMESTAMPDIFF(SECOND, i.created_at, NOW()) AS age_s, a.plate, a.driver_name, a.driver_phone, h.name AS dest_name
    FROM incidents i LEFT JOIN ambulances a ON a.id = i.ambulance_id LEFT JOIN providers h ON h.id = i.destination_id
    WHERE i.status IN ('open','needs_staff','call_fallback','acknowledged','arrived')
    ORDER BY FIELD(i.status,'needs_staff','call_fallback','open','acknowledged','arrived'), i.created_at")->fetchAll();
$ambs = $pdo->query("SELECT id, plate, driver_name, driver_phone, status, lat, lng, TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS age FROM ambulances WHERE active = 1 ORDER BY FIELD(status,'available','busy','offline'), plate")->fetchAll();
$free = array_values(array_filter($ambs, fn($x) => $x['status'] === 'available'));
$mmss = fn($s) => sprintf('%d:%02d', intdiv((int)$s, 60), (int)$s % 60);

$attn = 0;
foreach ($incs as $i) if (in_array($i['status'], ['needs_staff', 'call_fallback'], true) || ($i['status'] === 'open' && $i['age_s'] > 90)) $attn++;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Control room</title><link rel="stylesheet" href="../assets/app.css"></head><body>
<main class="wrap wide">
<div class="line"><h1>Emergency control room</h1><span><a href="index.php">Orders</a> &nbsp; <button class="btn ghost" id="snd" type="button">Turn sound on</button></span></div>
<p class="small muted">Updates every 5 seconds. A tone plays when an incident needs a person. Tap "Turn sound on" once after opening this page.</p>
<?php if ($err): ?><div class="err" role="alert"><?= e($err) ?></div><?php endif; ?>

<div id="live" data-attn="<?= $attn ?>">
<h2>Incidents (<?= count($incs) ?>)</h2>
<?php if (!$incs): ?><div class="box">No active incidents.</div><?php endif; ?>
<?php foreach ($incs as $i):
    $needs = in_array($i['status'], ['needs_staff', 'call_fallback'], true) || ($i['status'] === 'open' && $i['age_s'] > 90);
    $canAssign = in_array($i['status'], ['open', 'needs_staff', 'call_fallback'], true);
    $opts = [];
    foreach ($free as $f) {
        $km = ($i['lat'] !== null && $f['lat'] !== null) ? haversine_km((float)$i['lat'], (float)$i['lng'], (float)$f['lat'], (float)$f['lng']) : null;
        $opts[] = [$f, $km];
    }
    usort($opts, fn($x, $y) => ($x[1] ?? 1e9) <=> ($y[1] ?? 1e9));
?>
<div class="box" style="<?= $needs ? 'border:3px solid var(--sos)' : '' ?>">
  <div class="line"><h2>#<?= (int)$i['id'] ?> <?= e($types[$i['emergency_type']] ?? 'Emergency') ?>
     <span class="badge <?= $needs ? 'emg' : '' ?>"><?= e(str_replace('_', ' ', $i['status'])) ?></span></h2>
     <b><?= $mmss($i['age_s']) ?></b></div>
  <p>Contact: <a href="tel:<?= e($i['phone']) ?>"><?= e($i['phone']) ?></a>
     <?php if ($i['for_other']): ?> (booking for someone else)<?php endif; ?>
     <?php if ($i['patient_name']): ?><br>Patient: <?= e($i['patient_name']) ?><?php if ($i['patient_phone']): ?>, <a href="tel:<?= e($i['patient_phone']) ?>"><?= e($i['patient_phone']) ?></a><?php endif; ?><?php endif; ?>
     <?php if ($i['address_text']): ?><br>Address: <?= e($i['address_text']) ?><?php endif; ?>
     <?php if ($i['lat'] !== null): ?><br><a href="https://maps.google.com/?q=<?= (float)$i['lat'] ?>,<?= (float)$i['lng'] ?>" target="_blank" rel="noopener">Open GPS location</a><?php else: ?><br><span class="muted">No GPS. Use the address above.</span><?php endif; ?>
     <?php if ($i['dest_name']): ?><br>Going to: <?= e($i['dest_name']) ?><?php endif; ?>
     <?php if ($i['plate']): ?><br>Ambulance: <?= e($i['plate']) ?>, <?= e($i['driver_name']) ?>, <a href="tel:<?= e($i['driver_phone']) ?>"><?= e($i['driver_phone']) ?></a><?php endif; ?></p>

  <?php if ($canAssign): ?>
  <form method="post" class="line"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="incident_id" value="<?= (int)$i['id'] ?>">
    <select name="ambulance_id" aria-label="Ambulance to assign" required>
      <option value="">Choose an available ambulance</option>
      <?php foreach ($opts as [$f, $km]): ?><option value="<?= (int)$f['id'] ?>"><?= e($f['plate'] . ', ' . $f['driver_name']) ?><?= $km !== null ? ', ' . round($km, 1) . ' km' : ', no GPS' ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Assign</button></form>
  <?php endif; ?>
  <div class="line">
  <?php if ($i['status'] === 'open'): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="next"><input type="hidden" name="incident_id" value="<?= (int)$i['id'] ?>"><button class="btn ghost" type="submit">Offer to next responder now</button></form>
  <?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="incident_id" value="<?= (int)$i['id'] ?>"><input type="hidden" name="to" value="completed"><button class="btn ghost" type="submit">Close as completed</button></form>
    <form method="post" onsubmit="return confirm('Close this as a false alarm / cancelled?')"><?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="incident_id" value="<?= (int)$i['id'] ?>"><input type="hidden" name="to" value="cancelled"><button class="btn ghost" type="submit">Cancel</button></form>
  </div>
</div>
<?php endforeach; ?>

<h2>Ambulances</h2>
<table><tr><th>Plate</th><th>Driver</th><th>Status</th><th>GPS</th></tr>
<?php foreach ($ambs as $x): $stale = $x['status'] !== 'offline' && ($x['age'] === null || $x['age'] > GPS_FRESH_SECONDS); ?>
<tr><td><?= e($x['plate']) ?></td><td><?= e($x['driver_name']) ?>, <a href="tel:<?= e($x['driver_phone']) ?>"><?= e($x['driver_phone']) ?></a></td>
<td><?= e($x['status']) ?></td><td><?= $x['status'] === 'offline' ? '' : ($stale ? '<b style="color:var(--sos)">No recent GPS</b>' : 'Live, ' . (int)$x['age'] . ' s ago') ?></td></tr>
<?php endforeach; ?></table>
</div>
</main>
<script>
const $ = id => document.getElementById(id);
let ctx = null, prev = Number($('live').dataset.attn);
function beep() { if (!ctx) return; const o = ctx.createOscillator(); o.frequency.value = 880; o.connect(ctx.destination); o.start(); setTimeout(() => o.stop(), 500); }
$('snd').onclick = () => { ctx = new (window.AudioContext || window.webkitAudioContext)(); beep(); $('snd').textContent = 'Sound is on'; };
setInterval(async () => {
  if (document.querySelector('#live :focus')) return;       // do not disturb someone choosing an ambulance
  try {
    const t = await (await fetch(location.pathname, {cache: 'no-store', credentials: 'same-origin'})).text();
    const n = new DOMParser().parseFromString(t, 'text/html').getElementById('live');
    if (!n) { location.reload(); return; }
    $('live').innerHTML = n.innerHTML;
    const a = Number(n.dataset.attn); if (a > prev) beep(); prev = a;
  } catch (e) {}
}, 5000);
</script></body></html>
