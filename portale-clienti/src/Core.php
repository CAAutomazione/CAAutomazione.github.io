<?php
namespace Portal;
use PDO;
use RuntimeException;
final class Core {
 public static function config(): array {static $c; return $c ??= require dirname(__DIR__).'/config.php';}
 public static function db(): PDO {static $db; if (!$db) {$c=self::config();$db=new PDO($c['db_dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$db->exec("SET time_zone = '+00:00'");}return $db;}
 public static function row(string $sql,array $args=[]): array|false {$q=self::db()->prepare($sql);$q->execute($args);return $q->fetch();}
 public static function all(string $sql,array $args=[]): array {$q=self::db()->prepare($sql);$q->execute($args);return $q->fetchAll();}
 public static function exec(string $sql,array $args=[]): int {$q=self::db()->prepare($sql);$q->execute($args);return $q->rowCount();}
 public static function h(?string $v): string {return htmlspecialchars($v??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
 public static function session(): void {if(session_status()===PHP_SESSION_ACTIVE)return; if (PHP_SAPI !== 'cli' && empty($_SERVER['HTTPS']) && !(self::config()['allow_http_preview']??false) && !in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)) throw new RuntimeException('HTTPS richiesto');session_name('ca_portal');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);session_start();}
 public static function user(): array|false {self::session();if(empty($_SESSION['uid']))return false;$u=self::row('SELECT id,company_id,first_name,last_name,email,role,active,session_version,deleted_at FROM users WHERE id=?',[(int)$_SESSION['uid']]);if(!$u||!$u['active']||$u['deleted_at']!==null||(int)$u['session_version']!==(int)($_SESSION['session_version']??-1)){self::logout();return false;}if(isset($_SESSION['impersonator_uid'])){$admin=self::row("SELECT id,active,deleted_at,session_version FROM users WHERE id=? AND role='admin'",[(int)$_SESSION['impersonator_uid']]);if(!$admin||!$admin['active']||$admin['deleted_at']!==null||(int)$admin['session_version']!==(int)($_SESSION['impersonator_version']??-1)){self::logout();return false;}}return $u;}
 public static function requireUser(?string $role=null): array {$u=self::user();if(!$u){header('Location: /?login=1');exit;}if($role!==null && $u['role']!==$role){http_response_code(403);exit('Accesso negato');}return $u;}
 public static function csrf(): string {self::session();return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
 public static function checkCsrf(): void {self::session();if(!hash_equals(self::csrf(),(string)($_POST['_csrf']??''))){http_response_code(403);exit('Richiesta non valida');}}
 public static function logout(): void {self::session();$_SESSION=[];session_regenerate_id(true);}
 public static function go(string $path='/'): never {header('Location: '.$path, true,303);exit;}
 public static function queue(string $kind,string $to,string $subject,string $body,string $key): void {self::exec('INSERT IGNORE INTO notification_queue(kind,recipient_email,subject,body,dedupe_key) VALUES(?,?,?,?,?)',[$kind,$to,$subject,$body,$key]);}
 public static function audit(int $actor,string $action,?int $id=null): void {self::exec('INSERT INTO audit_events(actor_id,action,subject_id) VALUES(?,?,?)',[$actor,$action,$id]);}
 public static function date(string $utc): string {return (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Rome'))->format('d/m/Y H:i');}
}
