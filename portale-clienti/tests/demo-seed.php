<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Core.php';
use Portal\Core as C;
if (getenv('PORTAL_DEMO')!=='1') {fwrite(STDERR,"Solo ambiente di prova.\n");exit(1);}
$pdo=C::db();
$demoEmail='presentazione@example.invalid';
$directory=C::config()['storage_path'];
if (!is_dir($directory))mkdir($directory,0700,true);
if (!$pdo->query("SHOW TABLES LIKE 'users'")->fetch()) {
 $schema=str_replace('caniatoa@libero.it',$demoEmail,file_get_contents(dirname(__DIR__).'/sql/schema.sql'));
 foreach (explode("\n",$schema) as $line) { $line=trim($line); if ($line!=='')$pdo->exec($line); }
}
$roleType=$pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
if (!str_contains((string)$roleType['Type'],'operator')) {
 C::exec("UPDATE users SET email='caniatoa@libero.it' WHERE role='admin' AND email='demo-admin@example.invalid'");
 $pdo->exec(file_get_contents(dirname(__DIR__).'/sql/002-operator-role.sql'));
}
$settings=$pdo->query("SHOW TABLES LIKE 'portal_settings'")->fetch();
if (!$settings) $pdo->exec(file_get_contents(dirname(__DIR__).'/sql/003-document-settings.sql'));
// La sostituzione del vincolo è consentita soltanto nella banca dati fittizia Codespaces.
$check=$pdo->query("SHOW CREATE TABLE users")->fetch();
if (str_contains((string)$check['Create Table'],'caniatoa@libero.it')) {
 $pdo->exec('ALTER TABLE users DROP CHECK only_owner_admin');
 C::exec("UPDATE users SET email=? WHERE role='admin'",[$demoEmail]);
 $pdo->exec("ALTER TABLE users ADD CONSTRAINT only_owner_admin CHECK ((role='admin' AND email='presentazione@example.invalid') OR (role<>'admin' AND email<>'presentazione@example.invalid'))");
}
$credentialPath=$directory.'/.demo-admin-login';
$admin=C::row("SELECT id FROM users WHERE role='admin'");
if (!$admin || !is_file($credentialPath)) {
 $password=bin2hex(random_bytes(16));
 if ($admin) {C::exec('UPDATE users SET email=?,password_hash=?,session_version=session_version+1 WHERE id=?',[$demoEmail,password_hash($password,PASSWORD_DEFAULT),$admin['id']]);$adminId=(int)$admin['id'];}
 else {C::exec("INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,'admin')",['Amministratore','Presentazione',$demoEmail,password_hash($password,PASSWORD_DEFAULT)]);$adminId=(int)$pdo->lastInsertId();}
 file_put_contents($credentialPath,"Email: $demoEmail\nPassword: $password\n",LOCK_EX);chmod($credentialPath,0600);
} else $adminId=(int)$admin['id'];
$operator=C::row("SELECT id FROM users WHERE email='operatore@example.invalid'");
if (!$operator) C::exec("INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,'operator')",['Operatore','CA Automazione','operatore@example.invalid',password_hash('DemoAccess2026!',PASSWORD_DEFAULT)]);
$companies=['Azienda dimostrativa','Seconda azienda dimostrativa','Officine Alfa (esempio)','Impianti Beta (esempio)','Servizi Gamma (esempio)'];
$companyIds=[];$plantIds=[];
foreach ($companies as $name) {
 $company=C::row('SELECT id FROM companies WHERE name=?',[$name]);if (!$company){C::exec('INSERT INTO companies(name) VALUES(?)',[$name]);$company=['id'=>$pdo->lastInsertId()];}
 $companyIds[]=(int)$company['id'];$plant=C::row('SELECT id FROM plants WHERE company_id=? ORDER BY id LIMIT 1',[$company['id']]);
 if (!$plant){C::exec('INSERT INTO plants(company_id,name) VALUES(?,?)',[$company['id'],'Impianto principale']);$plant=['id'=>$pdo->lastInsertId()];}
 $plantIds[]=(int)$plant['id'];
}
$names=[['Mario','Rossi'],['Laura','Bianchi'],['Giulia','Verdi'],['Marco','Neri'],['Sara','Conti'],['Paolo','Moretti'],['Elena','Costa'],['Luca','Ferrari'],['Anna','Ricci'],['Davide','Gallo']];
foreach($names as $i=>[$first,$last]) {
 $email=mb_strtolower($first).'@example.invalid';$client=C::row('SELECT id FROM users WHERE email=?',[$email]);
 if (!$client){C::exec("INSERT INTO users(company_id,first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,?,'client')",[$companyIds[$i%5],$first,$last,$email,password_hash('DemoAccess2026!',PASSWORD_DEFAULT)]);$client=['id'=>$pdo->lastInsertId()];}
 $clientId=(int)$client['id'];$plant=$plantIds[$i%5];C::exec('INSERT IGNORE INTO user_plants(user_id,plant_id) VALUES(?,?)',[$clientId,$plant]);
 foreach (['Rapporto tecnico','Verbale di intervento'] as $j=>$kind) {
  $title=$kind.' · '.$first.' '.$last;
  if (C::row('SELECT id FROM documents WHERE recipient_id=? AND title=? AND deleted_at IS NULL',[$clientId,$title]))continue;
  $body="DOCUMENTO DI ESEMPIO - non contiene dati reali\n\n$kind per $first $last\n";
  $key=bin2hex(random_bytes(32));file_put_contents($directory.'/'.$key,$body);chmod($directory.'/'.$key,0600);
  $downloaded=$i<5 && $j===0;$date=gmdate('Y-m-d H:i:s',strtotime('-'.(20-$i-$j).' days'));
  C::exec('INSERT INTO documents(company_id,plant_id,recipient_id,title,original_name,storage_key,mime,bytes,sha256,created_by,created_at,first_download_at,download_count) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[$companyIds[$i%5],$plant,$clientId,$title,'esempio.txt',$key,'text/plain',strlen($body),hash('sha256',$body),$adminId,$date,$downloaded?$date:null,$downloaded?1:0]);
  if ($downloaded)C::exec('INSERT INTO download_events(document_id,user_id,started_at) VALUES(?,?,?)',[$pdo->lastInsertId(),$clientId,$date]);
 }
}
// Le password di prova degli altri due ruoli restano nel file privato del Codespace.
$credentialText=file_get_contents($credentialPath);
foreach ([['Operatore','operatore@example.invalid'],['Cliente','mario@example.invalid']] as [$label,$email]) {
 if(str_contains($credentialText,$label.' email:'))continue;
 $profile=C::row('SELECT id FROM users WHERE email=?',[$email]);if(!$profile)throw new RuntimeException('Profilo di prova mancante: '.$label);
 $password=bin2hex(random_bytes(16));
 C::exec('UPDATE users SET password_hash=?,active=1,deleted_at=NULL,session_version=session_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$profile['id']]);
 file_put_contents($credentialPath,"$label email: $email\n$label password: $password\n",FILE_APPEND|LOCK_EX);
}
chmod($credentialPath,0600);
echo "Dati di presentazione disponibili. Credenziali private: $credentialPath\n";
