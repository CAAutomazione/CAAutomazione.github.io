# Prova immediata

Apri [PROVA.md](PROVA.md) per avviare l’ambiente dimostrativo su GitHub con account e documenti fittizi.

# Portale Area Clienti — prima versione implementata

Applicazione **separata dal sito GitHub Pages**. Non mettere documenti, database o `config.php` nel repository pubblico. Il prototipo richiede PHP 8.2+, MySQL 8, estensioni PDO MySQL, mbstring e fileinfo, Composer e SMTP. Richiede configurazione e collaudo su server prima dell'uso con dati reali.

## Installazione su hosting di prova

1. Creare un database vuoto e importare `sql/schema.sql`.
2. Eseguire `composer install --no-dev` nella cartella dell'applicazione.
3. Copiare `config.example.php` in `config.php`, compilare credenziali e indirizzi. Impostare `site_url` sull’indirizzo pubblico del sito e `privacy_url` sull’informativa pubblicata sul dominio definitivo: il pulsante «Torna al sito internet» e il collegamento alla privacy useranno questi valori anche su Tophost. Non pubblicare il file di configurazione.
4. Configurare la document root del portale su **`public/`**, mai sulla cartella del progetto. Creare la cartella indicata da `storage_path` **fuori dalla directory web**, scrivibile dall'utente PHP. Se non si può impostare questa separazione, non pubblicare il portale.
5. Attivare HTTPS. Creare il primo amministratore da CLI: `php bin.php admin CA Automazione caniatoa@libero.it`; inserire al prompt una password nuova di almeno 12 caratteri. La password non va inserita nei file, nel repository o nella riga di comando. Il comando richiede un database Tophost configurato e accesso PHP da terminale; se l’hosting non lo offre, occorre predisporre una procedura di attivazione privata prima del rilascio.
6. Configurare un cron che esegua `php /percorso/portale-clienti/bin.php jobs` ogni 5 minuti. Se Tophost non offre CLI/cron, occorre un metodo alternativo protetto da verificare prima dell'uso; non rendere pubblico `bin.php`.
7. Verificare SMTP, limiti PHP di upload, dimensione effettiva disponibile, log e backup. Impostare `capacity_bytes` alla quota **realmente disponibile per questo archivio**, non automaticamente ai 50 GB commerciali. Con `null` l'allerta spazio è disattivata.

## Funzioni della prima versione

- Tre ruoli con password hash, sessione e CSRF: amministratore, operatore CA Automazione e cliente. Solo `caniatoa@libero.it` può avere ruolo amministratore; il database impedisce un secondo amministratore.
- Creazione aziende, impianti e account personali; modifica nome/email, aggiunta e rimozione impianti autorizzati, disattivazione e reimpostazione password. Recupero autonomo password tramite link monouso valido 30 minuti; cambio password dall’account e invalidazione delle sessioni precedenti.
- Upload di un file per un destinatario identificato con nome, cognome ed email e un impianto. File archiviati con nome casuale fuori dal web.
- Lista personale dei documenti da scaricare e archivio dopo il primo avvio di download. Anteprima e download richiedono autorizzazione lato server; cliente senza azioni di modifica.
- Log dei download e coda email per avviso di consegna, avviso all'amministratore e soglia residua di 2 GiB.
- Lista amministrativa per data, eliminazione multipla con conferma, modifica del titolo; la rimozione dei documenti avviene dall’archivio file.

## Limiti da risolvere prima del rilascio

- **Non è un portale pronto alla produzione.** Mancano invito iniziale senza password comunicata a parte, modifica dell’azienda di un account, gestione della conservazione e recupero dopo eliminazione, misurazione della quota reale del piano e test di integrazione su Tophost.
- La password iniziale è inserita dall'amministratore e va consegnata con canale separato e sicuro. Non inviarla per email in chiaro.
- La notifica di download attesta l'avvio del trasferimento dal server, non che il browser abbia salvato l'intero file.
- Il conteggio della quota attualmente usa la somma dei file attivi di questa applicazione; occorre integrare la misura di spazio reale di Tophost se la quota include anche sito, posta o altri contenuti.
- L'eliminazione attuale è immediata e singola; definire recupero/retention e backup prima di usare documenti reali.
- Caricare solo i tipi consentiti dal codice; dimensione massima predefinita 30 MiB, da allineare a `upload_max_filesize` e `post_max_size`.

## Account, privacy e accessi

