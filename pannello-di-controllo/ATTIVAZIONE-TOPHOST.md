# Attivazione del pannello riservato su Tophost

Il pannello è accessibile all'indirizzo `https://www.caniatoautomazione.com/pannello-di-controllo/` dopo l'installazione su un hosting con PHP e MySQL. Non funziona nell'anteprima GitHub Pages, che non esegue PHP. Non ci sono collegamenti al pannello nella navigazione del sito.

## Preparazione

1. Nel pannello Tophost verifica che il tuo piano consenta PHP 8.1 o superiore, PDO MySQL, sessioni PHP, HTTPS, invio SMTP e un database MySQL. Le versioni disponibili cambiano in base al piano: se PHP 8.1 non è disponibile, non caricare il pannello senza adeguare l'hosting.
2. Crea il database MySQL e conserva host, nome, utente e password. Le tabelle vengono create al primo avvio dal pannello.
3. Carica i file del sito nella cartella `htdocs`, compresi `pannello-di-controllo/`, `vendor/`, `.htaccess`, `visite.js` e `contatti/invia.php`.
4. **Fuori da `htdocs`, nella cartella immediatamente superiore**, copia `admin-config.example.php` con il nome `admin-config.php`. Inserisci i dati MySQL e l'indirizzo HTTPS pubblico esatto in `site_origin` (con oppure senza `www`, come nel dominio reale). Genera **due chiavi casuali diverse** di almeno 32 caratteri per `analytics_key` e `setup_key`, ad esempio con un gestore password. Non copiare né caricare su GitHub il file con i valori reali.
5. Nella stessa cartella privata configura `contact-config.php` come indicato in `contatti/DEPLOY-TOPHOST.md`. L'email SMTP è necessaria sia per i moduli sia per gli avvisi di accesso e il recupero password. Il destinatario amministrativo è `caniatoa@libero.it`.
6. Apri su HTTPS `/pannello-di-controllo/attiva.php`, inserisci la chiave `setup_key` e imposta la password iniziale. La password viene elaborata con `password_hash()` e non deve apparire nei file caricati. Dopo l'attivazione la pagina risponde come inesistente; puoi anche rimuovere `attiva.php` dal pacchetto caricato. Accedi dal percorso `/pannello-di-controllo/` e cambia la password iniziale con una credenziale lunga e diversa da quelle condivise in precedenza.

## Controlli da fare dopo il caricamento

- Senza login, `/pannello-di-controllo/` deve mostrare solo l'accesso e non statistiche, registro o impostazioni.
- Verifica una credenziale errata, poi una corretta: l'accesso riuscito deve arrivare nel registro e inviare un avviso email. Se l'SMTP non funziona, l'accesso deve restare negato.
- Prova “Password dimenticata?”: il link email scade dopo 20 minuti ed è monouso. Dopo il cambio password la vecchia credenziale e le sessioni aperte non devono più funzionare.
- Verifica che `lib.php`, i sorgenti PHPMailer e i file di configurazione fuori da `htdocs` non siano scaricabili dal browser.
- Prova i moduli di contatto e candidatura con invii reali di test; solo gli invii riusciti entrano nei contatori. La modalità “Sospendi gli invii” va provata prima di usarla operativamente.
- Il conteggio delle visite parte **disattivato**. Completata e verificata l'informativa privacy con la configurazione reale, puoi attivarlo dalle impostazioni del pannello. Non ricostruisce visite precedenti.

Il grafico mostra una **stima di visitatori distinti per giorno**, non il numero certificato di persone: più dispositivi o persone dietro lo stesso IP possono modificare il risultato. Il server usa un'impronta giornaliera derivata da IP e browser, cancellata dopo due giorni; la statistica aggregata e il registro accessi hanno una conservazione configurabile di 30, 90 o 180 giorni. La raccolta non usa cookie analytics nel browser. Il pannello usa invece un cookie tecnico di sessione (`ca_admin`) necessario al login, con flag Secure, HttpOnly e SameSite Strict; va riportato nell'informativa definitiva.

Il registro conserva gli ultimi 50 tentativi visibili e, nel database, i tentativi per il periodo di conservazione scelto. L'indirizzo IP e il browser dichiarato non consentono di attribuire con certezza un accesso a una persona. Tieni aggiornati PHP, il piano hosting, la libreria PHPMailer e la casella email amministrativa. Mantieni backup protetti del database e dei file privati.
