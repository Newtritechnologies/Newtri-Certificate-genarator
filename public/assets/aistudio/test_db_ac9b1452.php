<?php
require __DIR__ . '/config.php';
header('Content-Type: text/html; charset=utf-8');
echo '<h2>NEWTRI database test</h2>';
try {
    $c = db();
    echo '<p style="color:green"><b>Connected to MySQL successfully.</b></p>';
    $r = $c->query('SHOW TABLES');
    echo '<p>Tables found in <b>' . htmlspecialchars($DB_NAME) . '</b>:</p><ul>';
    while ($row = $r->fetch_row()) { echo '<li>' . htmlspecialchars($row[0]) . '</li>'; }
    echo '</ul>';
} catch (Throwable $e) {
    echo '<p style="color:red"><b>Could not connect.</b></p>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
}
