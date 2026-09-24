<?php
// Copiare come admin-config.php NELLA CARTELLA SOPRA htdocs. Mai caricare il file reale su GitHub.
// Creare il database MySQL nel pannello Tophost; usare un account con accesso solo a quel database.
return [
    'db_host' => 'HOST_MYSQL_DAL_PANNELLO',
    'db_name' => 'NOME_DATABASE',
    'db_user' => 'UTENTE_DATABASE',
    'db_password' => 'PASSWORD_DATABASE',
    'site_origin' => 'https://www.caniatoautomazione.com',
    'analytics_key' => 'GENERARE_UNA_CHIAVE_CASUALE_DI_ALMENO_32_CARATTERI',
    'setup_key' => 'GENERARE_UN_ALTRA_CHIAVE_CASUALE_DI_ALMENO_32_CARATTERI',
];
