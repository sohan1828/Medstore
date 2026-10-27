<?php
require_once __DIR__ . '/includes/i18n.php';
$L = strings();
$s = preg_match('/^[a-f0-9]{32}$/', $_GET['s'] ?? '') ? $_GET['s'] : '';
$h = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="<?= lang() ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($L['follow_title']) ?></title><link rel="stylesheet" href="assets/sos.css">
</head><body>
<p class="lang"><a href="?s=<?= $h($s) ?>&lang=<?= $L['other_code'] ?>"><?= $h($L['other_lang']) ?></a></p>
<h1><?= $h($L['follow_title']) ?></h1>
<p class="muted"><?= $h($L['follow_note']) ?></p>
<a class="call" href="tel:102"><?= $h($L['call102']) ?></a>
<div id="status" role="status" aria-live="polite"></div>
<div id="track" class="card hidden">
  <div class="big" id="drv"></div><div class="muted" id="plate"></div>
  <a id="callDrv" class="call green" href="#"></a>
  <div class="big" id="eta"></div><div class="muted" id="seen"></div>
  <div id="map"></div>
  <a id="mapLink" class="hidden" href="#" target="_blank" rel="noopener"><?= $h($L['open_maps']) ?></a>
</div>
<div id="prov" class="card hidden"><div class="big" id="provName"></div>
  <p class="muted"><?= $h($L['no_live']) ?></p><a id="callProv" class="call green" href="#"></a></div>
<p class="big hidden" id="dest"></p>
<script>const L = <?= json_encode($L, JSON_UNESCAPED_UNICODE) ?>; const SHARE = <?= json_encode($s) ?>;</script>
<script src="assets/track.js"></script>
<script>
let timer = null;
async function poll() {
  try {
    const r = await fetch('api/sos_status.php?share=' + SHARE, {cache: 'no-store'});
    if (r.status === 404) { setStatus('This link is no longer active.'); clearInterval(timer); return; }
    if (applyStatus(await r.json())) clearInterval(timer);
  } catch (e) { setStatus(L.conn_error); }
}
poll(); timer = setInterval(poll, 4000);
</script></body></html>
