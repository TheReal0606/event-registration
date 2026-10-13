<?php
// POST token + event id (+ csrf). JSON reply. Marks participant arrived.
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');
if (!is_admin()) { http_response_code(401); echo json_encode(['status' => 'error', 'message' => 'Not signed in.']); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Session expired. Reload the page.']); exit; }

$token = trim((string)($_POST['token'] ?? ''));
$eid = (int)($_POST['e'] ?? 0);
$pdo = db();
$st = $pdo->prepare('SELECT r.*, e.name AS event_name FROM registrations r JOIN events e ON e.id=r.event_id WHERE r.token=?');
$st->execute([$token]);
$r = $st->fetch();

if (!$r) { echo json_encode(['status' => 'invalid', 'message' => 'QR code not recognised.']); exit; }
$who = ['name' => $r['first_name'] . ' ' . $r['surname'], 'company' => $r['company'], 'type' => $r['participant_type']];
if ($eid && (int)$r['event_id'] !== $eid) { echo json_encode(['status' => 'wrong_event', 'message' => 'Registered for another event: ' . $r['event_name']] + $who); exit; }
if ($r['arrived_at']) { echo json_encode(['status' => 'already', 'message' => 'Already arrived ' . date('M j, g:i A', strtotime($r['arrived_at']))] + $who); exit; }
$pdo->prepare('UPDATE registrations SET arrived_at=NOW() WHERE id=? AND arrived_at IS NULL')->execute([$r['id']]);
echo json_encode(['status' => 'ok', 'message' => 'Marked arrived'] + $who);
