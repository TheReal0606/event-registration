<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pdo = db();
$eid = (int)($_GET['e'] ?? $_POST['e'] ?? 0);
$st = $pdo->prepare('SELECT * FROM events WHERE id=?');
$st->execute([$eid]);
$ev = $st->fetch();
if (!$ev) { header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rid = (int)($_POST['rid'] ?? 0);
    $q = $pdo->prepare('SELECT * FROM registrations WHERE id=? AND event_id=?');
    $q->execute([$rid, $eid]);
    if ($reg = $q->fetch()) {
        switch ($_POST['act'] ?? '') {
            case 'arrive':
                $pdo->prepare('UPDATE registrations SET arrived_at=COALESCE(arrived_at,NOW()) WHERE id=?')->execute([$rid]);
                flash($reg['first_name'] . ' ' . $reg['surname'] . ' marked arrived.');
                break;
            case 'unarrive':
                $pdo->prepare('UPDATE registrations SET arrived_at=NULL WHERE id=?')->execute([$rid]);
                flash('Arrival cleared.');
                break;
            case 'resend':
                $res = send_qr_email($reg, $ev['name']);
                if ($res['success']) { $pdo->prepare('UPDATE registrations SET email_sent=1 WHERE id=?')->execute([$rid]); flash('QR email sent to ' . $reg['email']); }
                else flash('Email failed: ' . $res['error'], 'err');
                break;
            case 'delete':
                $pdo->prepare('DELETE FROM registrations WHERE id=?')->execute([$rid]);
                flash('Registration removed.');
                break;
        }
    }
    header('Location: participants.php?e=' . $eid . (isset($_POST['q']) && $_POST['q'] !== '' ? '&q=' . urlencode($_POST['q']) : ''));
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$filter = $_GET['f'] ?? '';
$sql = 'SELECT * FROM registrations WHERE event_id=?';
$args = [$eid];
if ($q !== '') {
    $sql .= ' AND (surname LIKE ? OR first_name LIKE ? OR company LIKE ? OR email LIKE ? OR contact LIKE ?)';
    array_push($args, ...array_fill(0, 5, "%$q%"));
}
if ($filter === 'arrived') $sql .= ' AND arrived_at IS NOT NULL';
if ($filter === 'pending') $sql .= ' AND arrived_at IS NULL';
$sql .= ' ORDER BY surname, first_name';
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();
$tot = $pdo->prepare('SELECT COUNT(*) t, COUNT(arrived_at) a FROM registrations WHERE event_id=?');
$tot->execute([$eid]);
$tot = $tot->fetch();

page_head('Participants', true);
?>
<p><a href="index.php">&larr; Events</a></p>
<h1><?= e($ev['name']) ?></h1>
<div class="stats">
  <div class="stat"><b><?= (int)$tot['t'] ?> / <?= (int)$ev['capacity'] ?></b><span>Registered</span></div>
  <div class="stat"><b><?= (int)$tot['a'] ?></b><span>Arrived</span></div>
  <div class="stat"><b><?= (int)$tot['t'] - (int)$tot['a'] ?></b><span>Not yet arrived</span></div>
</div>
<div class="card">
  <form class="tools" method="get">
    <input type="hidden" name="e" value="<?= $eid ?>">
    <input name="q" value="<?= e($q) ?>" placeholder="Search name, company, email, contact" aria-label="Search">
    <select name="f" aria-label="Filter">
      <option value="">All</option>
      <option value="arrived" <?= $filter === 'arrived' ? 'selected' : '' ?>>Arrived</option>
      <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Not yet arrived</option>
    </select>
    <button class="btn sm" type="submit">Filter</button>
    <a class="btn sm alt" href="scan.php?e=<?= $eid ?>">Scan QR</a>
    <a class="btn sm alt" href="export.php?e=<?= $eid ?>">Export CSV</a>
  </form>
  <div class="scroll"><table>
    <tr><th>Name</th><th>Type</th><th>Company</th><th>Contact</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['surname']) ?></b>, <?= e($r['first_name']) ?><br><small><?= e($r['email']) ?></small></td>
      <td><?= e($r['participant_type']) ?></td>
      <td><?= e($r['company']) ?></td>
      <td><?= e($r['contact']) ?></td>
      <td><?= $r['arrived_at']
            ? '<span class="pill">Arrived</span><br><small>' . e(date('M j, g:i A', strtotime($r['arrived_at']))) . '</small>'
            : '<span class="pill wait">Not yet</span>' ?>
          <?php if (!$r['email_sent']): ?><br><small class="err-t">email not sent</small><?php endif; ?></td>
      <td>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="e" value="<?= $eid ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
          <?php if ($r['arrived_at']): ?>
            <button class="btn sm alt" name="act" value="unarrive">Undo</button>
          <?php else: ?>
            <button class="btn sm" name="act" value="arrive">Mark arrived</button>
          <?php endif; ?>
          <button class="btn sm alt" name="act" value="resend">Resend QR</button>
          <button class="btn sm danger" name="act" value="delete" onclick="return confirm('Remove this registration?')">Remove</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6">No participants found.</td></tr><?php endif; ?>
  </table></div>
</div>
<?php page_foot();
