<?php
require_once __DIR__ . '/includes/security.php';
secure_headers(); session_boot();
$err = '';

if (isset($_POST['logout'])) { csrf_check(); $_SESSION = []; session_destroy(); header('Location: driver.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    csrf_check(); $ip = client_ip();
    $n = db()->prepare("SELECT COUNT(*) c FROM login_attempts WHERE ip = ? AND username = 'driver' AND at > NOW() - INTERVAL 15 MINUTE");
    $n->execute([$ip]);
    if ($n->fetch()['c'] >= 10) {
        $err = 'Too many attempts. Wait 15 minutes and try again.';
    } else {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? ''));
        $q = db()->prepare('SELECT id FROM ambulances WHERE token_hash = ? AND active = 1');
        $q->execute([hash('sha256', $code)]); $row = $q->fetch();
        if ($row) { session_regenerate_id(true); $_SESSION['driver_id'] = (int)$row['id']; header('Location: driver.php'); exit; }
        db()->prepare("INSERT INTO login_attempts (ip, username) VALUES (?, 'driver')")->execute([$ip]);
        $err = 'That code is not recognised.';
    }
}

$driver = null;
if (!empty($_SESSION['driver_id'])) {
    $q = db()->prepare('SELECT driver_name, plate FROM ambulances WHERE id = ? AND active = 1');
    $q->execute([$_SESSION['driver_id']]); $driver = $q->fetch() ?: null;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Driver</title><link rel="stylesheet" href="assets/app.css"></head><body>
<main class="wrap">
<?php if (!$driver): ?>
  <h1>Ambulance driver sign in</h1>
  <?php if ($err): ?><div class="err" role="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="box"><?= csrf_field() ?>
    <label for="code">Your driver code</label>
    <input id="code" name="code" type="text" autocomplete="off" autocapitalize="characters" required>
    <p><button class="btn" type="submit">Sign in</button></p>
  </form>
<?php else: ?>
  <div class="line"><h1><?= e($driver['plate']) ?></h1>
    <form method="post"><?= csrf_field() ?><button class="btn ghost" name="logout" value="1" type="submit">Sign out</button></form></div>
  <p class="muted" id="who"><?= e($driver['driver_name']) ?></p>

  <div class="box">
    <p><b id="duty">Off duty</b><br><span class="small muted" id="gps">Location is not being shared.</span></p>
    <button class="btn" id="dutybtn" type="button">Go on duty</button>
    <p class="small muted">Keep this page open and the screen on while you are on duty. If you close it, patients cannot see you and you stop receiving requests.</p>
  </div>

  <div class="box hidden" id="offer" style="border:3px solid var(--sos)">
    <h2>Emergency request</h2>
    <p id="offerinfo"></p>
    <p class="tot big"><span>Respond within</span><span id="left"></span></p>
    <button class="btn red" id="accept" type="button">Accept</button>
    <button class="btn ghost" id="decline" type="button">Decline</button>
  </div>

  <div class="box hidden" id="job">
    <h2>Active job</h2>
    <p id="jobinfo"></p>
    <p><a class="btn" id="callpt" href="#">Call patient</a> <a class="btn ghost" id="mappt" href="#" target="_blank" rel="noopener">Open patient location</a></p>
    <button class="btn" id="arrived" type="button">I have arrived</button>
    <button class="btn ghost" id="complete" type="button">Trip complete</button>
  </div>
  <div id="msg" class="err hidden" role="alert"></div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const TYPES = {heart:'Heart attack or chest pain',stroke:'Stroke',choking:'Choking',bleeding:'Severe bleeding',accident:'Accident',general:'Other emergency'};
const $ = s => document.querySelector(s);
let status = 'offline', watchId = null, lastSent = 0, lastOffer = null, offerLeft = 0, wake = null;

async function api(path, body) {
  try {
    const r = await fetch('api/' + path, body ? {method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF}, body:JSON.stringify(body)} : {});
    if (r.status === 401) { location.reload(); return {}; }
    return await r.json();
  } catch (e) { return {error: 'No connection'}; }
}
function err(t) { const m = $('#msg'); m.textContent = t || ''; m.classList.toggle('hidden', !t); }
function beep() { try { navigator.vibrate && navigator.vibrate([300,150,300]); const c = new (window.AudioContext||window.webkitAudioContext)(), o = c.createOscillator(); o.frequency.value = 880; o.connect(c.destination); o.start(); setTimeout(() => { o.stop(); c.close(); }, 450); } catch (e) {} }

