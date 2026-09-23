<?php
return [
 'db_dsn' => 'mysql:host=localhost;dbname=area_clienti;charset=utf8mb4',
 'db_user' => 'CHANGE_ME', 'db_password' => 'CHANGE_ME',
 'base_url' => 'https://clienti.caniatoautomazione.com',
 'storage_path' => dirname(__DIR__) . '/private-documents', // outside public/ and not web-accessible
 'max_upload_bytes' => 30 * 1024 * 1024,
 'capacity_bytes' => null, // confirmed usable quota for this app; null disables quota alerts
 'alert_remaining_bytes' => 2 * 1024 * 1024 * 1024,
 'admin_email' => 'CHANGE_ME', 'mail_from' => 'CHANGE_ME',
 'smtp_host' => 'CHANGE_ME', 'smtp_port' => 587,
 'smtp_user' => 'CHANGE_ME', 'smtp_password' => 'CHANGE_ME',
 'cron_secret' => 'CHANGE_TO_LONG_RANDOM_SECRET',
];
