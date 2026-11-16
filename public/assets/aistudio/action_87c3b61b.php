<?php
require __DIR__ . '/lib.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_csrf($_POST['csrf'] ?? '')) {
    $do = $_POST['do'] ?? ''; $id = $_POST['id'] ?? '';
    $db = dbc();
    if ($do === 'approve') {
        $st = $db->prepare("UPDATE certificates SET status='approved', approval_note=? WHERE certificate_id=?");
        $note = 'Approved ' . date('Y-m-d H:i'); $st->bind_param('ss', $note, $id); $st->execute();
    } elseif ($do === 'revoke') {
        $st = $db->prepare("UPDATE certificates SET status='revoked', approval_note=? WHERE certificate_id=?");
        $note = 'Revoked ' . date('Y-m-d H:i'); $st->bind_param('ss', $note, $id); $st->execute();
    } elseif ($do === 'delete') {
        $db->begin_transaction();
        $st = $db->prepare('DELETE FROM student_contacts WHERE certificate_id=?'); $st->bind_param('s', $id); $st->execute();
        $st = $db->prepare('DELETE FROM certificates WHERE certificate_id=?'); $st->bind_param('s', $id); $st->execute();
        $db->commit();
    } elseif ($do === 'approve_all') {
        $note = 'Approved ' . date('Y-m-d H:i');
        $st = $db->prepare("UPDATE certificates SET status='approved', approval_note=? WHERE status='pending'");
        $st->bind_param('s', $note); $st->execute();
    }
}
$back = in_array($_POST['back'] ?? '', ['pending', 'approved', 'revoked'], true) ? $_POST['back'] : 'all';
header('Location: admin.php?status=' . $back);