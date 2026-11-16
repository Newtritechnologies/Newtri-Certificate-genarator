<?php
// Makes a finished PDF for every APPROVED certificate and saves it in the "certificates" folder.
// Run it by double-clicking make_pdfs.bat   (XAMPP Apache + MySQL must be running, and you need internet for the fonts).
// Add --force to remake PDFs that already exist.
if (php_sapi_name() !== 'cli') { exit('Run this from make_pdfs.bat'); }
require __DIR__ . '/config.php';

$force = in_array('--force', $argv ?? [], true);
$outDir = __DIR__ . DIRECTORY_SEPARATOR . 'certificates';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);
$tmpProfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'newtri_chrome_profile';

$chrome = !empty($CHROME_PATH) ? $CHROME_PATH : '';
if (!$chrome) {
    foreach (['C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
              'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
              (getenv('LOCALAPPDATA') ?: '') . '\\Google\\Chrome\\Application\\chrome.exe',
              'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe'] as $c) {
        if (is_file($c)) { $chrome = $c; break; }
    }
}
if (!$chrome) { exit("Chrome was not found. Add this line to config.php:  \$CHROME_PATH = 'C:\\\\path\\\\to\\\\chrome.exe';\n"); }

$site = 'http://localhost/' . basename(__DIR__);
$db = db();
$q = $db->query("SELECT certificate_id, qr_token, student_name FROM certificates WHERE status='approved' ORDER BY id");
$made = 0; $skipped = 0; $failed = 0;
echo "Using: $chrome\nSaving PDFs in: $outDir\n\n";
while ($r = $q->fetch_assoc()) {
    $pdf = $outDir . DIRECTORY_SEPARATOR . $r['certificate_id'] . '.pdf';
    if (is_file($pdf) && !$force) { $skipped++; continue; }
    @unlink($pdf);
    $url = $site . '/v.php?t=' . $r['qr_token'] . '&export=1';
    $cmd = '"' . $chrome . '" --headless=new --disable-gpu --no-pdf-header-footer --run-all-compositor-stages-before-draw'
         . ' --virtual-time-budget=20000 --user-data-dir="' . $tmpProfile . '" --print-to-pdf="' . $pdf . '" "' . $url . '" 2>&1';
    exec($cmd, $out, $code);
    if (is_file($pdf) && filesize($pdf) > 10000) { echo "OK      {$r['certificate_id']}  {$r['student_name']}\n"; $made++; }
    else { echo "FAILED  {$r['certificate_id']}  {$r['student_name']}\n"; $failed++; }
}
echo "\nDone. Made $made, skipped $skipped (already exist), failed $failed.\n";
if ($made && empty($BASE_URL)) {
    echo "\nNOTE: \$BASE_URL is empty in config.php, so the QR codes in these PDFs point to 'localhost'.\n"
       . "Set \$BASE_URL to your real website address and run again with --force before sending to students.\n";
}
