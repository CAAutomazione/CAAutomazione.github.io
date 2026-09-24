-- Eseguire una sola volta sul database esistente, dopo un backup.
-- Verificare prima che vi sia al massimo un amministratore e che il suo indirizzo sia quello indicato.
-- L'ALTER fallisce senza modifiche se il vincolo non è soddisfatto.
ALTER TABLE users
 MODIFY role ENUM('admin','operator','client') NOT NULL,
 ADD COLUMN admin_slot TINYINT GENERATED ALWAYS AS (CASE WHEN role='admin' THEN 1 ELSE NULL END) STORED,
 ADD UNIQUE KEY one_admin (admin_slot),
 ADD CONSTRAINT only_owner_admin CHECK ((role='admin' AND email='caniatoa@libero.it') OR (role<>'admin' AND email<>'caniatoa@libero.it'));
