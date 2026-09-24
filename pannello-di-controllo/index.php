<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
admin_headers();
admin_session();
try {
    $db = admin_db();
    $user = $db->query('SELECT password_hash, auth_epoch FROM admin_users WHERE id = 1')->fetch();
    if (!$user) throw new RuntimeException('Pannello non attivato.');
} catch (Throwable $exception) {
    error_log('Pannello: configurazione o database non disponibili.');
    http_response_code(503);
    exit('Pannello non disponibile. Completa prima l’attivazione su Tophost.');
}

$error = '';
$notice = isset($_GET['attivato']) ? 'Account attivato. Ora puoi accedere.' : '';
$mode = isset($_GET['reset']) ? 'reset' : (isset($_GET['recupera']) ? 'recover' : 'login');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if ($action === 'logout') {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
        admin_redirect('?uscita=1');
    }
    if ($action === 'login') {
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (admin_throttled('login_failed', 5, 15)) {
            $error = 'Troppi tentativi. Riprova fra 15 minuti.';
        } elseif ($email !== ADMIN_EMAIL || !password_verify($password, $user['password_hash'])) {
            admin_log('login_failed', $email);
            $error = 'Credenziali non valide.';
        } else {
            try {
                admin_mail('Accesso al pannello C.A. Automazione', "Accesso riuscito al pannello di controllo.\nData (UTC): " . gmdate('Y-m-d H:i:s') . "\nIndirizzo IP: " . admin_ip() . "\nBrowser: " . substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250) . "\n\nSe non sei stato tu, cambia subito la password e verifica l'account email.");
                admin_log('login_success');
                session_regenerate_id(true);
                $_SESSION['admin_id'] = 1;
                $_SESSION['auth_epoch'] = (int) $user['auth_epoch'];
                $_SESSION['created'] = $_SESSION['last_seen'] = time();
                admin_redirect();
            } catch (Throwable $exception) {
                error_log('Pannello: notifica email accesso non riuscita.');
                $error = 'Accesso non completato: notifica email non disponibile. Riprova più tardi.';
            }
        }
    } elseif ($action === 'request_reset') {
        $mode = 'recover';
        $notice = 'Se l’indirizzo è quello dell’amministratore, riceverai un messaggio con le istruzioni.';
        if (!admin_throttled('reset_request', 3, 60) && (int) $db->query("SELECT COUNT(*) FROM admin_access_log WHERE event = 'reset_request' AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 MINUTE)")->fetchColumn() < 20) {
            admin_log('reset_request');
            if (is_string($_POST['email'] ?? null) && strtolower(trim($_POST['email'])) === ADMIN_EMAIL) {
                $token = bin2hex(random_bytes(32));
                $db->prepare('REPLACE INTO admin_reset (id, token_hash, expires_at) VALUES (1, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 20 MINUTE))')->execute([hash('sha256', $token)]);
                $link = admin_config()['site_origin'] . '/pannello-di-controllo/?reset=' . $token;
                try {
                    admin_mail('Recupero password del pannello', "Hai richiesto il recupero della password. Apri questo link entro 20 minuti:\n$link\n\nSe non hai fatto la richiesta, ignora questo messaggio. Il link è utilizzabile una sola volta.");
                } catch (Throwable $exception) {
                    $db->exec('DELETE FROM admin_reset WHERE id = 1');
                    error_log('Pannello: email recupero password non inviata.');
                }
            }
        }
    } elseif ($action === 'save_reset') {
        $mode = 'reset';
        $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
        $password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $db->beginTransaction();
        try {
            $row = $db->query('SELECT token_hash, expires_at FROM admin_reset WHERE id = 1 FOR UPDATE')->fetch();
            if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !$row || !hash_equals($row['token_hash'], hash('sha256', $token)) || strtotime($row['expires_at'] . ' UTC') <= time() || strlen($password) < 14 || strlen($password) > 200) {
                $db->rollBack();
                $error = 'Link scaduto o password non valida. Richiedi un nuovo link e scegli almeno 14 caratteri.';
            } else {
                $db->prepare('UPDATE admin_users SET password_hash = ?, auth_epoch = auth_epoch + 1 WHERE id = 1')->execute([password_hash($password, PASSWORD_DEFAULT)]);
                $db->exec('DELETE FROM admin_reset WHERE id = 1');
                $db->commit();
                unset($_SESSION['admin_id']);
                admin_redirect('?password-aggiornata=1');
            }
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    } elseif (admin_authenticated() && $action === 'settings') {
        $analytics = ($_POST['analytics'] ?? '') === '1' ? '1' : '0';
        $forms = ($_POST['forms_paused'] ?? '') === '1' ? '1' : '0';
        $retention = in_array($_POST['retention'] ?? '', ['30', '90', '180'], true) ? $_POST['retention'] : '90';
        admin_save_setting('analytics_enabled', $analytics);
        admin_save_setting('forms_paused', $forms);
        admin_save_setting('retention_days', $retention);
        admin_redirect('?salvato=1');
    } elseif (admin_authenticated() && $action === 'change_password') {
        $old = is_string($_POST['old_password'] ?? null) ? $_POST['old_password'] : '';
        $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        if (!password_verify($old, $user['password_hash']) || strlen($new) < 14 || strlen($new) > 200 || hash_equals($old, $new)) {
            $error = 'Controlla la password attuale. La nuova deve essere diversa e avere almeno 14 caratteri.';
        } else {
            $db->prepare('UPDATE admin_users SET password_hash = ?, auth_epoch = auth_epoch + 1 WHERE id = 1')->execute([password_hash($new, PASSWORD_DEFAULT)]);
            $db->exec('DELETE FROM admin_reset WHERE id = 1');
            unset($_SESSION['admin_id']);
            admin_redirect('?password-aggiornata=1');
        }
    }
}

