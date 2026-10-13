<?php
require __DIR__ . '/includes/bootstrap.php';
$st = db()->prepare('SELECT r.*, e.name AS event_name FROM registrations r JOIN events e ON e.id=r.event_id WHERE r.token=?');
$st->execute([(string)($_GET['t'] ?? '')]);
$r = $st->fetch();
if (!$r) { http_response_code(404); page_head('Not found'); echo '<div class="card">Registration not found.</div>'; page_foot(); exit; }
page_head('Registered');
?>
<div class="card narrow center">
  <h1>You are registered</h1>
  <p class="lead"><?= e($r['first_name'] . ' ' . $r['surname']) ?> · <?= e($r['participant_type']) ?><br><b><?= e($r['event_name']) ?></b></p>
  <?php if ($r['email_sent']): ?>
    <div class="alert ok">Your QR code was sent to <?= e($r['email']) ?>.</div>
  <?php else: ?>
    <div class="alert err">We could not send the email. Save the QR code below, or ask the organizer to resend it.</div>
  <?php endif; ?>
  <img class="qr" src="qr.php?t=<?= e($r['token']) ?>" alt="Your event QR code">
  <p class="lead">Present this QR code at the event.</p>
  <a class="btn alt" href="qr.php?t=<?= e($r['token']) ?>&download=1">Download QR</a>
</div>
<?php page_foot();
