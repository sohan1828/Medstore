// Shared by sos.php and follow.php. The page must define `const L` (translated strings) first.
function $(id) { return document.getElementById(id); }
function show(id, on) { $(id).classList.toggle('hidden', !on); }
function setStatus(t) { $('status').textContent = t; }
function f(s, v) { return String(s).replace(/\{(\w+)\}/g, (_, k) => (v[k] === undefined ? '' : v[k])); }

function drawMap(me, amb) {
  const W = 320, H = 220, pad = 36, k = Math.cos(((me.lat + amb.lat) / 2) * Math.PI / 180);
  const P = [[me.lng * k, me.lat], [amb.lng * k, amb.lat]], xs = P.map(p => p[0]), ys = P.map(p => p[1]);
  const span = Math.max(Math.max(...xs) - Math.min(...xs), Math.max(...ys) - Math.min(...ys), 0.002);
  const cx = (Math.max(...xs) + Math.min(...xs)) / 2, cy = (Math.max(...ys) + Math.min(...ys)) / 2, sc = (Math.min(W, H) - 2 * pad) / span;
  const X = p => (W / 2 + (p[0] - cx) * sc).toFixed(1), Y = p => (H / 2 - (p[1] - cy) * sc).toFixed(1);
  const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
  $('map').innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' + esc(L.map_label) + '">' +
    '<line x1="' + X(P[0]) + '" y1="' + Y(P[0]) + '" x2="' + X(P[1]) + '" y2="' + Y(P[1]) + '" stroke="#51615e" stroke-width="2" stroke-dasharray="6 5"/>' +
    '<circle cx="' + X(P[0]) + '" cy="' + Y(P[0]) + '" r="11" fill="#1b6ef3" stroke="#fff" stroke-width="3"/>' +
    '<text x="' + X(P[0]) + '" y="' + (Number(Y(P[0])) + 28) + '" text-anchor="middle" font-size="14" font-weight="700" fill="#14211f">' + esc(L.you) + '</text>' +
    '<circle cx="' + X(P[1]) + '" cy="' + Y(P[1]) + '" r="12" fill="#1d6b3a" stroke="#fff" stroke-width="3"/>' +
    '<text x="' + X(P[1]) + '" y="' + (Number(Y(P[1])) - 18) + '" text-anchor="middle" font-size="14" font-weight="700" fill="#14211f">' + esc(L.amb) + '</text></svg>';
}

function renderAccepted(d) {
  const a = d.ambulance, p = d.provider;
  if (d.destination) { $('dest').textContent = f(L.to_hospital, {name: d.destination.name}); show('dest', true); } else show('dest', false);
  if (a) {
    show('prov', false); show('track', true);
    $('drv').textContent = f(L.driver, {name: a.driver_name}); $('plate').textContent = f(L.plate, {plate: a.plate});
    $('callDrv').href = 'tel:' + a.driver_phone; $('callDrv').textContent = f(L.call_driver, {phone: a.driver_phone});
    if (d.status === 'arrived') { setStatus(L.arrived); $('eta').textContent = ''; $('seen').textContent = ''; }
    else {
      setStatus(L.on_way);
      $('eta').textContent = a.eta_min !== null ? f(L.eta, {m: a.eta_min, km: a.km.toFixed(1)}) : L.eta_wait;
      $('seen').textContent = a.seen_age_s === null ? '' : a.seen_age_s > 60 ? f(L.stale, {m: Math.round(a.seen_age_s / 60)}) : f(L.estimate, {s: a.seen_age_s});
    }
    if (a.lat !== null) {
      if (d.patient) drawMap(d.patient, a); else $('map').textContent = '';
      $('mapLink').href = 'https://maps.google.com/?q=' + a.lat + ',' + a.lng; show('mapLink', true);
    } else show('mapLink', false);
  } else if (p) {
    show('track', false); show('prov', true); setStatus(L.help_alerted);
    $('provName').textContent = p.name; $('callProv').href = 'tel:' + p.phone; $('callProv').textContent = f(L.call_prov, {name: p.name});
  }
}

/** Returns true when the request is finished and polling can stop. */
function applyStatus(d) {
  switch (d.status) {
    case 'open': setStatus(f(L.finding, {n: Math.max(1, d.attempts), s: d.seconds_left})); return false;
    case 'needs_staff': setStatus(L.needs_staff); return false;
    case 'acknowledged': case 'arrived': renderAccepted(d); return false;
    case 'call_fallback': setStatus(L.fallback); return false;     // keep polling: the control room may still assign someone
    case 'cancelled': setStatus(L.cancelled); show('track', false); show('prov', false); return true;
    case 'completed': setStatus(L.completed); return true;
  }
  return false;
}
