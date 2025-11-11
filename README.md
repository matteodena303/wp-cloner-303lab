# WP Cloner

## Descrizione
WP Cloner è un plugin di WordPress che consente di **esportare e importare** il contenuto e il database di un sito. È pensato per clonare un’installazione in un ambiente di staging o di produzione in modo rapido e sicuro.

### Funzionalità principali
- **Esportazione asincrona**: genera un pacchetto zip (o zip+gzip) con `wp-content` e un dump del database. Mostra una barra di progresso e salva il file in `wp-content/uploads/wpcloner` per il download.
- **Importazione**: permette di caricare un pacchetto generato in precedenza e ripristinarlo in un’installazione WordPress. È possibile specificare un nuovo URL, anonimizzare email e disabilitare l’invio di mail.
- **Importazione pacchetto salvato**: se il pacchetto è già presente sul server (ad esempio caricato via FTP nella cartella `wp-content/uploads/wpcloner`), il plugin consente di selezionarlo e importarlo senza upload via browser.
- **CLI integrato**: comandi WP‑CLI per esportare e importare, utili su siti di grandi dimensioni o in ambienti automatizzati.

## Installazione
1. Comprimi la cartella `wp-cloner-303lab` in un file ZIP (oppure usa il pacchetto ZIP fornito).
2. Nel pannello di amministrazione di WordPress vai su **Plugin → Aggiungi nuovo** e clicca su **Carica plugin**.
3. Seleziona il file zip del plugin e procedi con l’installazione.
4. Attiva il plugin dalla schermata dei plugin installati.

## Utilizzo
### Esportare un sito
1. Vai in **Strumenti → WP Cloner**.
2. Seleziona il formato di compressione desiderato (ZIP o ZIP+GZIP) e clicca **Avvia esportazione**.
3. Durante l’esportazione, viene mostrata una barra di avanzamento. Al termine comparirà un pulsante **Scarica pacchetto**. Puoi anche trovare il pacchetto nella cartella `wp-content/uploads/wpcloner` via FTP.

### Importare un pacchetto tramite upload
1. Nella sezione **Importa**, seleziona il file `.zip` o `.zip.gz` generato da WP Cloner.
2. Inserisci l’eventuale **Nuovo URL** (opzionale) se desideri che il sito clonato abbia un URL diverso.
3. Se necessario, spunta le opzioni per anonimizzare le email degli utenti e/o disabilitare l’invio di mail.
4. Clicca **Importa pacchetto** e attendi il completamento.

### Importare un pacchetto già presente sul server
1. Carica il pacchetto in `wp-content/uploads/wpcloner/` tramite FTP o altro metodo.
2. Nella sezione **Importa pacchetto salvato**, seleziona il file dalla lista dei pacchetti disponibili.
3. Compila le stesse opzioni (nuovo URL, anonimizzazione email, disabilita mail) se necessario.
4. Clicca **Importa pacchetto salvato** per avviare l’importazione.

## Note e suggerimenti
- Assicurati che la dimensione del pacchetto non superi i limiti di upload del tuo hosting. Per file molto grandi, usa l’import tramite pacchetto salvato.
- Dopo l’importazione, controlla i permalink e rigenera le miniature se il tuo tema o plugin li utilizza.
- L’importazione sovrascrive file e database del sito di destinazione. Esegui un backup prima di importare su un sito esistente.