La schermata amministrativa mostra nome, email, azienda, impianti, stato e ultimo accesso; lo storico registra data, indirizzo IP visto dal server e user agent. L'IP **non prova la posizione geografica**. Definire nella privacy policy la conservazione dei log e limitare l'accesso ai soli amministratori.

Le password sono memorizzate solo come hash e non possono essere lette. L'amministratore può reimpostarle e usare **Entra come cliente** per consultare la stessa vista, con evento di audit. In tale modalità il download è disattivato, così l'amministratore non genera falsi eventi attribuiti al cliente. L'account cliente può essere rimosso: l'accesso e le sessioni cessano, mentre i record storici restano per la politica di conservazione ancora da definire.

Alla prima autenticazione viene richiesta la presa visione dell'informativa privacy. Se l'utente spunta **Ricorda la presa visione**, l'accettazione e la versione dell'informativa vengono registrate nel database e la richiesta non si ripete finché la versione resta la stessa. La versione va aggiornata in `config.php` quando cambia l'informativa. È una conferma di lettura, non un consenso generico al trattamento.

## Tre livelli di accesso

| Ruolo | Permessi |
| --- | --- |
| Amministratore (`caniatoa@libero.it`) | Gestisce clienti, aziende, impianti, operatori, file, accessi e impostazioni. È l’unico account amministratore. |
| Operatore CA Automazione | Carica e invia file ai clienti esistenti; consulta, rinomina ed elimina documenti. Può cambiare la propria password. Non gestisce persone, aziende o impianti. |
| Cliente | Consulta e scarica solo i documenti assegnati personalmente, accede al proprio archivio e cambia la propria password. |

L’amministratore crea gli operatori nella scheda «Clienti e impianti» e può disattivarli, reimpostarne la password o rimuoverli. Le verifiche dei permessi vengono eseguite anche dal server per le richieste inviate direttamente.

Per aggiornare **un database già esistente**, fare prima un backup e verificare gli amministratori presenti: eseguire `sql/002-operator-role.sql` una sola volta. Se l’account amministrativo esistente non ha email `caniatoa@libero.it`, aggiornare prima l’indirizzo dopo averne verificato la titolarità. L’ALTER rifiuta più di un amministratore e indirizzi amministrativi diversi. Non applicare la migrazione a un database creato con lo schema aggiornato. La demo Codespaces gestisce da sé la propria migrazione dei dati fittizi.

## Regole documenti e ambiente di presentazione

La scheda **Impostazioni** dell’amministratore permette di impostare il numero massimo di download per documento e i giorni di accesso dalla data di caricamento. `0` lascia la regola illimitata. Quando un limite è raggiunto, il cliente vede ancora la voce nel proprio elenco ma non può aprire o scaricare il file; l’amministrazione conserva il file finché non lo elimina. I download concorrenti vengono contati sotto blocco del record per rispettare il limite. La gestione tecnica di SMTP, dimensione massima del caricamento, spazio e URL resta nel file `config.php` sul server.

Il database di prova Codespaces è separato dalla produzione: usa un amministratore fittizio con password casuale conservata nel container Codespaces in `/private-documents/.demo-admin-login` (nel terminale del Codespace: `cat /private-documents/.demo-admin-login`), cinque aziende, dieci clienti e documenti inventati. Non importare la banca dati di prova sul server reale. Per aggiornare un database reale precedente è necessario applicare anche `sql/003-document-settings.sql` dopo `sql/002-operator-role.sql`, con backup preventivo.

## Caricamento cartelle e archivio

La scheda «Gestione Documenti» permette di caricare un singolo file o una cartella intera destinata a una sola persona e a un impianto. I titoli dei file conservano il percorso relativo delle sottocartelle; i contenuti restano nell’archivio privato e vengono notificati con una sola email per caricamento. Ogni invio accetta al massimo 100 file e ciascun file deve rispettare il limite di dimensione e i formati consentiti. Il server PHP deve essere configurato con `max_file_uploads` sufficiente (almeno 100 per l’intera cartella) e `post_max_size` adeguato; un invio troncato viene rifiutato.

L’«Archivio file» amministrativo mostra data e ora di caricamento, ultimo avvio di download e numero di download per documento, ordinati per data di caricamento. Il numero registra gli avvii di trasmissione dal server, non la conferma del salvataggio sul dispositivo. I documenti si eliminano da questa sezione; non esiste un comando separato di revoca.