if (!admin_authenticated()) {
    if (isset($_GET['password-aggiornata'])) $notice = 'Password aggiornata. Accedi con quella nuova.';
    if (isset($_GET['uscita'])) $notice = 'Sessione terminata.';
    $body = '<main class="auth-wrap"><section class="auth-card"><p class="eyebrow">Accesso riservato</p>';
    if ($mode === 'recover') {
        $body .= '<h1>Recupera la password</h1><p>Se riconosciamo l’indirizzo, invieremo un link utilizzabile una sola volta per 20 minuti.</p>';
    } elseif ($mode === 'reset') {
        $body .= '<h1>Imposta una nuova password</h1><p>Usa almeno 14 caratteri. Dopo il cambio tutte le sessioni precedenti saranno invalidate.</p>';
    } else {
        $body .= '<h1>Accedi al pannello</h1><p>Gestisci statistiche, invii e impostazioni del sito da un’unica area riservata.</p>';
    }
    if ($notice) $body .= '<p class="success" role="status">' . admin_h($notice) . '</p>';
    if ($error) $body .= '<p class="alert" role="alert">' . admin_h($error) . '</p>';
    $body .= '<form method="post"><input type="hidden" name="csrf" value="' . admin_h((string) $_SESSION['csrf']) . '">';
    if ($mode === 'reset') {
        $currentToken = is_string($_GET['reset'] ?? null) ? $_GET['reset'] : (is_string($_POST['token'] ?? null) ? $_POST['token'] : '');
        $body .= '<input type="hidden" name="token" value="' . admin_h($currentToken) . '"><label>Nuova password<input type="password" name="new_password" autocomplete="new-password" minlength="14" required></label><button class="primary" name="action" value="save_reset">Salva la nuova password</button>';
    } else {
        $body .= '<label>Email<input type="email" name="email" autocomplete="username" required></label>';
        if ($mode !== 'recover') $body .= '<label>Password<input type="password" name="password" autocomplete="current-password" required></label>';
        $body .= '<div class="auth-actions"><button class="primary" name="action" value="' . ($mode === 'recover' ? 'request_reset' : 'login') . '">' . ($mode === 'recover' ? 'Invia il link' : 'Accedi') . '</button><a class="secondary" href="' . ($mode === 'recover' ? '/pannello-di-controllo/' : '?recupera=1') . '">' . ($mode === 'recover' ? 'Torna all’accesso' : 'Password dimenticata?') . '</a></div>';
    }
    $body .= '</form></section></main>';
    admin_document('Accesso riservato', $body);
    exit;
}

