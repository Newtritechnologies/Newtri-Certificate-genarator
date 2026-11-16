<?php
// Sends email through Gmail SMTP (SSL, port 465) - no extra library needed.
// And sends WhatsApp template messages through the WhatsApp Business Cloud API.

function _smtp_read($fp) {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $data;
}
function _smtp_cmd($fp, $cmd, $expect) {
    if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
    $r = _smtp_read($fp);
    if (substr($r, 0, 3) !== (string)$expect) throw new Exception('SMTP: ' . trim($r));
}
function _h($s) { return str_replace(["\r", "\n"], ' ', (string)$s); }
function _enc($s) { return '=?UTF-8?B?' . base64_encode(_h($s)) . '?='; }

function send_email($to, $toName, $subject, $html, $text, &$err) {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_FROM_NAME;
    $err = '';
    if (empty($SMTP_USER) || empty($SMTP_PASS) || strpos($SMTP_PASS, 'PASTE') !== false) { $err = 'Email not set up in config.php'; return false; }
    try {
        $fp = @stream_socket_client('ssl://' . $SMTP_HOST . ':' . $SMTP_PORT, $en, $es, 20);
        if (!$fp) throw new Exception("Cannot connect to mail server ($es)");
        stream_set_timeout($fp, 20);
        _smtp_cmd($fp, null, 220);
        _smtp_cmd($fp, 'EHLO newtri', 250);
        _smtp_cmd($fp, 'AUTH LOGIN', 334);
        _smtp_cmd($fp, base64_encode($SMTP_USER), 334);
        _smtp_cmd($fp, base64_encode($SMTP_PASS), 235);
        _smtp_cmd($fp, 'MAIL FROM:<' . _h($SMTP_USER) . '>', 250);
        _smtp_cmd($fp, 'RCPT TO:<' . _h($to) . '>', 250);
        _smtp_cmd($fp, 'DATA', 354);
        $b = 'nw' . bin2hex(random_bytes(8));
        $msg  = 'Date: ' . date('r') . "\r\n";
        $msg .= 'From: ' . _enc($SMTP_FROM_NAME) . ' <' . _h($SMTP_USER) . ">\r\n";
        $msg .= 'To: ' . _enc($toName) . ' <' . _h($to) . ">\r\n";
        $msg .= 'Subject: ' . _enc($subject) . "\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n";
        $msg .= "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n";
        $msg .= "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n";
        $msg .= "--$b--\r\n";
        fwrite($fp, $msg . "\r\n.\r\n");
        $r = _smtp_read($fp);
        if (substr($r, 0, 3) !== '250') throw new Exception('SMTP: ' . trim($r));
        @fwrite($fp, "QUIT\r\n"); fclose($fp);
        return true;
    } catch (Throwable $e) {
        $err = substr($e->getMessage(), 0, 200);
        return false;
    }
}

function wa_enabled() { global $WA_TOKEN, $WA_PHONE_ID; return !empty($WA_TOKEN) && !empty($WA_PHONE_ID); }

function send_whatsapp($phone10, $name, $course, $link, &$err) {
    global $WA_TOKEN, $WA_PHONE_ID, $WA_TEMPLATE, $WA_LANG;
    $err = '';
    $payload = ['messaging_product' => 'whatsapp', 'to' => '91' . $phone10, 'type' => 'template',
        'template' => ['name' => $WA_TEMPLATE ?: 'certificate_ready', 'language' => ['code' => $WA_LANG ?: 'en'],
            'components' => [['type' => 'body', 'parameters' => [
                ['type' => 'text', 'text' => $name], ['type' => 'text', 'text' => $course], ['type' => 'text', 'text' => $link]]]]]];
    $ch = curl_init('https://graph.facebook.com/v20.0/' . rawurlencode($WA_PHONE_ID) . '/messages');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $WA_TOKEN, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload)]);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $ce = curl_error($ch); curl_close($ch);
    if ($res === false) { $err = 'WhatsApp: ' . $ce; return false; }
    if ($code >= 200 && $code < 300) return true;
    $j = json_decode($res, true);
    $err = 'WhatsApp: ' . substr($j['error']['message'] ?? ('HTTP ' . $code), 0, 160);
    return false;
}
