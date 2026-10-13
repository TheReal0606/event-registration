<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $cap  = (int)($_POST['capacity'] ?? 0);
    if ($act === 'create' || $act === 'update') {
        if ($name === '' || mb_strlen($name) > 200) flash('Enter an event name.', 'err');
        elseif ($cap < 1 || $cap > 100000) flash('Participant number must be 1 or more.', 'err');
        elseif ($act === 'create') {
            $pdo->prepare('INSERT INTO events (name,capacity) VALUES (?,?)')->execute([$name, $cap]);
            flash('Event created.');
        } else {
            $n = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE event_id=?');
            $n->execute([$id]);
            $have = (int)$n->fetchColumn();
            if ($cap < $have) flash("Participant number cannot be below current registrations ($have).", 'err');
            else { $pdo->prepare('UPDATE events SET name=?, capacity=? WHERE id=?')->execute([$name, $cap, $id]); flash('Event updated.'); }
        }
    } elseif ($act === 'toggle') {
        $pdo->prepare('UPDATE events SET is_open=1-is_open WHERE id=?')->execute([$id]);
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM events WHERE id=?')->execute([$id]);
        flash('Event and its registrations deleted.');
    }
    header('Location: index.php'); exit;
}

$events = $pdo->query('SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) AS taken,
    (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.arrived_at IS NOT NULL) AS arrived
    FROM events e ORDER BY e.created_at DESC')->fetchAll();
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME']));
page_head('Events', true);
?>
<h1>Events</h1>
<div class="card">
  <h2>New event</h2>
  <form method="post" class="row" style="grid-template-columns:2fr 1fr auto;align-items:end">
    <?= csrf_field() ?><input type="hidden" name="act" value="create">
    <div><label for="n">Event name</label><input id="n" name="name" required maxlength="200"></div>
    <div><label for="c">Number of participants</label><input id="c" name="capacity" type="number" min="1" required></div>
    <button class="btn" type="submit">Create</button>
  </form>
</div>

<?php foreach ($events as $ev): $link = $base . '/register.php?e=' . $ev['id']; ?>
<div class="card">
  <form method="post" class="row" style="grid-template-columns:2fr 1fr auto;align-items:end">
    <?= csrf_field() ?><input type="hidden" name="act" value="update"><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
    <div><label>Event name</label><input name="name" value="<?= e($ev['name']) ?>" required maxlength="200"></div>
    <div><label>Participants</label><input name="capacity" type="number" min="1" value="<?= (int)$ev['capacity'] ?>" required></div>
    <button class="btn alt" type="submit">Save</button>
  </form>
  <div class="stats" style="margin-top:14px">
    <div class="stat"><b><?= (int)$ev['taken'] ?> / <?= (int)$ev['capacity'] ?></b><span>Registered</span></div>
    <div class="stat"><b><?= (int)$ev['arrived'] ?></b><span>Arrived</span></div>
    <div class="stat"><b><?= $ev['is_open'] ? '<span class="pill">Open</span>' : '<span class="pill no">Closed</span>' ?></b><span>Registration</span></div>
  </div>
  <label>Registration link</label>
  <input readonly value="<?= e($link) ?>" onclick="this.select()">
  <div class="tools" style="margin-top:14px">
    <a class="btn sm" href="participants.php?e=<?= (int)$ev['id'] ?>">Participants</a>
    <a class="btn sm alt" href="scan.php?e=<?= (int)$ev['id'] ?>">Scan QR</a>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
      <button class="btn sm alt" type="submit"><?= $ev['is_open'] ? 'Close registration' : 'Reopen registration' ?></button></form>
    <form method="post" class="inline" onsubmit="return confirm('Delete this event and ALL its registrations?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
      <button class="btn sm danger" type="submit">Delete</button></form>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$events): ?><div class="card">No events yet. Create one above.</div><?php endif; ?>
<?php page_foot();
