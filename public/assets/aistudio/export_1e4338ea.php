<?php
// Downloads the certificates (with the current search / filters) as an Excel-friendly CSV file.
require __DIR__ . '/lib.php';
// ---- filter helper (built in, no extra file needed) ----
// Shared by admin.php and export.php: turns ?q=&status=&type=&college= into a safe SQL WHERE.
function build_filters(array $g): array {
    $status  = in_array($g['status'] ?? '', ['pending', 'approved', 'revoked'], true) ? $g['status'] : 'all';
    $type    = in_array($g['type'] ?? '', ['Course', 'Value-added', 'Internship'], true) ? $g['type'] : '';
    $college = trim($g['college'] ?? '');
    $q       = trim($g['q'] ?? '');
    $where = []; $types = ''; $params = [];
    if ($status !== 'all') { $where[] = 'c.status=?';    $types .= 's'; $params[] = $status; }
    if ($type !== '')      { $where[] = 'c.cert_type=?'; $types .= 's'; $params[] = $type; }
    if ($college !== '')   { $where[] = 'c.college=?';   $types .= 's'; $params[] = $college; }
    if ($q !== '') {
        $where[] = '(c.student_name LIKE ? OR c.certificate_id LIKE ? OR c.course LIKE ? OR c.college LIKE ?)';
        $like = '%' . $q . '%'; $types .= 'ssss'; array_push($params, $like, $like, $like, $like);
    }
    return ['status' => $status, 'type' => $type, 'college' => $college, 'q' => $q,
            'where' => $where ? ' WHERE ' . implode(' AND ', $where) : '', 'types' => $types, 'params' => $params];
}
require_admin();
$db = dbc();
$f = build_filters($_GET);
$st = $db->prepare('SELECT c.*, s.phone, s.email, s.email_sent_at FROM certificates c LEFT JOIN student_contacts s ON s.certificate_id=c.certificate_id' . $f['where'] . ' ORDER BY c.id DESC');
if ($f['params']) $st->bind_param($f['types'], ...$f['params']);
$st->execute(); $res = $st->get_result();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="newtri_certificates_' . date('Y-m-d') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['Certificate ID', 'Student', 'College', 'Course', 'Type', 'Duration', 'Start', 'End', 'Status', 'Phone', 'Email', 'Email sent', 'Verify link']);
while ($r = $res->fetch_assoc()) {
    fputcsv($out, [$r['certificate_id'], $r['student_name'], $r['college'], $r['course'], $r['cert_type'], $r['duration'], $r['start_date'], $r['end_date'],
                   $r['status'], $r['phone'], $r['email'], $r['email_sent_at'], verify_url($r['qr_token'])]);
}