function startGps() {
  if (watchId !== null || !navigator.geolocation) return;
  watchId = navigator.geolocation.watchPosition(p => {
    $('#gps').textContent = 'Sharing your location (accuracy about ' + Math.round(p.coords.accuracy) + ' m).';
    if (Date.now() - lastSent > 5000) { lastSent = Date.now(); api('driver_ping.php', {lat: p.coords.latitude, lng: p.coords.longitude, acc: Math.round(p.coords.accuracy)}); }
  }, () => { $('#gps').textContent = 'Cannot read your location. Allow location access for this page.'; },
  {enableHighAccuracy: true, maximumAge: 2000, timeout: 15000});
  if ('wakeLock' in navigator) navigator.wakeLock.request('screen').then(w => wake = w).catch(() => {});
}
function stopGps() {
  if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
  if (wake) { wake.release().catch(() => {}); wake = null; }
  $('#gps').textContent = 'Location is not being shared.';
}
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && status !== 'offline' && 'wakeLock' in navigator) navigator.wakeLock.request('screen').then(w => wake = w).catch(() => {}); });

async function refresh() {
  const d = await api('driver_state.php'); if (!d.status) return;
  status = d.status;
  $('#duty').textContent = status === 'offline' ? 'Off duty' : status === 'busy' ? 'On a job' : 'On duty, waiting for requests';
  $('#dutybtn').textContent = status === 'offline' ? 'Go on duty' : 'Go off duty';
  $('#dutybtn').disabled = status === 'busy';
  if (status !== 'offline') startGps(); else stopGps();

  const o = d.offer; $('#offer').classList.toggle('hidden', !o);
  if (o) {
    if (lastOffer !== o.incident_id) { lastOffer = o.incident_id; beep(); }
    offerLeft = o.seconds_left;
    $('#offerinfo').textContent = (TYPES[o.type] || 'Emergency') + (o.km !== null ? ', about ' + o.km + ' km from you.' : '.');
    $('#left').textContent = offerLeft + ' s';
    $('#accept').dataset.id = o.incident_id; $('#decline').dataset.id = o.incident_id;
  } else lastOffer = null;

  const j = d.job; $('#job').classList.toggle('hidden', !j);
  if (j) {
    $('#jobinfo').textContent = (TYPES[j.type] || 'Emergency') + '. Phone: ' + j.phone + (j.patient_name ? '. Patient: ' + j.patient_name : '') +
      (j.address ? '. Address: ' + j.address : '') +
      (j.destination ? '. Take the patient to: ' + j.destination.name + ' (' + j.destination.phone + ')' : '. Hospital not chosen yet: ask the patient') +
      '. Status: ' + (j.status === 'arrived' ? 'arrived' : 'on the way');
    $('#callpt').href = 'tel:' + j.phone;
    $('#mappt').classList.toggle('hidden', j.lat === null);
    if (j.lat !== null) $('#mappt').href = 'https://maps.google.com/?q=' + j.lat + ',' + j.lng;
    $('#arrived').dataset.id = j.incident_id; $('#complete').dataset.id = j.incident_id;
    $('#arrived').disabled = j.status === 'arrived';
  }
}
async function act(action, id) { err(''); const d = await api('driver_action.php', {action, incident_id: Number(id) || 0}); if (d.error) err(d.error); refresh(); }
$('#dutybtn').onclick = () => { beep(); act(status === 'offline' ? 'duty_on' : 'duty_off'); };   // the tap also unlocks sound
$('#accept').onclick  = e => act('accept', e.target.dataset.id);
$('#decline').onclick = e => act('decline', e.target.dataset.id);
$('#arrived').onclick = e => act('arrived', e.target.dataset.id);
$('#complete').onclick = e => act('complete', e.target.dataset.id);
setInterval(() => { if (offerLeft > 0 && !$('#offer').classList.contains('hidden')) { offerLeft--; $('#left').textContent = offerLeft + ' s'; } }, 1000);
setInterval(refresh, 3000); refresh();
</script>
<?php endif; ?>
</main></body></html>
