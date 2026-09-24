# Attivazione moduli Contatti su Tophost

La pagina `/contatti/` invia entrambi i moduli a `/contatti/invia.php` sullo stesso dominio. GitHub Pages mostra la pagina come anteprima e disabilita l'invio perché non esegue PHP. Nessun dato del modulo è affidato a FormSubmit.

1. Caricare il sito (incluse `contatti/invia.php`, `vendor/phpmailer/` e i file `.htaccess`) nella cartella `htdocs` del dominio Tophost. Se il File Manager nasconde i file che iniziano con un punto, attivare la visualizzazione dei file nascosti.
2. Creare o scegliere una casella email del dominio in Tophost e annotare utente SMTP e password. Il destinatario resta `caniatoa@libero.it` nel file PHP.
3. Copiare `contact-config.example.php` come `contact-config.php` nella cartella **sopra** `htdocs`, non nella cartella pubblica. Inserire la password SMTP nel file copiato; non aggiungerlo al repository. Il codice cerca esattamente `dirname(__DIR__, 2) . '/contact-config.php'` partendo da `htdocs/contatti/invia.php`.
4. Impostare PHP 8.1 o superiore nel pannello Tophost e verificare che l'estensione `fileinfo` sia attiva. Il limite del CV è 5 MB; `upload_max_filesize` e `post_max_size` devono consentirlo.
5. Provare prima una richiesta con dati di test e verificare che l'email arrivi a `caniatoa@libero.it` (anche nella cartella spam). Poi provare una candidatura con un PDF di test. Controllare che oggetto, Reply-To e allegato siano corretti.
6. Verificare l'informativa `/privacy/` rispetto ai dati aziendali, ai fornitori e ai tempi di conservazione effettivamente applicati, soprattutto alle email e ai CV ricevuti. La pagina pubblicata non cancella automaticamente i messaggi dalla casella di posta.
7. Verificare sul dominio Tophost che `/contatti/` si apra senza errore 500, che i due moduli funzionino, che `/vendor/phpmailer/PHPMailer.php` e `/.htaccess` non siano consultabili, e che gli header di sicurezza siano presenti. Se una direttiva Apache non è ammessa dal piano, verificare il log errori e adattare il file `.htaccess` prima di mantenere il sito online.

Il modulo conta tutti i tentativi POST per indirizzo IP, inclusi quelli non validi: massimo 4 in 15 minuti e 20 in 24 ore. Il conteggio richiede una directory temporanea scrivibile dal PHP; se manca, gli invii si fermano senza tentare di spedire. Il limite non sostituisce una protezione di rete: richieste massicce possono comunque raggiungere il server prima che PHP le respinga. Per resistere a un volume elevato di richieste serve una protezione a monte (per esempio un WAF/CDN configurato sul dominio). Verificare le opzioni disponibili nel piano hosting prima di cambiare DNS o abilitare un proxy.

La configurazione usa SMTP autenticato `mail.tophost.it:587` con STARTTLS, secondo la documentazione Tophost. PHPMailer v6.12.0 è incluso nel repository con la relativa licenza. La conferma di invio indica che il server SMTP ha accettato il messaggio: non garantisce la lettura da parte del destinatario.
