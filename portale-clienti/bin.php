<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/vendor/autoload.php';
use Portal\Core as C;
$cmd=$argv[1]??'';
if($cmd==='admin'){$first=$argv[2]??'';$last=$argv[3]??'';$email=$argv[4]??'';if(!$first||!$last||!filter_var($email,FILTER_VALIDATE_EMAIL)||strcasecmp($email,(string)C::config()['admin_email'])!==0){fwrite(STDERR,"Uso: php bin.php admin Nome Cognome email\n");exit(2);}fwrite(STDOUT,"Password iniziale (minimo 12 caratteri): ");$hidden=function_exists("shell_exec") && function_exists("exec") && stream_isatty(STDIN);if($hidden)@exec("stty -echo");try{$password=rtrim((string)fgets(STDIN),"\r\n");}finally{if($hidden){@exec("stty echo");fwrite(STDOUT,"\n");}}if(strlen($password)<12){fwrite(STDERR,"La password deve contenere almeno 12 caratteri.\n");exit(2);}C::exec("INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,'admin')",[$first,$last,strtolower($email),password_hash($password,PASSWORD_DEFAULT)]);echo "Account amministratore creato.\n";exit;}
if($cmd==='jobs'){C::exec('DELETE FROM login_attempts WHERE occurred_at<UTC_TIMESTAMP()-INTERVAL 2 DAY');C::exec('DELETE FROM password_reset_tokens WHERE created_at<UTC_TIMESTAMP()-INTERVAL 2 DAY');C::exec('DELETE FROM request_limits WHERE reset_at<UTC_TIMESTAMP()-INTERVAL 1 DAY');\Portal\Notifications::quota();\Portal\Notifications::sendPending();echo "Controlli completati.\n";exit;}
fwrite(STDERR,"Uso: php bin.php admin Nome Cognome email | jobs\n");exit(2);
