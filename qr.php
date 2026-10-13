<?php
require __DIR__ . '/includes/bootstrap.php';
$t = (string)($_GET['t'] ?? '');
$st = db()->prepare('SELECT 1 FROM registrations WHERE token=?');
$st->execute([$t]);
if (!$st->fetchColumn()) { http_response_code(404); exit; }
header('Content-Type: image/png');
header('Cache-Control: private, max-age=86400');
if (isset($_GET['download'])) header('Content-Disposition: attachment; filename="event-qr.png"');
echo qr_png($t);
