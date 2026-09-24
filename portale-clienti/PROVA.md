# Prova il portale con dati dimostrativi

Questa prova usa un database e documenti **fittizi**. Non caricare dati di clienti reali. Il sito pubblico e Tophost non vengono modificati; l'invio delle email è disattivato.

1. Apri [Avvia demo in GitHub Codespaces](https://codespaces.new/CAAutomazione/CAAutomazione.github.io/tree/area-clienti-portale?quickstart=1) e crea un codespace sul ramo `area-clienti-portale` (o riprendi quello già creato).
2. Attendi che l'ambiente abbia finito di avviarsi. Nella scheda **Porte / Ports** apri il collegamento associato alla porta **8000**, chiamato «Area Clienti · demo». Mantieni la visibilità della porta **Private**.
3. Alla prima entrata spunta «Ho letto l’informativa privacy» e «Ricorda la presa visione». L’amministratore di prova usa dati fittizi ed esiste solo nel database Codespaces. Per leggere le sue credenziali generate casualmente, apri il terminale **nel Codespace della demo** ed esegui `cat /private-documents/.demo-admin-login`. Il file è privato e non è nel repository. La password viene generata una volta e resta la stessa nei riavvii della demo. Nello stesso file privato trovi anche le credenziali **Operatore** e **Cliente** per confrontare le tre viste:

   | Profilo | Email | Cosa verificare |
   | --- | --- | --- |
   | Amministratore di presentazione | vedi file privato nel Codespace | Gestione completa dei dati fittizi |
   | Operatore CA Automazione | vedi file privato | Gestione file, senza accesso agli account |
   | Clienti fittizi | il cliente di prova è nel file privato; altri nove clienti fittizi | Documenti personali e archivio |

   Il database fittizio contiene cinque aziende, almeno dieci clienti e documenti di esempio, alcuni già scaricati. **Non copiare il database della demo su Tophost.** L’account reale `caniatoa@libero.it` va creato nel database di produzione con una password privata di almeno 12 caratteri.

4. Per provare entrambi i profili nello stesso browser, usa **Esci** prima di entrare con un altro account. Nell'amministrazione puoi anche usare «Entra come cliente»: la vista è tracciata e il download è disattivato per non attribuirlo falsamente al cliente.
5. Se la demo non si avvia, apri il terminale del codespace ed esegui `docker compose -f .devcontainer/docker-compose.yml logs app`. Condividi solo l'errore, senza eventuali credenziali che potresti avere aggiunto.

I dati della demo risiedono nel codespace. L'account GitHub che crea l'ambiente controlla l'accesso alla porta privata. Non rendere pubblica la porta: le password dimostrative sono scritte in questo repository pubblico. Le email restano nella coda locale e non partono. La demo non verifica limiti e SMTP del piano Tophost.

Per fermarla, arresta il codespace da GitHub; puoi eliminarlo quando non ti serve più. GitHub Codespaces usa tempo di calcolo e spazio: controlla la [quota del tuo account](https://docs.github.com/en/billing/concepts/product-billing/github-codespaces) prima di crearne più di uno.
