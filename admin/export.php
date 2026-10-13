<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
$st = db()->prepare('SELECT * FROM events WHERE id=?');
$st->execute([(int)($_GET['e'] ?? 0)]);
$ev = $st->fetch();
if (!$ev) { header('Location: index.php'); exit; }
$st = db()->prepare('SELECT * FROM registrations WHERE event_id=? ORDER BY surname, first_name');
$st->execute([$ev['id']]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="participants-' . $ev['id'] . '.csv"');
$o = fopen('php://output', 'w');
fwrite($o, "\xEF\xBB\xBF");
fputcsv($o, ['Surname', 'First name', 'Contact', 'Email', 'Company', 'Type', 'Registered', 'Arrived']);
$safe = fn($s) => preg_match('/^[=+\-@\t\r]/', (string)$s) ? "'" . $s : $s; // block CSV formula injection
foreach ($st as $r) {
    fputcsv($o, [$safe($r['surname']), $safe($r['first_name']), $safe($r['contact']), $safe($r['email']), $safe($r['company']),
        $r['participant_type'], $r['created_at'], $r['arrived_at'] ?? '']);
}
