<?php
date_default_timezone_set('Asia/Manila');
if (is_file(__DIR__ . '/../config.local.php')) require_once __DIR__ . '/../config.local.php';
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'event_registration');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mailer.php';

session_start();

const TYPES = ['MTRCB Deputy', 'MTRCB Stakeholder'];

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $boot = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
    $boot->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
    $pdo->exec(file_get_contents(__DIR__ . '/../sql/schema.sql'));
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid or expired form. Go back, refresh and try again.');
    }
}

function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void
{
    if (!is_admin()) { header('Location: login.php'); exit; }
}

function flash(?string $msg = null, string $kind = 'ok'): ?array
{
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $kind]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** PNG bytes for a QR code encoding $text. */
function qr_png(string $text): string
{
    $o = new \chillerlan\QRCode\QROptions([
        'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
        'outputBase64' => false,
        'scale'        => 10,
        'quietzoneSize' => 2,
        'eccLevel'     => \chillerlan\QRCode\Common\EccLevel::M,
    ]);
    return (new \chillerlan\QRCode\QRCode($o))->render($text);
}

function page_head(string $title, bool $admin = false): void
{
    $f = flash();
    ?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · MTRCB Events</title>
<link rel="icon" href="<?= $admin ? '../' : '' ?>assets/logo-96.png">
<link rel="stylesheet" href="<?= $admin ? '../' : '' ?>assets/style.css">
</head><body>
<header class="top"><div class="wrap bar">
  <a class="brand" href="<?= $admin ? 'index.php' : 'index.php' ?>">
    <img src="<?= $admin ? '../' : '' ?>assets/logo-96.png" alt="MTRCB logo" width="44" height="44">
    <span><b>MTRCB Events</b><small>Event Registration</small></span></a>
  <?php if ($admin && is_admin()): ?>
  <nav><a href="index.php">Events</a><a href="scan.php">Scan</a><a href="logout.php">Log out</a></nav>
  <?php endif; ?>
</div></header>
<main class="wrap">
<?php if ($f): ?><div class="alert <?= e($f[1]) ?>"><?= e($f[0]) ?></div><?php endif;
}

function page_foot(): void
{
    echo '</main><footer class="foot">Movie and Television Review and Classification Board</footer></body></html>';
}
