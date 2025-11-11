# WP Cloner

## Installazione
1. Scarica il pacchetto del plugin in formato ZIP.
2. Nel pannello WordPress vai in **Plugin → Aggiungi nuovo** e carica il file ZIP.
3. Attiva il plugin dalla lista dei plugin installati.

## Uso

### Esportazione
1. Vai in **Strumenti → WP Cloner**.
2. Scegli il formato di compressione (ZIP o ZIP+GZIP) e avvia l’esportazione.
3. Il pacchetto verrà salvato in `wp-content/uploads/wpcloner/` e sarà scaricabile tramite il pulsante “Scarica pacchetto”.
4. Per siti molto grandi puoi accedere via FTP a `wp-content/uploads/wpcloner/` e copiare il file sul tuo computer.

### Importazione
- **Upload**: Usa la sezione “Importa” per caricare il pacchetto ZIP direttamente dal browser (solo se rientra nei limiti di upload del server).
- **Pacchetto salvato**: Se hai già caricato il pacchetto via FTP nella cartella `wp-content/uploads/wpcloner/`, utilizza la sezione “Importa pacchetto salvato”. Seleziona il file dall’elenco e avvia l’importazione.
- Puoi impostare un nuovo URL per il sito clonato e, se necessario, abilitare le opzioni per anonimizzare gli indirizzi email o disabilitare l’invio di email (utile negli ambienti di staging).

## Consigli
- Per file molto grandi (>100–200 MB), preferisci il caricamento via FTP e l’importazione tramite “pacchetto salvato”.
- Assicurati che il pacchetto da importare sia stato creato con la stessa o più recente versione del plugin.
- Dopo l’importazione, verifica le impostazioni di permalink e rigenera eventuali miniature con un plugin tipo “Regenerate Thumbnails”.

