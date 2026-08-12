# Quickstart di Verifica End-to-End

**Scopo**: guida di validazione del programma e delle singole Slice; non è una procedura di
implementazione.

## Prerequisiti

- Docker e Docker Compose disponibili;
- servizi `laravel.test`, `mysql` e `frontend` avviati;
- nessun dato reale da preservare: il database di sviluppo/test è ricostruibile;
- browser desktop supportato.

## Avvio e Baseline

```bash
docker compose up -d laravel.test mysql frontend
docker compose exec -T -u sail laravel.test composer test:prepare
docker compose exec -T laravel.test php artisan test --testsuite=Accounting
docker compose exec -T frontend npm test -- --run
```

Baseline verificata prima del design: `34` test Accounting e `235` asserzioni superati nel container
MySQL; `33` file e `103` test Frontend superati. I numeri cresceranno con le Slice e non devono essere
usati come conteggio atteso fisso.

Prima di applicare una nuova Slice Greenfield:

```bash
docker compose exec -T -u sail laravel.test php artisan migrate:fresh --seed --env=testing --force
```

Il comando deve rifiutarsi di operare fuori dall'ambiente di test secondo le protezioni già presenti.

## Gate Economico

La prima Slice installa Xdebug nell'immagine di test. Il Gate usa il container e deve fallire sotto
il 100% line e branch per le classi pure del nucleo economico introdotte o modificate. Il report di
coverage non sostituisce le tabelle decisionali e le riconciliazioni MySQL.

Per ogni Slice economica verificare contemporaneamente:

1. esempi numerici canonici;
2. invarianti al centesimo;
3. nessun doppio conteggio;
4. stesso risultato in Budget, Report e Drill-Down;
5. rollback completo su errore;
6. allow stesso Tenant, deny permesso mancante e deny altro Tenant.

## Scenario 1 — Base Economica e Spesa

1. Creare un Tenant con Base predefinita Netto e Anno 2025.
2. Creare una Spesa con Stima €100 + IVA 22%, quindi Preventivo €110 + IVA e selezionarlo come Riga
   Previsionale Corrente.
3. Aggiungere due Effettivi da €40 e €65 Netti.
4. Verificare che il Budget Proposto, prima dell'Approvazione, usi €110 e che l'Effettivo sia €105.
5. Cambiare la Base a Lordo prima dell'Approvazione e verificare la stessa proiezione sui valori
   Lordi riconciliati.
6. Approvare il Budget e verificare che un successivo cambio Base sia bloccato anche dopo un eventuale
   Annullamento dell'Approvazione.
7. Verificare che la Spesa non mostri Stati Aperta/Chiusa.

## Scenario 2 — Plafond Ripartito

1. Creare due Plafond dello stesso Centro di Costo, con Residui rispettivi €100 e €1.000 nella Base
   ufficiale.
2. Creare una Riga Ordinaria da €500 e selezionare entrambi i Plafond.
3. Confermare Quote da €100 e €400 e salvare.
4. Verificare Copertura Prevista sulla Stima/Preventivo e Consumato soltanto sull'Effettivo.
5. Tentare Quote per €450: il salvataggio deve essere bloccato e il form deve mantenere i dati.
6. Superare la Capienza complessiva: deve apparire l'Avviso e il salvataggio deve richiedere una
   motivazione aggiunta alle Note Generali.

## Scenario 3 — Approvazione, Extra e Chiusura

1. Preparare il Budget Proposto con più Spese, Contratti e Progetti.
2. Aprire la Vista di Impatto e approvare l'intera composizione con una sola Data e Approvatore.
3. Modificare una Valutazione: il Previsto deve restare immutato.
4. Inserire una Spesa Extra Budget con Nota e un Effettivo; il Previsto non cambia.
5. Inserire un'omissione come Rettifica e verificare che entri nel valore rappresentato del Budget
   Approvato senza diventare Extra Budget.
6. Aprire Chiusura, lasciare almeno una segnalazione irrisolta e chiudere comunque.
7. Inserire una Nota di Credito tardiva nell'Anno Finale: deve produrre una Rettifica con Nota senza
   creare un `Budget Finale Rettificato`.
8. Verificare che la Riapertura sia bloccata dopo la prima Rettifica successiva.

## Scenario 4 — Progetto Pluriennale

1. Creare un Progetto 2025 con Valutazione €10.000 e Effettivi €4.000.
2. Registrare nel 2026 una Spesa appartenente al Progetto: deve concorrere al 2025 e mostrare sia
   Anno di Competenza sia Data reale.
3. Richiedere il passaggio al 2026: la preview deve proporre una Continuazione, non lo Spostamento.
4. Confermare un Importo da Riproporre suggerito di €6.000 o modificarlo.
5. Verificare Progetto 2025 chiuso, Progetto 2026 collegato e viste **Solo Questo Progetto** / **Intero
   Percorso** riconciliate.
