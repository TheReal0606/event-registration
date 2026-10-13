<?php
require __DIR__ . '/../includes/bootstrap.php';
$pdo = db();
$first = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');
    if ($first) {
        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $u)) $err = 'Username: 3-60 letters, numbers, . _ -';
        elseif (strlen($p) < 8) $err = 'Password must be at least 8 characters.';
        elseif ($p !== (string)($_POST['password2'] ?? '')) $err = 'Passwords do not match.';
        else {
            $pdo->prepare('INSERT INTO admins (username,password_hash) VALUES (?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$pdo->lastInsertId();
            header('Location: index.php'); exit;
        }
    } else {
        $st = $pdo->prepare('SELECT * FROM admins WHERE username=?');
        $st->execute([$u]);
        $a = $st->fetch();
        usleep(300000);
        if ($a && password_verify($p, $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            header('Location: index.php'); exit;
        }
        $err = 'Wrong username or password.';
    }
}
page_head($first ? 'Create admin' : 'Admin login', true);
?>
<div class="card narrow">
  <h1><?= $first ? 'Create admin account' : 'Admin login' ?></h1>
  <?php if ($first): ?><p class="lead">First run. Choose the administrator login.</p><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="username">Username</label><input id="username" name="username" required autocomplete="username">
    <label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="<?= $first ? 'new-password' : 'current-password' ?>">
    <?php if ($first): ?><label for="password2">Confirm password</label><input id="password2" name="password2" type="password" required autocomplete="new-password"><?php endif; ?>
    <button class="btn full" type="submit"><?= $first ? 'Create and sign in' : 'Sign in' ?></button>
  </form>
</div>
<?php page_foot();
