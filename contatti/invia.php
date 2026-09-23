<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

// La configurazione con la password SMTP si trova fuori da htdocs.
$configPath = dirname(__DIR__, 2) . '/contact-config.php';
if (!is_file($configPath)) {
    error_log('Modulo contatti: configurazione SMTP assente.');
    fail('configurazione', 503);
}
$config = require $configPath;
if (!is_array($config) || empty($config['smtp_user']) || empty($config['smtp_password']) || empty($config['smtp_host'])) {
    fail('configurazione', 503);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fail('campi', 405);
}
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $originHost = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
    $currentHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    if (!$originHost || strtolower($originHost) !== $currentHost) fail('campi', 403);
}
if (!empty($_POST['website'])) {
    // Una trappola per bot: nessun messaggio viene spedito.
    header('Location: /contatti/', true, 303);
    exit;
}
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) fail('file', 413);
$type = $_POST['form_type'] ?? '';
if (!in_array($type, ['richiesta', 'candidatura'], true) || ($_POST['privacy_ack'] ?? '') !== '1') fail('campi', 400);

function field(string $name, int $max, bool $required = false): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) fail('campi', 400);
    $value = trim($value);
    if (strlen($value) > $max * 4 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) fail('campi', 400);
    if ($required && $value === '') fail('campi', 400);
    return $value;
}
function fail(string $code, int $status): void
{
    http_response_code($status);
    header('Location: /contatti/?errore=' . rawurlencode($code), true, 303);
    exit;
}
$name = field('Nome e cognome', 120, true);
$email = field('email', 254, true);
$phone = field('Telefono', 35);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strpos($email, "\r") !== false || strpos($email, "\n") !== false) fail('campi', 400);
if ($type === 'richiesta') {
    $company = field('Azienda', 150);
    $subject = field('Oggetto', 150, true);
    $message = field('Messaggio', 5000, true);
    $body = "Richiesta dal sito\n\nNome: $name\nEmail: $email\nTelefono: $phone\nAzienda: $company\nOggetto: $subject\n\nMessaggio:\n$message";
} else {
    $area = field('Area di interesse', 120);
    $presentation = field('Presentazione', 3000, true);
    $body = "Candidatura dal sito\n\nNome: $name\nEmail: $email\nTelefono: $phone\nArea di interesse: $area\n\nPresentazione:\n$presentation";
    $file = $_FILES['attachment'] ?? null;
    if (!is_array($file) || is_array($file['name'] ?? null) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) fail('file', 400);
    if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) fail('file', 413);
    $filename = basename(str_replace('\\', '/', (string) $file['name']));
    if (strlen($filename) > 180 || !preg_match('/\.(pdf|doc|docx)$/i', $filename, $matches)) fail('file', 400);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];
    if (!in_array($mime, $allowed[strtolower($matches[1])], true)) fail('file', 400);
    $filename = 'curriculum.' . strtolower($matches[1]);
}

// Limita gli invii dallo stesso IP senza conservare l'indirizzo in chiaro.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$key = hash('sha256', 'ca-form|' . $ip);
$limitFile = sys_get_temp_dir() . '/ca-contact-' . $key;
$handle = @fopen($limitFile, 'c+');
if ($handle && flock($handle, LOCK_EX)) {
    $history = json_decode(stream_get_contents($handle) ?: '[]', true);
    if (!is_array($history)) $history = [];
    $history = array_values(array_filter($history, static fn($time) => is_int($time) && $time > time() - 900));
    if (count($history) >= 4) {
        flock($handle, LOCK_UN);
        fclose($handle);
        fail('limite', 429);
    }
    $history[] = time();
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($history));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

require __DIR__ . '/../vendor/phpmailer/Exception.php';
require __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/SMTP.php';
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->Port = (int) ($config['smtp_port'] ?? 587);
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_password'];
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->setFrom($config['smtp_user'], 'C.A. Automazione - Sito web');
    $mail->addAddress('caniatoa@libero.it');
    $mail->addReplyTo($email, $name);
    $mail->Subject = $type === 'richiesta' ? 'Richiesta dal sito: ' . $subject : 'Candidatura dal sito: ' . $name;
    $mail->Body = $body;
    if ($type === 'candidatura') $mail->addAttachment($file['tmp_name'], $filename);
    $mail->send();
} catch (MailException $exception) {
    error_log('Modulo contatti: errore invio SMTP (' . get_class($exception) . ').');
    fail('invio', 502);
}
header('Location: /contatti/grazie/?tipo=' . rawurlencode($type), true, 303);
exit;
