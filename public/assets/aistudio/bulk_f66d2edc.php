<?php
// Bulk actions on the ticked rows: approve | revoke | delete | resend (mark as "not sent yet")
require __DIR__ . '/lib.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || !check_csrf($in['csrf'] ?? '')) { http_response_code(403); echo json_encode(['error' => 'Session expired. Reload the page.']); exit; }
$do = $in['do'] ?? '';
$ids = array_values(array_filter(array_map('strval', $in['ids'] ?? []), function ($x) { return preg_match('/^[A-Z0-9-]{6,30}$/', $x); }));
$ids = array_slice($ids, 0, 500);
if (!$ids) { echo json_encode(['error' => 'No valid rows selected.']); exit; }
$db = dbc(); $n = 0;

if ($do === 'approve' || $do === 'revoke') {
    $status = $do === 'approve' ? 'approved' : 'revoked';
    $note = ucfirst($status) . ' ' . date('Y-m-d H:i');
    $st = $db->prepare('UPDATE certificates SET status=?, approval_note=? WHERE certificate_id=?');
    foreach ($ids as $id) { $st->bind_param('sss', $status, $note, $id); $st->execute(); $n += $st->affected_rows; }
} elseif ($do === 'resend') {
    $st = $db->prepare('UPDATE student_contacts SET email_sent_at=NULL, wa_sent_at=NULL, send_error=NULL WHERE certificate_id=?');
    foreach ($ids as $id) { $st->bind_param('s', $id); $st->execute(); $n += $st->affected_rows; }
} elseif ($do === 'delete') {
    $db->begin_transaction();
    $a = $db->prepare('DELETE FROM student_contacts WHERE certificate_id=?');
    $b = $db->prepare('DELETE FROM certificates WHERE certificate_id=?');
    foreach ($ids as $id) { $a->bind_param('s', $id); $a->execute(); $b->bind_param('s', $id); $b->execute(); $n += $b->affected_rows; }
    $db->commit();
} else { echo json_encode(['error' => 'Unknown action.']); exit; }
echo json_encode(['ok' => true, 'changed' => $n]);
