<?php
// Shared helpers for the NEWTRI certificate system.
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function dbc() { static $c = null; if ($c === null) { $c = db(); } return $c; }
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Address of this folder on the web (works on localhost and on real hosting).
// To force a fixed address, add  $BASE_URL = 'https://yourdomain.com/certs';  in config.php
function base_url() {
    global $BASE_URL;
    if (!empty($BASE_URL)) return rtrim($BASE_URL, '/');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
}
function verify_url($token) { return base_url() . '/v.php?t=' . $token; }

function is_admin() { return !empty($_SESSION['admin_id']); }
function require_admin() { if (!is_admin()) { header('Location: login.php'); exit; } }
function csrf() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function check_csrf($t) { return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }

function fmt_date($d) { return $d ? date('j F Y', strtotime($d)) : ''; }
function last10($p) { $d = preg_replace('/\D/', '', (string)$p); return strlen($d) >= 10 ? substr($d, -10) : ''; }

function page_head($title) {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($title) . '</title><style>
        *{box-sizing:border-box} body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#F4F8FF;color:#1B2638}
        .top{background:#0B2F6B;color:#fff;padding:14px 22px;display:flex;justify-content:space-between;align-items:center}
        .top b{letter-spacing:3px} .top a{color:#CFE5FF;margin-left:16px;text-decoration:none}
        .wrap{max-width:1150px;margin:22px auto;padding:0 14px}
        .card{background:#fff;border:1px solid #CFE5FF;border-radius:12px;padding:18px;margin-bottom:18px}
        h2{margin:0 0 10px;color:#0B2F6B} .muted{color:#55657F;font-size:14px}
        input,select{padding:10px;border:1px solid #9DBBE8;border-radius:8px;font-size:15px}
        .btn{display:inline-block;padding:9px 16px;border:0;border-radius:8px;background:#1E6FE0;color:#fff;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none}
        .btn.alt{background:#EAF3FF;color:#0B2F6B} .btn.red{background:#fff;color:#b00020;border:1px solid #b00020}
        .btn:disabled{opacity:.5;cursor:not-allowed} .btn.sm{padding:5px 10px;font-size:12px}
        table{border-collapse:collapse;width:100%;font-size:13px} th,td{padding:8px 6px;border-bottom:1px solid #EAF3FF;text-align:left;vertical-align:top}
        th{background:#EAF3FF;color:#0B2F6B} .tbl{overflow-x:auto}
        .tag{padding:2px 9px;border-radius:12px;font-size:12px;font-weight:700}
        .pending{background:#FFF3C4;color:#7a5b00} .approved{background:#D9F2E3;color:#146c3a} .revoked{background:#FBD9DE;color:#9b1c2e}
        .err{color:#b00020} .ok{color:#146c3a}
        </style></head><body>';
}
function top_bar() {
    echo '<div class="top"><b>NEWTRI CERTIFICATES</b><div><a href="admin.php">Dashboard</a><a href="history.php">History</a><a href="expenses.php">Expenses</a><a href="logout.php">Logout</a></div></div>';
}