6. Ripetere con un Progetto senza Effettivi e verificare lo Spostamento atomico.

## Scenario 5 — Contratti e Scadenziario

### Annuale

1. Creare un Contratto da €1.200 con Rinnovo 13 febbraio 2025 e periodo fino al 12 febbraio 2026.
2. Verificare una Riga Effettivo da €1.200 nel Budget 2025, senza ripartizione sul 2026.
3. Verificare nello Scadenziario Data, periodo, preavviso e collegamento alla Spesa.

### Mensile

1. Creare un Contratto da €100/mese con inizio 13 marzo 2025.
2. Verificare una sola Spesa Contrattuale 2025 con dieci Righe datate marzo–dicembre e Totale €1.000.
3. Modificare la sola mensilità di giugno e verificare che le altre non cambino.
4. Cessare il Contratto da settembre e verificare che non vengano generate Scadenze successive.
5. Creare il 2026 con Rinnovo Automatico e verificare generazione idempotente, senza duplicati.

## Scenario 6 — Avvio Storico e Composizione Annuale

1. Creare 2024 e 2025 come Anni Storici modificabili.
2. Inserire Effettivi ordinari ed Extra Budget, quindi attivare il Previsto Ricostruito.
3. Verificare che soltanto gli Effettivi non Extra formino il Previsto Ricostruito.
4. Nel nuovo Anno consultare la lista delle Spese senza Effettivi e scegliere, elemento per elemento,
   Riproposta, Eliminazione o Dettaglio.
5. Verificare che Contratti e Progetti seguano le proprie regole e non un riporto annuale generico.

## Scenario 7 — Cestino e Revisioni

1. Eliminare una Spesa con Effettivi fornendo la Nota richiesta.
2. Verificare che scompaia da Budget e Plafond e compaia nel Cestino unico.
3. Tentare il Restore con una dipendenza ancora eliminata: deve essere bloccato con link alla
   dipendenza.
4. Ripristinare le dipendenze e poi la Spesa; in Anno Finale verificare la Rettifica prodotta.
5. Aprire Revisioni, scegliere uno Snapshot e verificare i campi modificati nella normale vista
   documento.
6. Ripristinare lo Snapshot e verificare una nuova Revisione senza recupero binario degli Allegati.
7. Eseguire due volte i job di retention/purge e verificare idempotenza.

## Scenario 8 — Report e Navigazione Contestuale

1. Confrontare liberamente Budget Finale 2025 e Budget Approvato 2026.
2. Cambiare entrambi i selettori con anni non consecutivi.
3. Verificare KPI, Confronto per Dimensione, Differenza, Progressione Mensile e grafico condizionale
   Extra/Rettifiche.
4. Attivare lo Switch Valutazioni e verificare che Stime/Preventivi siano sovrapposti senza entrare
   nei Totali Effettivi.
5. Cliccare un punto del grafico: la pagina deve scorrere al dettaglio con filtri applicati e
   modificabili.
6. Sommare il dettaglio e verificare riconciliazione esatta con il punto e con il Totale del Budget.

## Scenario 9 — Workspace, Guida e Anno Globale

1. Cambiare Anno nella Barra Superiore navigando tra Dashboard, Spese, Contratti e Progetti.
2. Da un documento assente nel nuovo Anno, verificare ritorno al Registro pertinente con messaggio
   informativo.
3. Ripetere con modifiche non salvate e verificare la Dirty Guard.
4. Attivare Guida e controllare le descrizioni sotto le opzioni principali.
5. Configurare filtri, colonne e ordine in più Registri e verificare le Preferenze personali.
6. Provare Date vietate dal Date Picker, digitazione manuale e chiamata API diretta.

## Scenario 10 — Tenant e Scheduler

1. Come Amministratore di Piattaforma, creare un Tenant e aprirne le Impostazioni.
2. Come Amministratore del Tenant, verificare che non sia accessibile l'elenco degli altri Tenant.
3. Copiare dalla UI il comando Cron e verificare che i segnaposto siano dichiarati.
4. Senza heartbeat mostrare `Stato non verificabile`.
5. Dopo un'esecuzione schedulata registrata, mostrare ultima esecuzione ed esito senza leggere
   direttamente il demone Cron del sistema operativo.

## Verifica Finale di Ogni Slice

Prima di dichiarare una Slice completata:

```bash
docker compose exec -T -u sail laravel.test composer test:static
docker compose exec -T -u sail laravel.test composer test:prepare
docker compose exec -T -u sail laravel.test composer test:accounting
docker compose exec -T -u sail laravel.test composer test:application
docker compose exec -T frontend npm test -- --run
docker compose exec -T frontend npm run build
```

Eseguire inoltre il comando di coverage introdotto dalla Slice 1 e la relativa acceptance manuale o
automatizzata. Non dichiarare verde una suite non eseguita.
