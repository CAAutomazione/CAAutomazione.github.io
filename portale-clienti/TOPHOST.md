# Preparazione e installazione su Tophost

Questa procedura riguarda il **portale PHP**. Il sito pubblico resta separato. Non usare il database della demo e non caricare documenti veri finché tutti i controlli finali non passano.

## Verifiche indispensabili nel pannello Tophost

1. Verificare che l'indirizzo scelto per il portale risponda in **HTTPS con certificato valido**. La [guida Tophost sui sottodomini](https://www.tophost.it/assistenza/supporto/domande-tecniche/dns/attivare-sottodominio-terzo-livello/) segnala che HTTPS non è disponibile sui terzi livelli: la guida è datata e va verificata **sul proprio piano**, prima di scegliere `clienti.caniatoautomazione.com`. Se non funziona, usare un altro indirizzo HTTPS adatto oppure un hosting che lo supporti. Non usare il portale su HTTP.
2. Verificare che il dominio del portale possa pubblicare **solo la cartella `public/`**. Tutto il resto (`config.php`, `src/`, `vendor/`, `sql/`, `private-documents/`) deve restare fuori dalla radice servita dal web. L'applicazione rifiuta l'avvio se rileva che la propria cartella principale è dentro la radice web. Non affidarsi a un `.htaccess` per proteggere documenti o credenziali in una cartella pubblica.
3. Verificare PHP 8.2 o 8.3, estensioni `pdo_mysql`, `mbstring`, `fileinfo`, MySQL compatibile con lo schema, spazio scrivibile fuori dal web, limiti `upload_max_filesize`, `post_max_size` e `max_file_uploads` adeguati. Le specifiche commerciali Topweb Ultra [indicano PHP fino a 8.3, database MySQL, HTTPS e SMTP](https://www.tophost.it/), ma non confermano configurazione e permessi della singola installazione.

Se 1 o 2 non sono possibili, fermare l'installazione: cambiare l'architettura/hosting prima di esporre documenti e credenziali.

## Pacchetto, database e configurazione

1. Scaricare l'artefatto **`portale-clienti-tophost`** dall'ultima esecuzione riuscita di **Portal release package** in GitHub Actions sul ramo `area-clienti-portale`. Contiene la libreria email già installata (`vendor/`) e non contiene credenziali, `config.php` né database demo.
2. Caricare l'applicazione in una cartella privata e far puntare la radice web del portale a `portale-clienti/public/`. In `public/` devono essere visibili soltanto `index.php`, `portal.js`, `style.css`, `assets/` e `.htaccess`.
3. Creare un database **nuovo e vuoto** e importare **soltanto** `portale-clienti/sql/schema.sql`. Su un database già in uso seguire le migrazioni documentate in `README.md` dopo backup; non importare lo schema da capo.
4. Nella cartella privata copiare `config.example.php` in `config.php`. Inserire DSN, utente e password del database Tophost; `base_url` con l'indirizzo HTTPS definitivo del portale, `site_url` con `https://caniatoautomazione.com/`, `privacy_url` con l'informativa **definitiva e pubblicata**, `storage_path` in una cartella privata esistente e scrivibile; `admin_email`, mittente e parametri SMTP effettivi. Impostare `capacity_bytes` soltanto dopo aver verificato quanta quota è davvero disponibile per i documenti; con `null` l'allerta a 2 GiB è disattivata.
5. Sostituire `cron_secret` e `setup_secret` con **due valori casuali diversi di almeno 32 caratteri**, conservati solo in `config.php`. Generarli localmente con un gestore di password o un generatore crittografico. Non inserirli nel repository, nei link, nelle schermate o nei messaggi.
6. Consentire a PHP di scrivere in `storage_path`; non rendere la directory accessibile da HTTP. Verificare che file e cartelle non siano elencabili dal web.

## Primo account e notifiche

Se l'hosting non dà accesso alla riga di comando, prima di creare altri account aprire **`https://INDIRIZZO-PORTALE/?action=setup`** tramite HTTPS. Il modulo chiede nome, cognome, codice `setup_secret` e una nuova password di almeno 12 caratteri. Crea esclusivamente l'amministratore `caniatoa@libero.it` e si disattiva dopo il primo utilizzo. La password precedente non è necessaria e non deve essere pubblicata. Se si dispone di CLI si può usare `php bin.php admin Nome Cognome caniatoa@libero.it`.

Tophost [documenta l'uso di un servizio cron esterno](https://www.tophost.it/assistenza/supporto/domande-tecniche/usare-il-servizio-cron/). Per inviare realmente le email della coda, impostare un servizio di schedulazione che faccia una richiesta **POST** ogni cinque minuti a `https://INDIRIZZO-PORTALE/?action=jobs` con header `X-Portal-Job-Key` uguale a `cron_secret`. **Non mettere il segreto nell'URL**. Il servizio [cron-job.org supporta header personalizzati](https://cron-job.org/en/faq/); controllare le condizioni di uso e verificare che un'esecuzione produca HTTP `204`. Una richiesta senza header deve produrre `404`. Se si dispone di CLI, in alternativa eseguire `php bin.php jobs` ogni cinque minuti. Non configurare contemporaneamente entrambe le modalità. Il job HTTP invia al massimo quattro email per esecuzione per limitare il tempo di risposta; con coda più lunga richiede più esecuzioni.

## Collaudo prima di invitare i clienti

- Aprire la pagina di accesso su HTTPS; verificare il certificato e che HTTP venga reindirizzato a HTTPS. Verificare nel pannello amministratore **Impostazioni → Verifica ambiente**: tutti i controlli devono essere verdi. I limiti PHP di upload devono essere coerenti con la dimensione prevista per file e cartelle.
- Provare dall'esterno a richiedere `config.php`, `sql/schema.sql`, `src/Core.php`, `vendor/autoload.php` e un file dell'archivio: nessuno deve essere scaricabile tramite URL.
- Provare login errato, recupero password, login corretto, caricamento di un file di prova destinato a un cliente di prova, email di consegna, accesso del solo destinatario, download, email di download e archivio.
- Provare un account diverso: non deve vedere né scaricare il file. Verificare che il job invii le email, che non produca duplicati e che i messaggi in errore restino nella coda per il nuovo tentativo.
- Verificare backup di database e archivio, ripristino, monitoraggio della quota e disponibilità di protezioni web server/CDN per traffico abusivo. L'email di download registra l'avvio del trasferimento e non certifica il salvataggio completo del file.

Non è possibile confermare SSL dei sottodomini, radice web configurabile, SMTP e invio periodico senza accedere alle impostazioni del piano concreto e provarle sull'indirizzo definitivo.
