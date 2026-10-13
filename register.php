<?php
require __DIR__ . '/includes/bootstrap.php';
$pdo = db();
$st = $pdo->prepare('SELECT * FROM events WHERE id=?');
$st->execute([(int)($_GET['e'] ?? 0)]);
$ev = $st->fetch();
if (!$ev) { http_response_code(404); page_head('Not found'); echo '<div class="card">Event not found.</div>'; page_foot(); exit; }

$errors = [];
$v = ['surname' => '', 'first_name' => '', 'contact' => '', 'email' => '', 'company' => '', 'type' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    if ($v['surname'] === '' || mb_strlen($v['surname']) > 100) $errors['surname'] = 'Enter your surname.';
    if ($v['first_name'] === '' || mb_strlen($v['first_name']) > 100) $errors['first_name'] = 'Enter your first name.';
    if (!preg_match('/^[0-9+\-() ]{7,20}$/', $v['contact'])) $errors['contact'] = 'Enter a valid contact number.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) $errors['email'] = 'Enter a valid email address. Your QR code is sent here.';
    if ($v['company'] === '' || mb_strlen($v['company']) > 200) $errors['company'] = 'Enter your company.';
    if (!in_array($v['type'], TYPES, true)) $errors['type'] = 'Choose a participant type.';

    if (!$errors) {
        $token = bin2hex(random_bytes(16));
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT capacity, is_open FROM events WHERE id=? FOR UPDATE');
            $lock->execute([$ev['id']]);
            $cur = $lock->fetch();
            $n = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE event_id=?');
            $n->execute([$ev['id']]);
            if (!$cur['is_open']) {
                $errors['_'] = 'Registration for this event is closed.';
            } elseif ((int)$n->fetchColumn() >= (int)$cur['capacity']) {
                $errors['_'] = 'Sorry, this event is fully booked.';
            } else {
                $pdo->prepare('INSERT INTO registrations (event_id,surname,first_name,contact,email,company,participant_type,token)
                               VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$ev['id'], $v['surname'], $v['first_name'], $v['contact'], strtolower($v['email']), $v['company'], $v['type'], $token]);
            }
            $pdo->commit();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['_'] = $ex->errorInfo[1] === 1062
                ? 'This email is already registered for this event. Check your inbox, or ask the organizer to resend your QR code.'
                : 'Could not save your registration. Please try again.';
        }
        if (!$errors) {
            $reg = ['first_name' => $v['first_name'], 'surname' => $v['surname'], 'email' => strtolower($v['email']),
                    'participant_type' => $v['type'], 'token' => $token];
            $res = send_qr_email($reg, $ev['name']);
            if ($res['success']) {
                $pdo->prepare('UPDATE registrations SET email_sent=1 WHERE token=?')->execute([$token]);
            } else {
                error_log('QR email failed: ' . $res['error']);
            }
            header('Location: done.php?t=' . $token . '&m=' . (int)$res['success']);
            exit;
        }
    }
}

$taken = (int)$pdo->query('SELECT COUNT(*) FROM registrations WHERE event_id=' . (int)$ev['id'])->fetchColumn();
$full = $taken >= $ev['capacity'];
page_head('Register');
$err = fn($k) => isset($errors[$k]) ? '<div class="err-t">' . e($errors[$k]) . '</div>' : '';
?>
<div class="card narrow">
  <h1><?= e($ev['name']) ?></h1>
  <p class="lead"><?= $ev['is_open'] && !$full ? e($ev['capacity'] - $taken) . ' slots left' : '' ?></p>
  <?php if (isset($errors['_'])): ?><div class="alert err"><?= e($errors['_']) ?></div><?php endif; ?>
  <?php if (!$ev['is_open']): ?>
    <div class="alert err">Registration for this event is closed.</div>
  <?php elseif ($full): ?>
    <div class="alert err">Sorry, this event is fully booked.</div>
  <?php else: ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="row">
      <div><label for="surname">Surname</label><input id="surname" name="surname" value="<?= e($v['surname']) ?>" required autocomplete="family-name"><?= $err('surname') ?></div>
      <div><label for="first_name">First name</label><input id="first_name" name="first_name" value="<?= e($v['first_name']) ?>" required autocomplete="given-name"><?= $err('first_name') ?></div>
    </div>
    <label for="contact">Contact number</label>
    <input id="contact" name="contact" type="tel" value="<?= e($v['contact']) ?>" required autocomplete="tel"><?= $err('contact') ?>
    <label for="email">Email (QR code is sent here)</label>
    <input id="email" name="email" type="email" value="<?= e($v['email']) ?>" required autocomplete="email"><?= $err('email') ?>
    <label for="company">Company</label>
    <input id="company" name="company" value="<?= e($v['company']) ?>" required autocomplete="organization"><?= $err('company') ?>
    <label for="type">Participant type</label>
    <select id="type" name="type" required>
      <option value="">Select…</option>
      <?php foreach (TYPES as $t): ?><option <?= $v['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
    </select><?= $err('type') ?>
    <button class="btn full" type="submit">Register</button>
  </form>
  <?php endif; ?>
</div>
<?php page_foot();
