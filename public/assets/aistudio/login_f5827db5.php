<?php
require __DIR__ . '/lib.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? '')) { $err = 'Session expired. Please try again.'; }
    else {
        $u = trim($_POST['username'] ?? '');
        $st = dbc()->prepare('SELECT id,password_hash FROM admin_users WHERE username=?');
        $st->bind_param('s', $u); $st->execute();
        $r = $st->get_result()->fetch_assoc();
        if ($r && password_verify($_POST['password'] ?? '', $r['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $r['id'];
            header('Location: admin.php'); exit;
        }
        usleep(800000);
        $err = 'Wrong username or password.';
    }
}
page_head('Admin login');
echo '<div class="wrap" style="max-width:400px"><div class="card"><h2>NEWTRI Admin</h2>';
if ($err) echo '<p class="err">' . e($err) . '</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrf()) . '">
<p><input name="username" placeholder="Username" required style="width:100%"></p>
<p><input name="password" type="password" placeholder="Password" required style="width:100%"></p>
<button class="btn">Login</button></form></div></div></body></html>';
