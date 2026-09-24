<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Core.php';
use Portal\Core as C;
if (getenv('PORTAL_DEMO') !== '1') {fwrite(STDERR,"Solo ambiente dimostrativo.\n");exit(1);}
$pdo=C::db();
if (!$pdo->query("SHOW TABLES LIKE 'users'")->fetch()) {
 foreach (explode("\n",file_get_contents(dirname(__DIR__).'/sql/schema.sql')) as $line) {
  $line=trim($line);if ($line!=='')$pdo->exec($line);
 }
}
$roleType=$pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
if (!str_contains((string)$roleType['Type'], 'operator')) {
 // Solo l'ambiente di prova: conserva account e documenti già creati.
 C::exec("UPDATE users SET email='caniatoa@libero.it' WHERE role='admin' AND email='demo-admin@example.invalid'");
 $pdo->exec(file_get_contents(dirname(__DIR__).'/sql/002-operator-role.sql'));
}
if ((int)C::row('SELECT COUNT(*) n FROM users')['n']>0) {
 if (!C::row("SELECT id FROM users WHERE email='operatore@example.invalid'")) C::exec("INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,'operator')",['Operatore','CA Automazione','operatore@example.invalid',password_hash('DemoAccess2026!',PASSWORD_DEFAULT)]);
 echo "Dati dimostrativi già presenti.\n";exit;
}
C::exec('INSERT INTO companies(name) VALUES(?)',['Azienda dimostrativa']);$company=(int)$pdo->lastInsertId();
C::exec('INSERT INTO companies(name) VALUES(?)',['Seconda azienda dimostrativa']);$otherCompany=(int)$pdo->lastInsertId();
C::exec('INSERT INTO plants(company_id,name) VALUES(?,?)',[$company,'Impianto Nord']);$plant=(int)$pdo->lastInsertId();
C::exec('INSERT INTO plants(company_id,name) VALUES(?,?)',[$company,'Impianto Sud']);
C::exec('INSERT INTO plants(company_id,name) VALUES(?,?)',[$otherCompany,'Impianto Produzione']);$otherPlant=(int)$pdo->lastInsertId();
$hash=password_hash('DemoAccess2026!',PASSWORD_DEFAULT);
C::exec("INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,'admin')",['CA','Automazione','caniatoa@libero.it',$hash]);$admin=(int)$pdo->lastInsertId();
C::exec("INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,'operator')",['Operatore','CA Automazione','operatore@example.invalid',$hash]);
C::exec("INSERT INTO users(company_id,first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,?,'client')",[$company,'Mario','Rossi','mario@example.invalid',$hash]);$mario=(int)$pdo->lastInsertId();
C::exec('INSERT INTO user_plants(user_id,plant_id) VALUES(?,?)',[$mario,$plant]);
C::exec("INSERT INTO users(company_id,first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,?,'client')",[$otherCompany,'Laura','Bianchi','laura@example.invalid',$hash]);$laura=(int)$pdo->lastInsertId();
C::exec('INSERT INTO user_plants(user_id,plant_id) VALUES(?,?)',[$laura,$otherPlant]);
foreach ([['Rapporto di taratura dimostrativo','Rapporto di taratura - dati fittizi',null],['Verbale intervento dimostrativo','Verbale intervento - dati fittizi',gmdate('Y-m-d H:i:s')]] as [$title,$body,$download]) {
 $key=bin2hex(random_bytes(32));file_put_contents('/private-documents/'.$key,$body);
 C::exec('INSERT INTO documents(company_id,plant_id,recipient_id,title,original_name,storage_key,mime,bytes,sha256,created_by,first_download_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$company,$plant,$mario,$title,'documento-demo.txt',$key,'text/plain',strlen($body),hash('sha256',$body),$admin,$download]);
}
echo "Demo pronta: account dimostrativi nel README.\n";
