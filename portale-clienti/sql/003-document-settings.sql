-- Eseguire una sola volta su un database esistente dopo il backup.
ALTER TABLE documents ADD COLUMN download_count INT UNSIGNED NOT NULL DEFAULT 0;
UPDATE documents d SET download_count=(SELECT COUNT(*) FROM download_events e WHERE e.document_id=d.id);
CREATE TABLE IF NOT EXISTS portal_settings (setting_key VARCHAR(40) PRIMARY KEY, value_int INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB;
INSERT IGNORE INTO portal_settings(setting_key,value_int) VALUES ('max_downloads',0),('retention_days',0);
