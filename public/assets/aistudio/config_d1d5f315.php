<?php
// NEWTRI certificate system - settings (keep this file private, never upload it to GitHub)

// ---- Database ----
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'newtri_certs';
$DB_USER = 'root';
$DB_PASS = 'Vibish@2003';      // <-- fill in (use the NEW password)

// ---- Public address of this site ----
// Change ONLY this line when the tunnel address changes. No slash at the end.
$BASE_URL = 'https://ind-call-tomato-cable.trycloudflare.com/newtri-certs';

// ---- Email (Gmail) ----
$SMTP_HOST = 'smtp.gmail.com';
$SMTP_PORT = 465;
$SMTP_USER = 'newtritechnologies@gmail.com';
$SMTP_PASS = 'PUT_16_LETTER_APP_PASSWORD_HERE'; // <-- fill in, no spaces
$SMTP_FROM_NAME = 'NEWTRI Technologies';

// ---- WhatsApp Business Cloud API (optional, leave empty for now) ----
$WA_TOKEN = '';
$WA_PHONE_ID = '';
$WA_TEMPLATE = 'certificate_ready';
$WA_LANG = 'en';

function db() {
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $c = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $c->set_charset('utf8mb4');
    return $c;
}