$retention = (int) admin_setting('retention_days', '90');
$db->exec('DELETE FROM admin_access_log WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $retention . ' DAY)');
$db->exec('DELETE FROM admin_visitor_keys WHERE day < DATE_SUB(UTC_DATE(), INTERVAL 2 DAY)');
$db->exec('DELETE FROM admin_daily WHERE day < DATE_SUB(UTC_DATE(), INTERVAL ' . $retention . ' DAY)');
$totals = $db->query('SELECT COALESCE(SUM(pageviews),0) views, COALESCE(SUM(visitors),0) visitors, COALESCE(SUM(messages),0) messages, COALESCE(SUM(cvs),0) cvs FROM admin_daily WHERE day >= DATE_SUB(UTC_DATE(), INTERVAL 29 DAY)')->fetch();
$days = $db->query('SELECT day, pageviews, visitors, messages, cvs FROM admin_daily WHERE day >= DATE_SUB(UTC_DATE(), INTERVAL 13 DAY) ORDER BY day ASC')->fetchAll();
$daily = [];
foreach ($days as $row) $daily[$row['day']] = $row;
$chart = [];
for ($i = 13; $i >= 0; --$i) {
    $date = gmdate('Y-m-d', strtotime("-$i days"));
    $chart[] = $daily[$date] ?? ['day' => $date, 'pageviews' => 0, 'visitors' => 0, 'messages' => 0, 'cvs' => 0];
}
$max = max(1, ...array_map(static fn($d) => (int) $d['visitors'], $chart));
$logs = $db->query('SELECT event, account, ip, user_agent, created_at FROM admin_access_log WHERE event IN (\'login_success\',\'login_failed\') ORDER BY id DESC LIMIT 50')->fetchAll();
$csrf = admin_h((string) $_SESSION['csrf']);
$body = '<main class="dashboard"><div class="dashboard-intro"><div><p class="eyebrow">Area amministratore</p><h1>Panoramica del sito</h1><p>Dati degli ultimi 30 giorni, aggiornati quando il sito è ospitato su Tophost.</p></div><span class="user-chip">' . admin_h(ADMIN_EMAIL) . '</span></div>';
if (isset($_GET['salvato'])) $body .= '<p class="success" role="status">Impostazioni salvate.</p>';
if ($error) $body .= '<p class="alert" role="alert">' . admin_h($error) . '</p>';
$cards = [['Visite stimate', 'visitors'], ['Pagine visualizzate', 'views'], ['Richieste ricevute', 'messages'], ['CV ricevuti', 'cvs']];
$body .= '<div class="stat-grid">';
foreach ($cards as [$label, $field]) $body .= '<article class="stat-card"><span>' . admin_h($label) . '</span><strong>' . number_format((int) $totals[$field], 0, ',', '.') . '</strong><small>Ultimi 30 giorni</small></article>';
$body .= '</div><div class="dashboard-columns"><section class="panel chart-panel"><div class="panel-heading"><div><p class="eyebrow">Andamento</p><h2>Visite giornaliere</h2></div><span>Ultimi 14 giorni · UTC</span></div><div class="chart" role="img" aria-label="Grafico delle visite stimate negli ultimi 14 giorni">';
foreach ($chart as $d) {
    $height = max(3, (int) round(100 * (int) $d['visitors'] / $max));
    $body .= '<div class="bar-col" title="' . admin_h($d['day']) . ': ' . (int) $d['visitors'] . ' visite"><strong>' . (int) $d['visitors'] . '</strong><span class="bar" style="height:' . $height . '%"></span><small>' . admin_h(substr($d['day'], 8, 2)) . '</small></div>';
}
$body .= '</div><p class="chart-note">Le visite sono una stima giornaliera ricavata da segnali tecnici del server; dispositivi condivisi e reti aziendali possono alterarla. Non identifica persone né include le visite precedenti all’attivazione.</p></section><section class="panel"><p class="eyebrow">Invii</p><h2>Contatti e candidature</h2><p>I contatori aumentano dopo che il server email ha accettato l’invio. In caso di errore del database, un’email può arrivare senza essere conteggiata.</p><div class="mini-stat"><span>Richieste</span><strong>' . (int) $totals['messages'] . '</strong></div><div class="mini-stat"><span>Curriculum</span><strong>' . (int) $totals['cvs'] . '</strong></div><a class="text-link" href="/contatti/" target="_blank" rel="noopener">Apri la pagina contatti ↗</a></section></div>';
$body .= '<div class="dashboard-columns"><section class="panel" id="impostazioni"><p class="eyebrow">Configurazione</p><h2>Impostazioni del sito</h2><form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><label class="check"><input type="checkbox" name="analytics" value="1"' . (admin_setting('analytics_enabled', '0') === '1' ? ' checked' : '') . '><span><strong>Conteggio delle visite</strong><small>Attivalo dopo aver completato l’informativa privacy. Registra conteggi aggregati senza cookie sul dispositivo.</small></span></label><label class="check"><input type="checkbox" name="forms_paused" value="1"' . (admin_setting('forms_paused', '0') === '1' ? ' checked' : '') . '><span><strong>Sospendi gli invii dai moduli</strong><small>Le richieste al server vengono fermate. La pagina continua a mostrare i moduli.</small></span></label><label>Conservazione dei conteggi e del registro<select name="retention"><option value="30"' . ($retention === 30 ? ' selected' : '') . '>30 giorni</option><option value="90"' . ($retention === 90 ? ' selected' : '') . '>90 giorni</option><option value="180"' . ($retention === 180 ? ' selected' : '') . '>180 giorni</option></select></label><button class="primary" name="action" value="settings">Salva impostazioni</button></form></section><section class="panel"><p class="eyebrow">Protezione account</p><h2>Cambia la password</h2><p>Ogni accesso riuscito invia un’email di avviso. Se l’email non parte, l’accesso viene negato. Una modifica della password termina le sessioni precedenti.</p><form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><label>Password attuale<input type="password" name="old_password" autocomplete="current-password" required></label><label>Nuova password, almeno 14 caratteri<input type="password" name="new_password" autocomplete="new-password" minlength="14" required></label><button class="primary" name="action" value="change_password">Aggiorna password</button></form></section></div>';
$body .= '<section class="panel access-panel"><div class="panel-heading"><div><p class="eyebrow">Sicurezza</p><h2>Registro degli accessi</h2></div><span>Ultimi 50 tentativi · UTC</span></div><div class="table-scroll"><table><thead><tr><th>Data e ora</th><th>Esito</th><th>Account</th><th>Indirizzo IP</th><th>Browser dichiarato</th></tr></thead><tbody>';
foreach ($logs as $entry) $body .= '<tr><td>' . admin_h($entry['created_at']) . '</td><td><span class="badge ' . ($entry['event'] === 'login_success' ? 'ok' : 'bad') . '">' . ($entry['event'] === 'login_success' ? 'Riuscito' : 'Respinto') . '</span></td><td>' . admin_h($entry['account']) . '</td><td>' . admin_h($entry['ip']) . '</td><td class="ua">' . admin_h($entry['user_agent']) . '</td></tr>';
if (!$logs) $body .= '<tr><td colspan="5">Nessun tentativo registrato.</td></tr>';
$body .= '</tbody></table></div><p class="chart-note">L’indirizzo IP e l’identificazione del browser sono dati tecnici; non indicano con certezza la persona che ha effettuato il tentativo.</p></section></main>';
admin_document('Dashboard', $body, true);
