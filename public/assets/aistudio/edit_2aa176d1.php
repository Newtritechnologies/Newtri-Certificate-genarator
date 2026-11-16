<?php
// Fix a student's details (name, college, course, dates, phone, email) without deleting the certificate.
require __DIR__ . '/lib.php';
require_admin();
$db = dbc();
$id = $_GET['id'] ?? ($_POST['id'] ?? '');
if (!preg_match('/^[A-Z0-9-]{6,30}$/', $id)) { header('Location: admin.php'); exit; }
$st = $db->prepare('SELECT c.*, s.phone, s.email FROM certificates c LEFT JOIN student_contacts s ON s.certificate_id=c.certificate_id WHERE c.certificate_id=?');
$st->bind_param('s', $id); $st->execute();
$r = $st->get_result()->fetch_assoc();
if (!$r) { header('Location: admin.php'); exit; }

function good_date($s) { if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s)) return false; [$y, $m, $d] = array_map('intval', explode('-', $s)); return checkdate($m, $d, $y); }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? '')) { $err = 'Session expired. Please try again.'; }
    else {
        $name = trim($_POST['student_name'] ?? ''); $college = trim($_POST['college'] ?? ''); $course = trim($_POST['course'] ?? '');
        $type = in_array($_POST['cert_type'] ?? '', ['Course', 'Value-added', 'Internship'], true) ? $_POST['cert_type'] : $r['cert_type'];
        $duration = trim($_POST['duration'] ?? ''); $start = $_POST['start_date'] ?? ''; $end = $_POST['end_date'] ?? '';
        $phone = last10($_POST['phone'] ?? ''); $email = trim($_POST['email'] ?? '');
        if ($name === '' || $college === '' || $course === '') $err = 'Name, college and course cannot be empty.';
        elseif (!good_date($start) || !good_date($end) || $end < $start) $err = 'Start and end dates are not valid.';
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'That email address is not valid.';
        else {
            $up = $db->prepare('UPDATE certificates SET student_name=?, college=?, course=?, cert_type=?, duration=?, start_date=?, end_date=? WHERE certificate_id=?');
            $up->bind_param('ssssssss', $name, $college, $course, $type, $duration, $start, $end, $id); $up->execute();
            $salt = bin2hex(random_bytes(8)); $hash = $phone ? hash('sha256', $salt . substr($phone, -4)) : null;
            $up = $db->prepare('UPDATE certificates SET phone_salt=?, phone_last4_hash=? WHERE certificate_id=?');
            $up->bind_param('sss', $salt, $hash, $id); $up->execute();
            $ex = $db->prepare('SELECT 1 FROM student_contacts WHERE certificate_id=?'); $ex->bind_param('s', $id); $ex->execute();
            if ($ex->get_result()->fetch_row()) { $c = $db->prepare('UPDATE student_contacts SET phone=?, email=? WHERE certificate_id=?'); $c->bind_param('sss', $phone, $email, $id); }
            else { $c = $db->prepare('INSERT INTO student_contacts (phone,email,certificate_id) VALUES (?,?,?)'); $c->bind_param('sss', $phone, $email, $id); }
            $c->execute();
            if (!empty($_POST['resend'])) { $db->query("UPDATE student_contacts SET email_sent_at=NULL, wa_sent_at=NULL, send_error=NULL WHERE certificate_id='" . $db->real_escape_string($id) . "'"); }
            header('Location: admin.php'); exit;
        }
        $r = array_merge($r, ['student_name' => $name, 'college' => $college, 'course' => $course, 'cert_type' => $type, 'duration' => $duration, 'start_date' => $start, 'end_date' => $end, 'phone' => $phone, 'email' => $email]);
    }
}
page_head('Edit certificate'); top_bar();
?>
<div class="wrap" style="max-width:640px">
  <div class="card">
    <h2>Edit <?= e($r['certificate_id']) ?></h2>
    <p class="muted">The QR code and link stay the same. Changes show on the certificate page straight away.</p>
    <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($id) ?>">
      <p>Student name<br><input name="student_name" value="<?= e($r['student_name']) ?>" required style="width:100%"></p>
      <p>College<br><input name="college" value="<?= e($r['college']) ?>" required style="width:100%"></p>
      <p>Course / programme<br><input name="course" value="<?= e($r['course']) ?>" required style="width:100%"></p>
      <p>Type<br><select name="cert_type"><?php foreach (['Course', 'Value-added', 'Internship'] as $t): ?><option<?= $r['cert_type'] === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></p>
      <p>Duration<br><input name="duration" value="<?= e($r['duration']) ?>" placeholder="e.g. 1 Month"></p>
      <p>Start date &nbsp; <input type="date" name="start_date" value="<?= e($r['start_date']) ?>" required> &nbsp; End date &nbsp; <input type="date" name="end_date" value="<?= e($r['end_date']) ?>" required></p>
      <p>Phone<br><input name="phone" value="<?= e($r['phone'] ?? '') ?>" placeholder="10-digit mobile"></p>
      <p>Email<br><input name="email" value="<?= e($r['email'] ?? '') ?>" style="width:100%"></p>
      <p><label><input type="checkbox" name="resend" value="1"> Mark as “not sent yet” so it can be sent again</label></p>
      <p><button class="btn">Save changes</button> &nbsp; <a class="btn alt" href="admin.php">Cancel</a></p>
    </form>
  </div>
</div>
</body></html>
