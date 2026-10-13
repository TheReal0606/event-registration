<?php
require __DIR__ . '/includes/bootstrap.php';
$events = db()->query('SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) AS taken
                       FROM events e WHERE e.is_open=1 ORDER BY e.created_at DESC')->fetchAll();
page_head('Events');
?>
<h1>Event Registration</h1>
<p class="lead">Choose an event to register. You will receive a QR code by email.</p>
<?php if (!$events): ?>
  <div class="card">No events are open for registration right now.</div>
<?php endif; foreach ($events as $ev): $left = $ev['capacity'] - $ev['taken']; ?>
  <div class="card">
    <h2><?= e($ev['name']) ?></h2>
    <p class="lead"><?= $left > 0 ? e($left) . ' of ' . e($ev['capacity']) . ' slots left' : 'Fully booked' ?></p>
    <?php if ($left > 0): ?><a class="btn" href="register.php?e=<?= (int)$ev['id'] ?>">Register</a><?php endif; ?>
  </div>
<?php endforeach; ?>
<p><a href="admin/login.php">Admin</a></p>
<?php page_foot();
