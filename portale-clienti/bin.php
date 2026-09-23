<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/vendor/autoload.php';
use Portal\Core as C;
$cmd=$argv[1]??'';
if($cmd==='admin'){$first=$argv[2]??'';$last=$argv[3]??'';$email=$argv[4]??'';if(!$first||!$last||!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Uso: php bin.php admin Nome Cognome email\n");exit(2);}fwrite(STDOUT,"Password iniziale (minimo 12 caratteri): ");$password=trim(fgets(STDIN));if(strlen($password)<12)exit(2);C::exec("INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,'admin')",[$first,$last,strtolower($email),password_hash($password,PASSWORD_DEFAULT)]);echo "Account amministratore creato.\n";exit;}
if($cmd==='jobs'){\Portal\Notifications::quota();\Portal\Notifications::sendPending();echo "Controlli completati.\n";exit;}
fwrite(STDERR,"Uso: php bin.php admin Nome Cognome email | jobs\n");exit(2);
