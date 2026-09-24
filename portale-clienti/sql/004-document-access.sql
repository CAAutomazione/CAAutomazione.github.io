-- Eseguire una sola volta su un database esistente, dopo un backup.
-- Conserva per i documenti già presenti i limiti attualmente in vigore.
ALTER TABLE documents ADD COLUMN max_downloads INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN retention_days INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN manual_access TINYINT(1) NULL DEFAULT NULL;
UPDATE documents SET max_downloads=COALESCE((SELECT value_int FROM portal_settings WHERE setting_key='max_downloads'),0), retention_days=COALESCE((SELECT value_int FROM portal_settings WHERE setting_key='retention_days'),0);
