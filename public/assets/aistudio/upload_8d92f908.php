<?php
// Receives the rows read from the Excel sheet (JSON) and saves them as PENDING certificates.
require __DIR__ . '/lib.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || !check_csrf($in['csrf'] ?? '')) { http_response_code(403); echo json_encode(['error' => 'Session expired. Reload the page.']); exit; }
$rows = $in['rows'] ?? [];
if (!is_array($rows) || !count($rows) || count($rows) > 1000) { echo json_encode(['error' => 'Send between 1 and 1000 rows.']); exit; }

$typeMap = ['course' => 'Course', 'value-added' => 'Value-added', 'value added' => 'Value-added', 'valueadded' => 'Value-added', 'internship' => 'Internship'];
$default = $typeMap[strtolower(trim($in['defaultType'] ?? ''))] ?? 'Course';
$letter = ['Course' => 'C', 'Value-added' => 'V', 'Internship' => 'I'];
$alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$db = dbc();
$inserted = 0; $skipped = 0; $errors = [];

function valid_date($s) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s)) return null;
    [$y, $m, $d] = array_map('intval', explode('-', $s));
    return checkdate($m, $d, $y) ? $s : null;
}

foreach ($rows as $i => $r) {
    $line = $i + 2;
    $name = trim($r['name'] ?? ''); $college = trim($r['college'] ?? ''); $course = trim($r['course'] ?? '');
    if ($name === '' || $college === '' || $course === '') { $errors[] = "Row $line: name, college or course is empty"; continue; }
    $type = $typeMap[strtolower(trim($r['type'] ?? ''))] ?? $default;
    $start = valid_date($r['start'] ?? ''); $end = valid_date($r['end'] ?? '');
    if (!$start || !$end || $end < $start) { $errors[] = "Row $line ($name): start/end date missing or wrong"; continue; }
    $duration = trim($r['duration'] ?? '');
    if ($duration === '') {
        $days = (int)((strtotime($end) - strtotime($start)) / 86400) + 1;
        $duration = ($days >= 7 && $days % 7 === 0) ? ($days / 7) . ' Week' . ($days > 7 ? 's' : '') : $days . ' Day' . ($days > 1 ? 's' : '');
    }
    $phone = last10($r['phone'] ?? ''); $email = trim($r['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $email = ''; $errors[] = "Row $line ($name): email not valid, left blank"; }

    // skip duplicates (same student + course + college + start date)
    $st = $db->prepare('SELECT 1 FROM certificates WHERE student_name=? AND course=? AND college=? AND start_date=? LIMIT 1');
    $st->bind_param('ssss', $name, $course, $college, $start); $st->execute();
    if ($st->get_result()->fetch_row()) { $skipped++; continue; }

    $year = (int)substr($end, 0, 4);
    for ($try = 0; $try < 8; $try++) {
        $suffix = ''; for ($k = 0; $k < 6; $k++) $suffix .= $alpha[random_int(0, strlen($alpha) - 1)];
        $cid = "NT-$year-$suffix";
        $c = $db->prepare('SELECT 1 FROM certificates WHERE certificate_id=?'); $c->bind_param('s', $cid); $c->execute();
        if (!$c->get_result()->fetch_row()) break;
    }
    $token = bin2hex(random_bytes(16));
    $salt = bin2hex(random_bytes(8)); $hash = $phone ? hash('sha256', $salt . substr($phone, -4)) : null;
    $issue = date('Y-m-d');
    $ins = $db->prepare("INSERT INTO certificates (certificate_id,qr_token,student_name,college,course,cert_type,duration,start_date,end_date,issue_date,status,phone_salt,phone_last4_hash)
                         VALUES (?,?,?,?,?,?,?,?,?,?, 'pending', ?, ?)");
    $ins->bind_param('ssssssssssss', $cid, $token, $name, $college, $course, $type, $duration, $start, $end, $issue, $salt, $hash);
    $ins->execute();
    $ct = $db->prepare('INSERT INTO student_contacts (certificate_id,phone,email) VALUES (?,?,?)');
    $ct->bind_param('sss', $cid, $phone, $email); $ct->execute();
    $inserted++;
}
echo json_encode(['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors]);
