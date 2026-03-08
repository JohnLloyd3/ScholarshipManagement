<?php
// Debug script to show SMTP conversation with the configured SMTP server
require_once __DIR__ . '/../../config/email.php';

$to = $argv[1] ?? null;
if (!$to) {
    echo "Usage: php send_test_code_debug.php recipient@example.com\n";
    exit(1);
}

$host = SMTP_HOST;
$port = SMTP_PORT;
$user = SMTP_USER;
$pass = SMTP_PASS;

echo "SMTP host={$host} port={$port} user={$user}\n";

$timeout = 15;
$fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
if (!$fp) {
    echo "Connection failed: {$errno} {$errstr}\n";
    exit(2);
}
stream_set_timeout($fp, $timeout);

function readAll($fp) {
    $data = '';
    while (!feof($fp)) {
        $line = fgets($fp, 515);
        if ($line === false) break;
        echo "S: " . rtrim($line) . "\n";
        $data .= $line;
        if (preg_match('/^\d{3}\s/', $line)) break;
    }
    return $data;
}
function writeLine($fp, $cmd) {
    echo "C: {$cmd}\n";
    fwrite($fp, $cmd . "\r\n");
}

$banner = readAll($fp);

writeLine($fp, 'EHLO localhost');
$ehlo = readAll($fp);

writeLine($fp, 'STARTTLS');
$starttls = readAll($fp);

if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    echo "TLS negotiation failed. Is OpenSSL enabled in PHP?\n";
    fclose($fp);
    exit(3);
}

writeLine($fp, 'EHLO localhost');
$ehlo2 = readAll($fp);

writeLine($fp, 'AUTH LOGIN');
$auth = readAll($fp);

writeLine($fp, base64_encode($user));
$uResp = readAll($fp);

writeLine($fp, base64_encode($pass));
$pResp = readAll($fp);

writeLine($fp, 'MAIL FROM:<' . EMAIL_FROM . '>');
$mf = readAll($fp);

writeLine($fp, 'RCPT TO:<' . $to . '>');
$rt = readAll($fp);

writeLine($fp, 'DATA');
$dataResp = readAll($fp);

$headers = [];
$headers[] = 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>';
$headers[] = 'To: <' . $to . '>';
$headers[] = 'Subject: Debug Test SMTP';
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';

$body = "This is a debug test message.\n";
$payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";

fwrite($fp, $payload . "\r\n");
$sent = readAll($fp);

writeLine($fp, 'QUIT');
fclose($fp);

echo "Done. Check responses above for errors.\n";
