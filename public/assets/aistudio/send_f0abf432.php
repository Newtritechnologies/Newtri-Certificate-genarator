<?php
// Sends certificates automatically to all APPROVED students (20 at a time; the dashboard calls this repeatedly).
require __DIR__ . '/lib.php';
require __DIR__ . '/mailer.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');
set_time_limit(120);
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || !check_csrf($in['csrf'] ?? '')) { http_response_code(403); echo json_encode(['error' => 'Session expired. Reload the page.']); exit; }
$db = dbc();

if (($in['mode'] ?? '') === 'retry') {
    $db->query('UPDATE student_contacts SET send_error=NULL WHERE send_error IS NOT NULL');
    echo json_encode(['ok' => true]); exit;
}

$wa = wa_enabled();
$where = "c.status='approved' AND s.send_error IS NULL AND ((s.email IS NOT NULL AND s.email<>'' AND s.email_sent_at IS NULL)"
       . ($wa ? " OR (s.phone IS NOT NULL AND s.phone<>'' AND s.wa_sent_at IS NULL)" : '') . ')';
$ids = array_values(array_filter(array_map('strval', $in['ids'] ?? []), function ($x) { return preg_match('/^[A-Z0-9-]{6,30}$/', $x); }));
if ($ids) { $where .= " AND c.certificate_id IN ('" . implode("','", array_slice($ids, 0, 500)) . "')"; }   // only the ticked rows
$base = 'FROM certificates c JOIN student_contacts s ON s.certificate_id=c.certificate_id WHERE ' . $where;

$q = $db->query("SELECT c.certificate_id,c.qr_token,c.student_name,c.course,c.cert_type,c.duration,c.start_date,c.end_date,
                        s.phone,s.email,s.email_sent_at,s.wa_sent_at $base ORDER BY c.id LIMIT 20");
$sent = 0; $failed = 0;
while ($r = $q->fetch_assoc()) {
    $link = verify_url($r['qr_token']);
    $name = $r['student_name']; $course = $r['course'];
    $err = '';
    if (!empty($r['email']) && empty($r['email_sent_at'])) {
        $subject = 'Your NEWTRI Technologies certificate - ' . $course;
        $period = fmt_date($r['start_date']) . ' to ' . fmt_date($r['end_date']);
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #CFE5FF;border-radius:12px;overflow:hidden">'
              . '<div style="background:#0B2F6B;color:#fff;padding:18px 22px;font-size:20px;letter-spacing:3px"><b>NEWTRI</b> TECHNOLOGIES</div>'
              . '<div style="padding:22px;color:#1B2638;font-size:15px;line-height:1.6">'
              . '<p>Dear <b>' . e($name) . '</b>,</p>'
              . '<p>Congratulations! Your <b>' . e($r['cert_type']) . '</b> certificate for <b>' . e($course) . '</b> (' . e($r['duration']) . ', ' . e($period) . ') is ready.</p>'
              . '<p style="text-align:center;margin:26px 0"><a href="' . e($link) . '" style="background:#1E6FE0;color:#fff;padding:12px 26px;border-radius:8px;text-decoration:none;font-weight:bold">View / Download Certificate</a></p>'
              . '<p style="color:#55657F;font-size:13px">Open the link and choose “Download / Print” → “Save as PDF”. Anyone can scan the QR code on your certificate to check that it was issued by NEWTRI Technologies.</p>'
              . '<p>Regards,<br><b>NEWTRI Technologies</b><br>Karangad, Kanyakumari District, Tamil Nadu</p></div></div>';
        $text = "Dear $name,\n\nCongratulations! Your {$r['cert_type']} certificate for $course ($period) is ready.\nView / download: $link\n\nNEWTRI Technologies\nKarangad, Kanyakumari District, Tamil Nadu";
        if (send_email($r['email'], $name, $subject, $html, $text, $err)) {
            $u = $db->prepare('UPDATE student_contacts SET email_sent_at=NOW() WHERE certificate_id=?');
            $u->bind_param('s', $r['certificate_id']); $u->execute(); $sent++;
        } else {
            $u = $db->prepare('UPDATE student_contacts SET send_error=? WHERE certificate_id=?');
            $m = 'Email: ' . $err; $u->bind_param('ss', $m, $r['certificate_id']); $u->execute(); $failed++; continue;
        }
    }
    if ($wa && !empty($r['phone']) && empty($r['wa_sent_at'])) {
        if (send_whatsapp($r['phone'], $name, $course, $link, $err)) {
            $u = $db->prepare('UPDATE student_contacts SET wa_sent_at=NOW() WHERE certificate_id=?');
            $u->bind_param('s', $r['certificate_id']); $u->execute(); $sent++;
        } else {
            $u = $db->prepare('UPDATE student_contacts SET send_error=? WHERE certificate_id=?');
            $u->bind_param('ss', $err, $r['certificate_id']); $u->execute(); $failed++;
        }
    }
}
$remaining = (int)$db->query("SELECT COUNT(*) $base")->fetch_row()[0];
echo json_encode(['sent' => $sent, 'failed' => $failed, 'remaining' => $remaining]);
