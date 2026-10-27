<?php
// Homepage. Uses sample products so it works with no database; swap $products for a
// SELECT on the `products` table when the SQL side is ready.
$store = 'Sanjivani Pharmacy';           // placeholder name, change freely
$phone_amb = '102';                      // verify current Nepal ambulance number

require_once __DIR__ . '/includes/catalog.php';
$products = catalog();
$cats = array_values(array_unique(array_column($products, 'cat')));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($store) ?>: medicines and emergency help, 24 hours</title>
<meta name="description" content="Order medicines online, upload prescriptions, and request an ambulance any hour of the day.">
<style>
:root{
  --ink:#14211f; --muted:#51615e; --paper:#f6faf9; --mint:#e1f0ec; --pine:#0f4c45; --pine-d:#0a3732;
  --sos:#c8102e; --sos-d:#9d0c24; --amber:#8a5a00; --amber-bg:#fdf1d6; --line:#cfdcd8;
  --font:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Devanagari",sans-serif;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:var(--font);color:var(--ink);background:var(--paper);line-height:1.55;font-size:1.0625rem;padding-bottom:84px}
a{color:var(--pine)}
:focus-visible{outline:3px solid #1b6ef3;outline-offset:3px}
.wrap{max-width:1120px;margin:0 auto;padding:0 18px}

/* Emergency strip: on every screen, no JavaScript needed */
.strip{background:var(--sos);color:#fff;position:sticky;top:0;z-index:20}
.strip .wrap{display:flex;gap:14px;align-items:center;justify-content:space-between;padding-top:9px;padding-bottom:9px;flex-wrap:wrap}
.strip strong{font-size:1rem}
.strip a{color:#fff;font-weight:700}
.strip .acts{display:flex;gap:16px}

header.site .wrap{display:flex;align-items:center;justify-content:space-between;padding-top:16px;padding-bottom:16px;gap:12px}
.brand{font-size:1.35rem;font-weight:800;color:var(--pine);text-decoration:none;letter-spacing:-.01em}
.open{font-size:.9rem;color:var(--muted)}
.cartbtn{border:2px solid var(--pine);background:#fff;color:var(--pine);font:inherit;font-weight:700;padding:8px 14px;border-radius:8px;cursor:pointer}

/* Hero: SOS is the one loud thing on the page */
.hero{padding:20px 0 40px}
.hero .wrap{display:grid;grid-template-columns:1.05fr .95fr;gap:36px;align-items:center}
.hero h1{font-size:clamp(2rem,4.6vw,3.2rem);line-height:1.08;margin:0 0 14px;letter-spacing:-.02em;max-width:15ch}
.hero p.lead{font-size:1.15rem;color:var(--muted);max-width:46ch;margin:0 0 22px}
.search{display:flex;gap:8px;max-width:520px}
.search input{flex:1;font:inherit;padding:14px 16px;border:2px solid var(--line);border-radius:8px;background:#fff;min-width:0}
.search button{font:inherit;font-weight:700;background:var(--pine);color:#fff;border:0;border-radius:8px;padding:0 20px;cursor:pointer}
.search button:hover{background:var(--pine-d)}

.sospanel{background:#fff;border:3px solid var(--sos);border-radius:20px;padding:26px 22px;text-align:center}
.sospanel .np{margin:0 0 4px;font-weight:700;color:var(--sos)}
.sosbtn{display:flex;align-items:center;justify-content:center;flex-direction:column;width:min(240px,60vw);aspect-ratio:1;margin:8px auto 16px;border-radius:50%;
  background:var(--sos);color:#fff;text-decoration:none;font-size:3.4rem;font-weight:900;letter-spacing:.02em;box-shadow:0 0 0 0 rgba(200,16,46,.45);animation:ring 2.6s ease-out infinite}
.sosbtn small{font-size:.95rem;font-weight:600;margin-top:2px}
.sosbtn:hover{background:var(--sos-d)}
@keyframes ring{0%{box-shadow:0 0 0 0 rgba(200,16,46,.45)}70%,100%{box-shadow:0 0 0 26px rgba(200,16,46,0)}}
.sospanel p{margin:0 auto 14px;max-width:34ch;color:var(--muted)}
.call102{display:inline-block;font-weight:800;font-size:1.15rem;border:2px solid var(--ink);color:var(--ink);padding:10px 18px;border-radius:10px;text-decoration:none}
.call102:hover{background:var(--ink);color:#fff}
.fine{font-size:.85rem;color:var(--muted);margin-top:12px}

/* Shop */
section{padding:44px 0}
section.tint{background:var(--mint)}
h2{font-size:1.7rem;margin:0 0 6px;letter-spacing:-.01em}
.sub{color:var(--muted);margin:0 0 22px}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px}
.chip{font:inherit;border:2px solid var(--line);background:#fff;border-radius:999px;padding:7px 16px;cursor:pointer}
.chip[aria-pressed="true"]{background:var(--pine);border-color:var(--pine);color:#fff}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px}
.item{background:#fff;border:1px solid var(--line);border-radius:6px;padding:16px;display:flex;flex-direction:column;gap:6px}
.item h3{font-size:1.05rem;margin:0}
.item .n{color:var(--muted);font-size:.92rem}
.item .row{display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:10px}
.price{font-weight:800}
.rx{display:inline-block;background:var(--amber-bg);color:var(--amber);font-size:.82rem;font-weight:700;padding:2px 8px;border-radius:4px;width:max-content}
.add{font:inherit;font-weight:700;border:2px solid var(--pine);background:#fff;color:var(--pine);border-radius:8px;padding:6px 14px;cursor:pointer}
.add:hover{background:var(--pine);color:#fff}
.empty{padding:24px;background:#fff;border:1px dashed var(--line);border-radius:6px}

/* Prescription steps: a real sequence, so numbered */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;list-style:none;padding:0;margin:0;counter-reset:s}
.steps li{counter-increment:s;background:#fff;border-radius:6px;padding:18px 18px 18px 62px;position:relative}
.steps li::before{content:counter(s);position:absolute;left:16px;top:16px;width:32px;height:32px;border-radius:50%;background:var(--pine);color:#fff;display:grid;place-items:center;font-weight:800}
.steps b{display:block;margin-bottom:2px}
.btn{display:inline-block;margin-top:22px;background:var(--pine);color:#fff;font-weight:700;text-decoration:none;padding:13px 22px;border-radius:8px}
.btn:hover{background:var(--pine-d)}

.how{display:grid;grid-template-columns:1fr 1fr;gap:28px}
.how ul{padding-left:20px;margin:8px 0 0}

footer{background:var(--ink);color:#d7e3e0;padding:34px 0 40px;font-size:.95rem}
footer a{color:#fff}
footer .cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px}
footer h4{margin:0 0 6px;color:#fff}

/* Bottom bar: SOS stays within thumb reach while scrolling */
.dock{position:fixed;left:0;right:0;bottom:0;z-index:30;background:#fff;border-top:2px solid var(--sos);display:flex;gap:10px;padding:10px 14px}
.dock a{flex:1;text-align:center;text-decoration:none;font-weight:800;padding:13px 8px;border-radius:10px;font-size:1.05rem}
.dock .a{background:var(--sos);color:#fff}
.dock .b{border:2px solid var(--ink);color:var(--ink)}

#toast{position:fixed;left:50%;transform:translateX(-50%);bottom:96px;background:var(--ink);color:#fff;padding:12px 18px;border-radius:8px;max-width:90vw;opacity:0;pointer-events:none;transition:opacity .2s;z-index:40}
#toast.show{opacity:1}

@media (max-width:860px){
  .hero .wrap{grid-template-columns:1fr}
  .hero .sospanel{order:-1}
  .steps,.how{grid-template-columns:1fr}
}
@media (prefers-reduced-motion:reduce){.sosbtn{animation:none}html{scroll-behavior:auto}}
</style>
</head>
<body>

<div class="strip" role="region" aria-label="Emergency help">
  <div class="wrap">
    <strong>Medical emergency?</strong>
    <div class="acts">
      <a href="tel:<?= $phone_amb ?>">Call <?= $phone_amb ?></a>
      <a href="sos.php">Request an ambulance</a>
    </div>
  </div>
</div>

<header class="site">
  <div class="wrap">
    <a class="brand" href="/"><?= htmlspecialchars($store) ?></a>
    <span class="open">Open 24 hours, every day</span>
    <button class="cartbtn" id="cartbtn" aria-label="Items in basket">Basket (<span id="count">0</span>)</button>
  </div>
</header>

<main>
<div class="hero">
  <div class="wrap">
    <div>
      <h1>Medicines at your door. Help when it can't wait.</h1>
      <p class="lead">Order everyday medicines and health supplies, upload a prescription for the rest, or book an ambulance and watch it come to you. We answer at any hour.</p>
      <form class="search" role="search" onsubmit="return false">
        <input id="q" type="search" placeholder="Search medicines, e.g. paracetamol" aria-label="Search medicines">
        <button type="button" id="go">Search</button>
      </form>
    </div>

    <div class="sospanel">
      <p class="np" lang="ne">आपतकालीन सहायता</p>
      <a class="sosbtn" href="sos.php" aria-label="Emergency SOS: request an ambulance">SOS<small>Tap for an ambulance</small></a>
      <p>We alert the nearest ambulance and hospital with your location, and show you what to do while you wait.</p>
      <a class="call102" href="tel:<?= $phone_amb ?>">Call <?= $phone_amb ?> directly</a>
      <p class="fine">This supports emergency services and does not replace them. If someone is unconscious or not breathing, call <?= $phone_amb ?> first.</p>
    </div>
  </div>
</div>

<section id="shop" aria-labelledby="shoptitle">
  <div class="wrap">
    <h2 id="shoptitle">Shop medicines and supplies</h2>
    <p class="sub">Items marked <span class="rx">Prescription</span> need a doctor's prescription before we send them.</p>
    <div class="chips" id="chips" role="group" aria-label="Categories">
      <button class="chip" data-c="" aria-pressed="true">All</button>
      <?php foreach ($cats as $c): ?>
        <button class="chip" data-c="<?= htmlspecialchars($c) ?>" aria-pressed="false"><?= htmlspecialchars($c) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="grid" id="grid" aria-live="polite"></div>
  </div>
</section>

<section class="tint" id="rx" aria-labelledby="rxtitle">
  <div class="wrap">
    <h2 id="rxtitle">Have a prescription?</h2>
    <p class="sub">A pharmacist checks every prescription before your order goes out.</p>
    <ol class="steps">
      <li><b>Upload a photo</b>Take a clear photo of the prescription in good light.</li>
      <li><b>Pharmacist checks it</b>You'll hear back quickly, including if we need a better photo.</li>
      <li><b>We deliver</b>Once approved, your order is packed and sent.</li>
    </ol>
    <a class="btn" href="#shop">Choose your medicines first</a>
  </div>
</section>

<section aria-labelledby="howtitle">
  <div class="wrap how">
    <div>
      <h2 id="howtitle">What happens when you press SOS</h2>
      <ul>
        <li>Your location and phone number go to the nearest available ambulance driver.</li>
        <li>If they don't accept within 30 seconds, we try the next closest one.</li>
        <li>Once a driver accepts, you see their name, phone number and number plate, and follow the ambulance on a live map with an estimated arrival time.</li>
        <li>If no driver is online, we alert nearby ambulance providers and hospitals, and tell you to call <?= $phone_amb ?>.</li>
        <li>Meanwhile, your screen shows simple steps for what to do until help arrives.</li>
      </ul>
    </div>
    <div>
      <h2>Free to use, no account needed</h2>
      <p>Anyone can press SOS without signing in. We only use your location and number to send help, and nothing else.</p>
    </div>
  </div>
</section>
</main>

<footer>
  <div class="wrap cols">
    <div><h4><?= htmlspecialchars($store) ?></h4>Open 24 hours, every day.<br>Licensed pharmacist reviews all prescriptions.</div>
    <div><h4>Emergency</h4><a href="tel:<?= $phone_amb ?>">Ambulance: <?= $phone_amb ?></a><br><a href="sos.php">Request an ambulance online</a></div>
    <div><h4>Shop</h4><a href="#shop">All medicines</a><br><a href="#rx">Prescriptions</a></div>
  </div>
</footer>

<nav class="dock" aria-label="Emergency shortcuts">
  <a class="a" href="sos.php">SOS</a>
  <a class="b" href="tel:<?= $phone_amb ?>">Call <?= $phone_amb ?></a>
</nav>
<div id="toast" role="status"></div>

<script>
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
let cat = '', term = '';
const $ = s => document.querySelector(s);

function render() {
  const g = $('#grid'); g.textContent = '';
  const list = PRODUCTS.filter(p =>
    (!cat || p.cat === cat) && (!term || (p.name + ' ' + p.note).toLowerCase().includes(term)));
  if (!list.length) {
    const d = document.createElement('div'); d.className = 'empty';
    d.textContent = 'Nothing matches "' + term + '". Try a shorter name, or call us and we will help you find it.';
    g.appendChild(d); return;
  }
  list.forEach(p => {
    const el = document.createElement('div'); el.className = 'item';
    const h = document.createElement('h3'); h.textContent = p.name;
    const n = document.createElement('div'); n.className = 'n'; n.textContent = p.note;
    el.append(h, n);
    if (p.rx) { const r = document.createElement('span'); r.className = 'rx'; r.textContent = 'Prescription'; el.appendChild(r); }
    const row = document.createElement('div'); row.className = 'row';
    const pr = document.createElement('span'); pr.className = 'price'; pr.textContent = 'Rs ' + p.price.toLocaleString('en-IN');
    const b = document.createElement('button'); b.className = 'add'; b.textContent = 'Add';
    b.setAttribute('aria-label', 'Add ' + p.name + ' to basket');
    if (p.controlled) { b.disabled = true; b.textContent = 'In store only'; } else { b.onclick = () => add(p); }
    row.append(pr, b); el.appendChild(row); g.appendChild(el);
  });
}

function toast(t) { const e = $('#toast'); e.textContent = t; e.classList.add('show'); clearTimeout(toast.t); toast.t = setTimeout(() => e.classList.remove('show'), 3200); }
function getCart() { try { return JSON.parse(localStorage.getItem('cart') || '{}'); } catch (e) { return {}; } }
function saveCart(c) { try { localStorage.setItem('cart', JSON.stringify(c)); } catch (e) {} }
function updateCount() { $('#count').textContent = Object.values(getCart()).reduce((a, b) => a + b, 0); }
function add(p) {
  const c = getCart(); c[p.id] = Math.min(20, (c[p.id] || 0) + 1); saveCart(c); updateCount();
  toast(p.rx ? p.name + ' added. You will upload the prescription at checkout.' : p.name + ' added to your basket.');
}

$('#chips').addEventListener('click', e => {
  const b = e.target.closest('.chip'); if (!b) return;
  cat = b.dataset.c;
  document.querySelectorAll('.chip').forEach(c => c.setAttribute('aria-pressed', c === b));
  render();
});
function search() { term = $('#q').value.trim().toLowerCase(); render(); if (term) $('#shop').scrollIntoView(); }
$('#go').onclick = search;
$('#q').addEventListener('keydown', e => { if (e.key === 'Enter') search(); });
$('#cartbtn').onclick = () => { location.href = 'checkout.php'; };
updateCount();
render();
</script>
</body>
</html>
