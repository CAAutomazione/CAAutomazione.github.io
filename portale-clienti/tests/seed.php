<?php
require dirname(__DIR__).'/src/Core.php';
use Portal\Core as C;
$pdo=C::db();foreach(explode("\n",file_get_contents(dirname(__DIR__).'/sql/schema.sql')) as $line){$line=trim($line);if($line!=='')$pdo->exec($line);}
$dir=C::config()['storage_path'];mkdir($dir,0700,true);
C::exec('INSERT INTO companies(name) VALUES(?)',['Azienda A']);$a=(int)$pdo->lastInsertId();C::exec('INSERT INTO companies(name) VALUES(?)',['Azienda B']);$b=(int)$pdo->lastInsertId();
C::exec('INSERT INTO plants(company_id,name) VALUES(?,?)',[$a,'Impianto A']);$pa=(int)$pdo->lastInsertId();C::exec('INSERT INTO plants(company_id,name) VALUES(?,?)',[$b,'Impianto B']);$pb=(int)$pdo->lastInsertId();C::exec('INSERT INTO plants(company_id,name) VALUES(?,?)',[$a,'Impianto A2']);$pa2=(int)$pdo->lastInsertId();
foreach([[$a,'Alice','Cliente','alice@example.invalid','client',$pa],[$b,'Bob','Cliente','bob@example.invalid','client',$pb],[$a,'Carla','Cliente','carla@example.invalid','client',$pa2]] as [$company,$first,$last,$email,$role,$plant]){C::exec('INSERT INTO users(company_id,first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,?,?)',[$company,$first,$last,$email,password_hash('test-password-1234',PASSWORD_DEFAULT),$role]);$id=(int)$pdo->lastInsertId();C::exec('INSERT INTO user_plants(user_id,plant_id) VALUES(?,?)',[$id,$plant]);if($email==='alice@example.invalid')$alice=$id;}
$key=bin2hex(random_bytes(32));file_put_contents($dir.'/'.$key,'private test document');C::exec('INSERT INTO documents(company_id,plant_id,recipient_id,title,original_name,storage_key,mime,bytes,sha256,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',[$a,$pa,$alice,'Documento Alice','alice.txt',$key,'text/plain',21,hash('sha256','private test document'),$alice]);echo $pdo->lastInsertId();
