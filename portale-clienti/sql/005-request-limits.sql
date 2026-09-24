-- Eseguire dopo backup, una sola volta sul database esistente.
CREATE TABLE IF NOT EXISTS request_limits (limit_key BINARY(32) PRIMARY KEY, hits INT UNSIGNED NOT NULL DEFAULT 0, reset_at DATETIME NOT NULL, INDEX(reset_at)) ENGINE=InnoDB;
