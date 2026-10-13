<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pdo = db();
$events = $pdo->query('SELECT id,name FROM events ORDER BY created_at DESC')->fetchAll();
$eid = (int)($_GET['e'] ?? ($events[0]['id'] ?? 0));
page_head('Scan', true);
?>
<h1>Scan participant QR</h1>
<div class="card narrow">
  <label for="ev">Event</label>
  <select id="ev" onchange="location='scan.php?e='+this.value">
    <?php foreach ($events as $ev): ?><option value="<?= (int)$ev['id'] ?>" <?= $ev['id'] == $eid ? 'selected' : '' ?>><?= e($ev['name']) ?></option><?php endforeach; ?>
  </select>
  <div id="reader"></div>
  <p id="camerr" class="err-t"></p>
  <div id="result" role="status" aria-live="polite"></div>
  <hr style="border:0;border-top:1px solid var(--line);margin:20px 0">
  <label for="manual">Handheld scanner or manual entry</label>
  <form id="mform" class="tools"><input id="manual" autocomplete="off" placeholder="Click here, then scan or paste code"><button class="btn sm" type="submit">Check in</button></form>
</div>
<script src="../assets/html5-qrcode.min.js"></script>
<script>
const CSRF = <?= json_encode(csrf()) ?>, EID = <?= $eid ?>;
const out = document.getElementById('result');
let busy = false, last = '', lastAt = 0;

async function checkin(token) {
  token = token.trim();
  if (!token || busy || (token === last && Date.now() - lastAt < 4000)) return;
  busy = true; last = token; lastAt = Date.now();
  try {
    const r = await fetch('checkin.php', {method: 'POST', body: new URLSearchParams({token, e: EID, csrf: CSRF})});
    const j = await r.json();
    const cls = j.status === 'ok' ? 'ok' : (j.status === 'already' ? 'warn' : 'bad');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    out.innerHTML = `<div class="big ${cls}">${j.status === 'ok' ? '✓ ' : ''}${esc(j.name || j.message)}
      <small>${j.name ? esc(j.type) + ' · ' + esc(j.company) + '<br>' : ''}${esc(j.message)}</small></div>`;
    if (navigator.vibrate) navigator.vibrate(j.status === 'ok' ? 80 : [80, 60, 80]);
  } catch (e) {
    out.innerHTML = '<div class="big bad">Network error<small>Try again</small></div>';
  }
  busy = false;
}

document.getElementById('mform').addEventListener('submit', ev => {
  ev.preventDefault();
  const i = document.getElementById('manual'); checkin(i.value); i.value = '';
});

if (window.Html5Qrcode && (window.isSecureContext)) {
  const sc = new Html5Qrcode('reader');
  sc.start({facingMode: 'environment'}, {fps: 10, qrbox: 240}, txt => checkin(txt), () => {})
    .catch(err => { document.getElementById('camerr').textContent = 'Camera unavailable: ' + err; });
} else {
  document.getElementById('camerr').textContent =
    'Camera scanning needs HTTPS or http://localhost. Use the entry box below with a handheld scanner, or open this page via localhost.';
}
</script>
<?php page_foot();
