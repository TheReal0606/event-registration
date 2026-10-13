<?php
/**
 * Pure-PHP SMTP mailer (STARTTLS + AUTH LOGIN) with inline PNG images.
 * Configure SMTP_* constants in config.local.php.
 */

/** @param array<string,string> $images content-id => PNG bytes (shown inline AND attached) */
function sendMail(string $toAddr, string $toName, string $subject, string $html, array $images = []): array
{
    if (!defined('SMTP_USER') || SMTP_USER === '') return ['success' => false, 'error' => 'SMTP not configured.'];
    if (!filter_var($toAddr, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'error' => 'Invalid email address.'];

    $host     = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
    $port     = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $fromAddr = (defined('SMTP_FROM') && SMTP_FROM !== '') ? SMTP_FROM : SMTP_USER;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'MTRCB Events';

    $readLine = function ($sock) {
        $buf = '';
        while (!feof($sock)) {
            $line = fgets($sock, 1024);
            if ($line === false) break;
            $buf .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $buf;
    };
    $cmd  = function ($sock, $c) use ($readLine) { fputs($sock, $c . "\r\n"); return $readLine($sock); };
    $code = fn($r) => (int)substr(trim($r), 0, 3);
    $fail = function ($sock, string $msg) { if (is_resource($sock)) fclose($sock); return ['success' => false, 'error' => $msg]; };

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) return ['success' => false, 'error' => "Cannot connect to {$host}:{$port} - {$errstr}"];
    stream_set_timeout($sock, 20);

    $r = $readLine($sock);
    if ($code($r) !== 220) return $fail($sock, "SMTP greeting failed: {$r}");
    $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
    if ($code($r = $cmd($sock, $ehlo)) !== 250) return $fail($sock, "EHLO failed: {$r}");
    if ($code($r = $cmd($sock, 'STARTTLS')) !== 220) return $fail($sock, "STARTTLS failed: {$r}");
    if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) return $fail($sock, 'TLS handshake failed.');
    if ($code($r = $cmd($sock, $ehlo)) !== 250) return $fail($sock, "EHLO (TLS) failed: {$r}");
    if ($code($r = $cmd($sock, 'AUTH LOGIN')) !== 334) return $fail($sock, "AUTH failed: {$r}");
    if ($code($r = $cmd($sock, base64_encode(SMTP_USER))) !== 334) return $fail($sock, "AUTH user rejected: {$r}");
    if ($code($r = $cmd($sock, base64_encode(SMTP_PASS))) !== 235) return $fail($sock, 'SMTP login rejected - check account / App Password.');
    if ($code($r = $cmd($sock, "MAIL FROM:<{$fromAddr}>")) !== 250) return $fail($sock, "MAIL FROM rejected: {$r}");
    if ($code($r = $cmd($sock, "RCPT TO:<{$toAddr}>")) !== 250) return $fail($sock, "RCPT TO rejected: {$r}");
    if ($code($r = $cmd($sock, 'DATA')) !== 354) return $fail($sock, "DATA failed: {$r}");

    $enc = fn($s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $b64 = fn(string $s) => chunk_split(base64_encode($s), 76, "\r\n");
    $rel = 'rel_' . bin2hex(random_bytes(8));

    $m  = "From: {$enc($fromName)} <{$fromAddr}>\r\nTo: {$enc($toName)} <{$toAddr}>\r\nSubject: {$enc($subject)}\r\n";
    $m .= 'Date: ' . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . "@mtrcb-events>\r\n";
    $m .= "MIME-Version: 1.0\r\nContent-Type: multipart/related; boundary=\"$rel\"\r\n\r\n";
    $m .= "--$rel\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($html);
    foreach ($images as $cid => $png) {
        $m .= "--$rel\r\nContent-Type: image/png; name=\"$cid.png\"\r\nContent-Transfer-Encoding: base64\r\n"
            . "Content-ID: <$cid>\r\nContent-Disposition: inline; filename=\"$cid.png\"\r\n\r\n" . $b64($png);
    }
    $m .= "--$rel--\r\n";
    // dot-stuffing is unnecessary: base64 lines never start with '.'
    fputs($sock, $m . "\r\n.\r\n");
    $r = $readLine($sock);
    $cmd($sock, 'QUIT');
    fclose($sock);
    return $code($r) === 250 ? ['success' => true] : ['success' => false, 'error' => "Message rejected: {$r}"];
}

/** Email with the participant's QR code. Returns sendMail() result. */
function send_qr_email(array $reg, string $eventName): array
{
    $name = $reg['first_name'] . ' ' . $reg['surname'];
    $html = '<!DOCTYPE html><html><body style="margin:0;background:#f3f6f4;font-family:Segoe UI,Arial,sans-serif;color:#1d1d1b">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fff;border-radius:12px;border:1px solid #d6ded9">
<tr><td style="padding:20px 28px;border-bottom:4px solid #1F8A3B;font-size:18px;font-weight:700">
<img src="cid:mtrcb-logo" width="48" height="48" alt="" style="vertical-align:middle;border-radius:50%;margin-right:10px">MTRCB Events</td></tr>
<tr><td style="padding:28px">
<h1 style="font-size:21px;margin:0 0 12px">You are registered</h1>
<p style="font-size:15px;line-height:1.6">Hello <strong>' . e($name) . '</strong>,<br>
you are registered for <strong>' . e($eventName) . '</strong> as <strong>' . e($reg['participant_type']) . '</strong>.</p>
<p style="font-size:15px;line-height:1.6">Present this QR code at the event entrance. Keep this email, or save the image to your phone.</p>
<p style="text-align:center;margin:22px 0"><img src="cid:mtrcb-qr" width="260" height="260" alt="Your QR code" style="border:1px solid #d6ded9;border-radius:8px"></p>
<p style="text-align:center;font-size:12px;color:#5b6560">Ref: ' . e(strtoupper(substr($reg['token'], 0, 8))) . '. This code is personal. Do not share it.</p>
</td></tr></table></td></tr></table></body></html>';
    return sendMail($reg['email'], $name, "Your QR code: $eventName", $html, [
        'mtrcb-qr'   => qr_png($reg['token']),
        'mtrcb-logo' => file_get_contents(__DIR__ . '/../assets/logo-96.png'),
    ]);
}
