<?php
declare(strict_types=1);

const ADMIN_EMAIL = 'caniatoa@libero.it';

function admin_config(): array
{
    static $config;
    if ($config !== null) return $config;
    $path = dirname(__DIR__, 2) . '/admin-config.php';
    if (!is_file($path)) throw new RuntimeException('Configurazione amministrativa mancante.');
    $config = require $path;
    if (!is_array($config)) throw new RuntimeException('Configurazione amministrativa non valida.');
    foreach (['db_host', 'db_name', 'db_user', 'db_password', 'site_origin', 'analytics_key', 'setup_key'] as $key) {
        if (!is_string($config[$key] ?? null) || $config[$key] === '' || str_contains($config[$key], 'GENERARE_') || str_contains($config[$key], 'DATABASE') || str_contains($config[$key], 'HOST_MYSQL')) {
            throw new RuntimeException('Configurazione amministrativa incompleta.');
        }
    }
    if (strlen($config['analytics_key']) < 32 || strlen($config['setup_key']) < 32 || !preg_match('~^https://[a-z0-9.-]+$~i', $config['site_origin'])) {
        throw new RuntimeException('Chiavi o indirizzo del sito non validi.');
    }
    return $config;
}

function admin_db(): PDO
{
    static $db;
    if ($db !== null) return $db;
    $c = admin_config();
    $db = new PDO('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec("SET time_zone = '+00:00'");
    return $db;
}

function admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        http_response_code(403);
        exit('È necessaria una connessione HTTPS.');
    }
    session_name('ca_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/pannello-di-controllo/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
    if (!isset($_SESSION['created'])) $_SESSION['created'] = time();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function admin_headers(): void
{
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
}

function admin_csrf(): void
{
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $_POST['csrf'])) {
        http_response_code(403);
        exit('Richiesta non valida.');
    }
}

function admin_h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_redirect(string $suffix = ''): never
{
    header('Location: /pannello-di-controllo/' . $suffix, true, 303);
    exit;
}

function admin_authenticated(): bool
{
    if (empty($_SESSION['admin_id']) || empty($_SESSION['last_seen'])) return false;
    if (time() - (int) $_SESSION['last_seen'] > 1800 || time() - (int) $_SESSION['created'] > 43200) {
        unset($_SESSION['admin_id']);
        return false;
    }
    $stmt = admin_db()->query('SELECT auth_epoch FROM admin_users WHERE id = 1');
    if ((int) $stmt->fetchColumn() !== (int) ($_SESSION['auth_epoch'] ?? 0)) {
        unset($_SESSION['admin_id']);
        return false;
    }
    $_SESSION['last_seen'] = time();
    return true;
}

function admin_require_auth(): void
{
    if (!admin_authenticated()) admin_redirect();
}

function admin_setting(string $key, string $default): string
{
    $stmt = admin_db()->prepare('SELECT value FROM admin_settings WHERE name = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : $default;
}

function admin_save_setting(string $key, string $value): void
{
    $stmt = admin_db()->prepare('INSERT INTO admin_settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute([$key, $value]);
}

function admin_ip(): string
{
    // Non fidarsi degli header X-Forwarded-For forniti dal visitatore.
    return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'sconosciuto';
}

function admin_log(string $outcome, string $account = ADMIN_EMAIL): void
{
    $stmt = admin_db()->prepare('INSERT INTO admin_access_log (event, account, ip, user_agent) VALUES (?, ?, ?, ?)');
    $stmt->execute([$outcome, substr($account, 0, 254), admin_ip(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250)]);
}

function admin_throttled(string $event, int $maximum, int $minutes): bool
{
    $stmt = admin_db()->prepare('SELECT COUNT(*) FROM admin_access_log WHERE event = ? AND ip = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)');
    $stmt->execute([$event, admin_ip(), $minutes]);
    return (int) $stmt->fetchColumn() >= $maximum;
}

function admin_mail(string $subject, string $body): void
{
    $configPath = dirname(__DIR__, 2) . '/contact-config.php';
    if (!is_file($configPath)) throw new RuntimeException('SMTP non configurato.');
    $config = require $configPath;
    if (!is_array($config) || empty($config['smtp_host']) || empty($config['smtp_user']) || empty($config['smtp_password'])) throw new RuntimeException('SMTP non configurato.');
    require_once dirname(__DIR__) . '/vendor/phpmailer/Exception.php';
    require_once dirname(__DIR__) . '/vendor/phpmailer/PHPMailer.php';
    require_once dirname(__DIR__) . '/vendor/phpmailer/SMTP.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->Port = (int) ($config['smtp_port'] ?? 587);
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout = 12;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_password'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['smtp_user'], 'C.A. Automazione - Pannello');
    $mail->addAddress(ADMIN_EMAIL);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->send();
}

function admin_document(string $title, string $body, bool $logged = false): void
{
    $nav = $logged ? '<nav class="top-actions"><a href="/" target="_blank" rel="noopener">Apri il sito ↗</a><form method="post"><input type="hidden" name="csrf" value="' . admin_h((string) $_SESSION['csrf']) . '"><button name="action" value="logout">Esci</button></form></nav>' : '<a class="back-home" href="/">← Torna al sito</a>';
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . admin_h($title) . ' | C.A. Automazione</title><link rel="icon" href="/favicon.png"><link rel="stylesheet" href="/pannello-di-controllo/dashboard.css?v=1"></head><body><header class="admin-header"><a class="admin-brand" href="/pannello-di-controllo/"><img src="/images/brand/logo-square.png" alt="" width="46" height="46"><span><strong>C.A. Automazione</strong><small>Pannello di controllo</small></span></a>' . $nav . '</header>' . $body . '</body></html>';
}
