# Attivazione moduli Contatti su Tophost

La pagina `/contatti/` invia entrambi i moduli a `/contatti/invia.php` sullo stesso dominio. GitHub Pages mostra la pagina come anteprima e disabilita l'invio perché non esegue PHP. Nessun dato del modulo è affidato a FormSubmit.

1. Caricare il sito (incluse `contatti/invia.php` e `vendor/phpmailer/`) nella cartella `htdocs` del dominio Tophost.
2. Creare o scegliere una casella email del dominio in Tophost e annotare utente SMTP e password. Il destinatario resta `caniatoa@libero.it` nel file PHP.
3. Copiare `contact-config.example.php` come `contact-config.php` nella cartella **sopra** `htdocs`, non nella cartella pubblica. Inserire la password SMTP nel file copiato; non aggiungerlo al repository. Il codice cerca esattamente `dirname(__DIR__, 2) . '/contact-config.php'` partendo da `htdocs/contatti/invia.php`.
4. Impostare PHP 8.1 o superiore nel pannello Tophost e verificare che l'estensione `fileinfo` sia attiva. Il limite del CV è 5 MB; `upload_max_filesize` e `post_max_size` devono consentirlo.
5. Provare prima una richiesta con dati di test e verificare che l'email arrivi a `caniatoa@libero.it` (anche nella cartella spam). Poi provare una candidatura con un PDF di test. Controllare che oggetto, Reply-To e allegato siano corretti.
6. Completare e approvare l'informativa `/privacy/` prima di raccogliere candidature reali: la pagina attuale è un segnaposto. Definire finalità, tempi di conservazione, destinatari e contatto del titolare.

La configurazione usa SMTP autenticato `mail.tophost.it:587` con STARTTLS, secondo la documentazione Tophost. PHPMailer v6.12.0 è incluso nel repository con la relativa licenza. La conferma di invio indica che il server SMTP ha accettato il messaggio: non garantisce la lettura da parte del destinatario.
