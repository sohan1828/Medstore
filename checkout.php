<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/catalog.php';
secure_headers(); session_boot();
$products = catalog();
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Checkout</title><link rel="stylesheet" href="assets/app.css">
</head><body>
<div class="strip"><strong>Medical emergency?</strong><span><a href="tel:102">Call 102</a> &nbsp; <a href="sos.php">Request an ambulance</a></span></div>
<main class="wrap">
<p><a href="index.php#shop">Back to shop</a></p>
<h1>Checkout</h1>

<form id="f" method="post" action="order_place.php" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="cart" id="cartfield">
  <input type="hidden" name="lat" id="lat"><input type="hidden" name="lng" id="lng">

  <fieldset><legend>Your basket</legend>
    <div id="lines"></div>
    <p id="emptymsg" class="hidden">Your basket is empty. <a href="index.php#shop">Choose medicines</a>.</p>
  </fieldset>

  <fieldset><legend>How fast do you need it?</legend>
    <label class="opt"><input type="radio" name="delivery" value="normal" checked>
      <span><b>Normal delivery: Rs <?= DELIVERY_FEE['normal'] ?></b>Usually <?= e(DELIVERY_TARGET['normal']) ?>.</span></label>
    <label class="opt emg"><input type="radio" name="delivery" value="emergency">
      <span><b>Emergency delivery: Rs <?= DELIVERY_FEE['emergency'] ?></b>For urgent medicine needs, <?= e(DELIVERY_TARGET['emergency']) ?>. Our staff are alerted straight away and your order is handled first.</span></label>
    <p class="small muted">If someone's life is in danger, do not wait for delivery. <a href="sos.php">Press SOS</a> or call 102.</p>
  </fieldset>

  <fieldset><legend>Where should we deliver?</legend>
    <label for="name">Your name</label><input id="name" name="name" type="text" maxlength="120" autocomplete="name" required>
    <label for="phone">Phone number</label><input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" required>
    <label for="address">Full address and a landmark</label><textarea id="address" name="address" maxlength="500" required></textarea>
    <p><button type="button" class="btn ghost" id="loc">Share my location (helps the rider find you)</button> <span id="locmsg" class="small muted"></span></p>
  </fieldset>

  <fieldset id="rxbox" class="hidden"><legend>Prescription needed</legend>
    <p>Your basket has <span class="rx">Prescription</span> items. Upload a clear photo of the doctor's prescription. A pharmacist will check it before we send anything.</p>
    <label for="rxfile">Prescription photo or PDF (max 5 MB)</label>
    <input id="rxfile" name="rx" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
  </fieldset>

  <div class="box">
    <div class="tot"><span>Medicines</span><span id="sub">Rs 0</span></div>
    <div class="tot"><span>Delivery</span><span id="fee">Rs 0</span></div>
    <div class="tot big"><span>Total, pay on delivery</span><span id="total">Rs 0</span></div>
    <p class="small muted">Prices are confirmed again by our system when you place the order.</p>
  </div>
  <div id="errbox" class="err hidden" role="alert"></div>
  <button class="btn" id="place" type="submit">Place order</button>
</form>
</main>

<script>
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const FEES = <?= json_encode(DELIVERY_FEE) ?>;
const byId = Object.fromEntries(PRODUCTS.map(p => [p.id, p]));
const $ = s => document.querySelector(s);
function getCart(){ try { return JSON.parse(localStorage.getItem('cart')||'{}'); } catch(e){ return {}; } }
function saveCart(c){ try { localStorage.setItem('cart', JSON.stringify(c)); } catch(e){} }
const rs = n => 'Rs ' + n.toLocaleString('en-IN');

function render(){
  const cart = getCart(), box = $('#lines'); box.textContent = '';
  let sub = 0, needRx = false, n = 0;
  for (const [id, q] of Object.entries(cart)) {
    const p = byId[id]; if (!p || p.controlled) { delete cart[id]; continue; }
    n++; sub += p.price * q; if (p.rx) needRx = true;
    const row = document.createElement('div'); row.className = 'line';
    const l = document.createElement('div'); l.textContent = p.name + ' (' + p.note + ')';
    if (p.rx) { const t = document.createElement('span'); t.className = 'rx'; t.textContent = 'Prescription'; l.append(' ', t); }
    const qty = document.createElement('div'); qty.className = 'qty';
    const m = document.createElement('button'); m.type='button'; m.textContent='-'; m.setAttribute('aria-label','Fewer '+p.name);
    const c = document.createElement('span'); c.textContent = ' ' + q + ' ';
    const a = document.createElement('button'); a.type='button'; a.textContent='+'; a.setAttribute('aria-label','More '+p.name);
    m.onclick = () => { cart[id] = q - 1; if (cart[id] < 1) delete cart[id]; saveCart(cart); render(); };
    a.onclick = () => { cart[id] = Math.min(20, q + 1); saveCart(cart); render(); };
    const pr = document.createElement('div'); pr.textContent = rs(p.price * q);
    qty.append(m, c, a); row.append(l, qty, pr); box.appendChild(row);
  }
  saveCart(cart);
  $('#emptymsg').classList.toggle('hidden', n > 0);
  $('#place').disabled = n === 0;
  $('#rxbox').classList.toggle('hidden', !needRx);
  $('#rxfile').required = needRx;
  const fee = FEES[document.querySelector('input[name=delivery]:checked').value];
  $('#sub').textContent = rs(sub); $('#fee').textContent = rs(n ? fee : 0); $('#total').textContent = rs(n ? sub + fee : 0);
  $('#cartfield').value = JSON.stringify(cart);
}
document.querySelectorAll('input[name=delivery]').forEach(r => r.addEventListener('change', render));

$('#loc').onclick = () => {
  $('#locmsg').textContent = 'Getting location...';
  navigator.geolocation.getCurrentPosition(
    p => { $('#lat').value = p.coords.latitude; $('#lng').value = p.coords.longitude; $('#locmsg').textContent = 'Location added.'; },
    () => { $('#locmsg').textContent = 'Could not get location. Please write a clear address instead.'; },
    {timeout: 8000});
};

$('#f').addEventListener('submit', e => {
  const err = [];
  if (!$('#name').value.trim()) err.push('Enter your name.');
  if (!/^\+?[0-9]{7,15}$/.test($('#phone').value.replace(/[\s-]/g,''))) err.push('Enter a valid phone number.');
  if ($('#address').value.trim().length < 5) err.push('Enter your full address.');
  if ($('#rxfile').required && !$('#rxfile').files.length) err.push('Upload the prescription photo.');
  if (err.length) { e.preventDefault(); const b = $('#errbox'); b.textContent = err.join(' '); b.classList.remove('hidden'); b.scrollIntoView(); }
  else $('#place').disabled = true;
});
render();
</script>
</body></html>
