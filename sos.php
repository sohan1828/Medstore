<?php
require_once __DIR__ . '/includes/i18n.php';
$L = strings(); $FA = (require __DIR__ . '/includes/firstaid.php')[lang()];
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="<?= lang() ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($L['title']) ?></title><link rel="stylesheet" href="assets/sos.css">
</head><body>
<p class="lang"><a href="?lang=<?= $L['other_code'] ?>"><?= $h($L['other_lang']) ?></a></p>
<p class="notice"><?= $h($L['notice']) ?></p>
<a class="call" href="tel:102"><?= $h($L['call102']) ?></a>

<div id="form">
  <p class="q"><?= $h($L['who']) ?></p>
  <label class="r"><input type="radio" name="who" value="me" checked> <?= $h($L['me']) ?></label>
  <label class="r"><input type="radio" name="who" value="other"> <?= $h($L['other']) ?></label>

  <select id="type" aria-label="Emergency type">
    <?php foreach (['heart','stroke','choking','bleeding','accident','general'] as $t): ?>
      <option value="<?= $t ?>"<?= $t === 'general' ? ' selected' : '' ?>><?= $h($L['type_' . $t]) ?></option>
    <?php endforeach; ?>
  </select>
  <input id="phone" type="tel" placeholder="<?= $h($L['phone']) ?>" aria-label="<?= $h($L['phone']) ?>" autocomplete="tel">

  <div id="otherBox" class="hidden">
    <input id="pname" type="text" maxlength="120" placeholder="<?= $h($L['pname']) ?>" aria-label="<?= $h($L['pname']) ?>">
    <input id="pphone" type="tel" placeholder="<?= $h($L['pphone']) ?>" aria-label="<?= $h($L['pphone']) ?>">
  </div>

  <p class="q"><?= $h($L['where']) ?></p>
  <label class="r"><input type="radio" name="loc" value="gps" checked> <?= $h($L['loc_gps']) ?></label>
  <label class="r"><input type="radio" name="loc" value="addr"> <?= $h($L['loc_addr']) ?></label>
  <div id="addrBox" class="hidden">
    <label for="address" class="muted"><?= $h($L['addr_label']) ?></label>
    <textarea id="address" rows="3" maxlength="500"></textarea>
  </div>

  <p class="muted"><?= $h($L['privacy']) ?></p>
  <p class="muted"><?= $h($L['fare_note']) ?></p>
  <button id="sos"><?= $h($L['book']) ?></button>
</div>

<div id="status" role="status" aria-live="polite"></div>

<div id="track" class="card hidden">
  <div class="big" id="drv"></div>
  <div class="muted" id="plate"></div>
  <a id="callDrv" class="call green" href="#"></a>
  <div class="big" id="eta"></div>
  <div class="muted" id="seen"></div>
  <div id="map"></div>
  <a id="mapLink" class="hidden" href="#" target="_blank" rel="noopener"><?= $h($L['open_maps']) ?></a>
</div>
<div id="prov" class="card hidden">
  <div class="big" id="provName"></div>
  <p class="muted"><?= $h($L['no_live']) ?></p>
  <a id="callProv" class="call green" href="#"></a>
</div>
<p class="big hidden" id="dest"></p>

<div id="hosp" class="card hidden" style="border-color:#51615e">
  <div class="q" style="margin-top:0"><?= $h($L['hosp_title']) ?></div>
  <select id="hospSel" aria-label="<?= $h($L['hosp_title']) ?>"></select>
  <div id="fareLine" class="big" style="font-size:1.05rem"></div>
  <p class="muted"><?= $h($L['hosp_note']) ?></p>
</div>

<button id="share" class="ghost hidden" type="button"><?= $h($L['share_btn']) ?></button>

<h3 id="faTitle" class="hidden"></h3>
<ol id="steps"></ol>
<button id="cancel" class="ghost hidden" type="button"><?= $h($L['cancel_btn']) ?></button>

