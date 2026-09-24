<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
// Non emette cookie. Un unico invio breve per pagina; nessun URL completo o query string viene archiviato.
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 256) { http_response_code(413); exit; }
$origin = parse_url((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), PHP_URL_HOST);
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($origin && strtolower($origin) !== $host) { http_response_code(403); exit; }
$path = $_POST['p'] ?? '';
if (!is_string($path) || strlen($path) > 160 || !preg_match('~^/[a-zA-Z0-9_/-]*$~D', $path)) { http_response_code(400); exit; }
try {
    if (admin_setting('analytics_enabled', '0') !== '1') { http_response_code(204); exit; }
    $config = admin_config();
    $ip = admin_ip();
    if ($ip === 'sconosciuto') { http_response_code(204); exit; }
    $day = gmdate('Y-m-d');
    $fingerprint = hash_hmac('sha256', $day . '|' . $ip . '|' . substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200), $config['analytics_key']);
    $db = admin_db();
    $db->beginTransaction();
    $q = $db->prepare('INSERT INTO admin_visitor_keys (day, fingerprint, events) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE events = LEAST(events + 1, 30)');
    $q->execute([$day, $fingerprint]);
    $affected = $q->rowCount();
    if ($affected > 0) {
        $q = $db->prepare('INSERT INTO admin_daily (day, pageviews, visitors) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE pageviews = pageviews + 1, visitors = visitors + ?');
        $unique = $affected === 1 ? 1 : 0;
        $q->execute([$day, $unique, $unique]);
    }
    $db->commit();
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Pannello: conteggio visite non disponibile.');
}
http_response_code(204);
