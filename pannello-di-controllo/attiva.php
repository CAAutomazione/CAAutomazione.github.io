<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
admin_headers();
try {
    $config = admin_config();
    $db = admin_db();
    $db->query('SELECT id FROM admin_users LIMIT 1');
    http_response_code(404);
    exit('Pagina non disponibile.');
} catch (PDOException $exception) {
    // Prima installazione: la tabella non esiste ancora. Il segreto resta fuori dal sito.
    if (!isset($db)) {
        http_response_code(503);
        exit('Database non disponibile.');
    }
    if ($db->query("SHOW TABLES LIKE 'admin_users'")->fetch()) {
        http_response_code(503);
        exit('Installazione non disponibile.');
    }
} catch (Throwable $exception) {
    http_response_code(503);
    exit('Configurazione non disponibile.');
}
admin_session();
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf();
    $key = is_string($_POST['setup_key'] ?? null) ? $_POST['setup_key'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!hash_equals($config['setup_key'], $key) || strlen($password) < 10 || strlen($password) > 200) {
        $error = 'Controlla la chiave di attivazione e la password (almeno 10 caratteri).';
    } else {
        try {
            $db->exec('CREATE TABLE IF NOT EXISTS admin_users (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, email VARCHAR(254) NOT NULL, password_hash VARCHAR(255) NOT NULL, auth_epoch INT UNSIGNED NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->exec('CREATE TABLE IF NOT EXISTS admin_settings (name VARCHAR(80) NOT NULL PRIMARY KEY, value VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->exec('CREATE TABLE IF NOT EXISTS admin_access_log (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, event VARCHAR(32) NOT NULL, account VARCHAR(254) NOT NULL, ip VARCHAR(45) NOT NULL, user_agent VARCHAR(250) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX ix_event_time (event, created_at), INDEX ix_ip_time (ip, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->exec('CREATE TABLE IF NOT EXISTS admin_reset (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->exec('CREATE TABLE IF NOT EXISTS admin_daily (day DATE NOT NULL PRIMARY KEY, pageviews INT UNSIGNED NOT NULL DEFAULT 0, visitors INT UNSIGNED NOT NULL DEFAULT 0, messages INT UNSIGNED NOT NULL DEFAULT 0, cvs INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->exec('CREATE TABLE IF NOT EXISTS admin_visitor_keys (day DATE NOT NULL, fingerprint CHAR(64) NOT NULL, events TINYINT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (day, fingerprint)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $stmt = $db->prepare('INSERT IGNORE INTO admin_users (id, email, password_hash) VALUES (1, ?, ?)');
            $stmt->execute([ADMIN_EMAIL, password_hash($password, PASSWORD_DEFAULT)]);
            admin_redirect('?attivato=1');
        } catch (Throwable $exception) {
            error_log('Pannello: installazione database non riuscita.');
            $error = 'Impossibile inizializzare il database. Controlla la configurazione MySQL.';
        }
    }
}
$body = '<main class="auth-wrap"><section class="auth-card"><p class="eyebrow">Prima attivazione</p><h1>Attiva il pannello</h1><p>Inserisci la chiave presente nel file privato e scegli la password amministratore. Dopo l’attivazione questa pagina non sarà più utilizzabile.</p>';
if ($error) $body .= '<p class="alert" role="alert">' . admin_h($error) . '</p>';
$body .= '<form method="post"><input type="hidden" name="csrf" value="' . admin_h((string) $_SESSION['csrf']) . '"><label>Chiave di attivazione<input name="setup_key" type="password" autocomplete="off" required></label><label>Password iniziale<input name="password" type="password" autocomplete="new-password" required minlength="10"></label><button class="primary" type="submit">Attiva l’account</button></form></section></main>';
admin_document('Attivazione', $body);