<script>const L = <?= json_encode($L, JSON_UNESCAPED_UNICODE) ?>; const FA = <?= json_encode($FA, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/track.js"></script>
<script>
let token = null, timer = null, shareUrl = '';
const val = n => document.querySelector('input[name=' + n + ']:checked').value;

function syncForm() {
  show('otherBox', val('who') === 'other');
  show('addrBox', val('loc') === 'addr');
}
document.querySelectorAll('input[name=who],input[name=loc]').forEach(r => r.addEventListener('change', syncForm));

function showSteps(type) {
  const x = FA[type] || FA.general;
  $('faTitle').textContent = f(L.fa_what, {title: x.title}); show('faTitle', true);
  $('steps').innerHTML = x.steps.map(s => '<li>' + s.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</li>').join('');
}

async function loadHospitals() {
  try {
    const d = await (await fetch('api/hospitals.php?token=' + token, {cache: 'no-store'})).json();
    if (!d.hospitals || !d.hospitals.length) return;
    const sel = $('hospSel'); sel.textContent = '';
    const none = document.createElement('option'); none.value = ''; none.textContent = L.hosp_later; sel.appendChild(none);
    d.hospitals.forEach(x => {
      const o = document.createElement('option'); o.value = x.id;
      o.textContent = x.km !== null ? f(L.hosp_opt, {name: x.name, km: x.km, lo: x.fare_lo, hi: x.fare_hi}) : f(L.hosp_opt_nokm, {name: x.name});
      o.dataset.lo = x.fare_lo === null ? '' : x.fare_lo; o.dataset.hi = x.fare_hi === null ? '' : x.fare_hi; sel.appendChild(o);
    });
    sel.onchange = async () => {
      const o = sel.selectedOptions[0];
      $('fareLine').textContent = o.value && o.dataset.lo ? f(L.fare_line, {lo: o.dataset.lo, hi: o.dataset.hi}) : '';
      await fetch('api/sos_destination.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({token, hospital_id: o.value || null})});
    };
    show('hosp', true);
  } catch (e) {}
}

async function poll() {
  try {
    const d = await (await fetch('api/sos_status.php?token=' + token, {cache: 'no-store'})).json();
    const done = applyStatus(d);
    show('cancel', ['open', 'needs_staff', 'call_fallback', 'acknowledged'].includes(d.status));
    if (done) { clearInterval(timer); show('hosp', false); }
  } catch (e) { setStatus(L.conn_error); }
}

async function send(body) {
  try {
    const r = await fetch('api/sos_create.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
    const d = await r.json();
    if (!r.ok) { setStatus((d.error || 'Failed') + ' / 102'); return; }
    token = d.token; shareUrl = d.share_url; show('form', false); show('share', true);
    poll(); timer = setInterval(poll, 3000); loadHospitals();
  } catch (e) { setStatus(L.srv_error); }
}

$('sos').onclick = () => {
  const phone = $('phone').value.trim(), type = $('type').value, mode = val('loc');
  if (phone.length < 7) { alert(L.need_phone); return; }
  if (mode === 'addr' && $('address').value.trim().length < 5) { alert(L.need_addr); return; }
  showSteps(type);                                     // guidance appears before any network call
  const body = {phone, type, for_other: val('who') === 'other', address: $('address').value.trim(),
                patient_name: $('pname').value.trim(), patient_phone: $('pphone').value.trim()};
  if (mode === 'addr') { setStatus('...'); send(body); return; }
  setStatus(L.getting_loc);
  navigator.geolocation.getCurrentPosition(
    pos => send({...body, lat: pos.coords.latitude, lng: pos.coords.longitude}),
    () => { document.querySelector('input[name=loc][value=addr]').checked = true; syncForm(); setStatus(L.loc_blocked); $('address').focus(); },
    {enableHighAccuracy: true, timeout: 8000});
};

$('cancel').onclick = async () => {
  if (!token || !confirm(L.cancel_confirm)) return;
  await fetch('api/sos_cancel.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({token})});
  poll();
};
$('share').onclick = () => {
  const text = f(L.share_text, {url: shareUrl});
  if (navigator.share) navigator.share({text}).catch(() => {}); else location.href = 'sms:?body=' + encodeURIComponent(text);
};
</script></body></html>
