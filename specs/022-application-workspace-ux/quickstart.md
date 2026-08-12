# Quickstart di Verifica End-to-End

**Scopo**: guida di validazione del programma e delle singole Slice; non è una procedura di
implementazione.

## Prerequisiti

- Docker e Docker Compose disponibili;
- servizi `laravel.test`, `mysql` e `frontend` avviati;
- prodotto Greenfield senza dati da preservare, confermato esplicitamente dal proprietario il
  2026-08-12; i reset restano limitati agli ambienti di sviluppo/test previsti;
- browser desktop supportato.

## Avvio e Baseline

```bash
docker compose up -d laravel.test mysql frontend
docker compose exec -T -u sail laravel.test composer test:prepare
docker compose exec -T laravel.test php artisan test --testsuite=Accounting
docker compose exec -T frontend npm test -- --run
```

Questi comandi appartengono alla validazione delle future Slice e non sono stati eseguiti durante
questo intervento esclusivamente documentale. Non usare conteggi storici come risultato atteso fisso.

Prima di applicare una nuova Slice Greenfield nell'ambiente di test:

```bash
docker compose exec -T -u sail laravel.test php artisan migrate:fresh --seed --env=testing --force
```

Il comando deve rifiutarsi di operare fuori dagli ambienti di sviluppo/test protetti. La conferma
Greenfield non autorizza purge automatici delle Spese nel Cestino.

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

## Scenario 2 — Plafond Singolo e Copertura Integrale

1. Creare il solo Plafond 2025 del Centro di Costo A con Allocazione iniziale €3.000.
2. Creare una Riga Ordinaria da €500 dello stesso Tenant/Anno/Centro, collegarla al Plafond e
   verificare che sia coperta integralmente.
3. Verificare che l'Allocazione entri una volta nel Budget, la pianificazione coperta alimenti la
   Copertura Prevista senza aumentare il totale e l'Effettivo alimenti il Consumato.
4. Tentare una seconda Spesa Plafond corrente sullo stesso Centro/Anno: la mutazione deve fallire.
5. Creare una Riga da €2.700 quando il Disponibile è €2.500: il salvataggio deve restituire
   `PLAFOND_INSUFFICIENT` con Allocazione, Disponibile, Richiesto e Mancante, non persistere nulla e
   mantenere tutti gli input.
6. Aggiungere una Riga di Allocazione `+500` allo stesso Plafond e ripetere il salvataggio con esito
   positivo.
7. Collegare Stime/Preventivi coperti per un totale superiore al Disponibile e verificare che
   aumenti Copertura Prevista senza ridurre Disponibile; trasformare poi una parte in Effettivo e
   verificare che Disponibile diminuisca e che un Effettivo eccedente sia bloccato atomicamente.
8. Tentare una riduzione `-2.900` che invaliderebbe coperture esistenti: la preview deve elencarle e
   il salvataggio deve essere bloccato senza scollegarle.
9. Modellare una Spesa da €800, di cui €500 coperti e €300 non coperti, usando due Righe distinte;
   verificare che una singola Riga non accetti copertura parziale.

## Scenario 3 — Approvazione, Extra e Chiusura

1. Preparare il Budget Proposto con più Spese, Contratti e Progetti.
2. Aprire la Vista di Impatto e approvare l'intera composizione con una sola Data e Approvatore.
3. Modificare una Valutazione: il Previsto deve restare immutato.
4. Inserire una Spesa Extra Budget con Nota e un Effettivo; il Previsto non cambia.
5. Inserire un'omissione come Rettifica e verificare che entri nel valore rappresentato del Budget
   Approvato senza diventare Extra Budget.
6. Aprire Chiusura, lasciare almeno una segnalazione irrisolta e chiudere comunque.
7. Inserire una Nota di Credito tardiva nell'Anno Chiuso: deve produrre una Rettifica con Nota senza
   creare un `Budget Finale Rettificato`.
8. Verificare che la Riapertura sia bloccata dopo la prima Rettifica successiva.
9. Su un secondo Budget Chiuso senza Rettifiche successive, eseguire la Riapertura con Nota e
   verificare snapshot precedente non corrente, nuova Revisione e ritorno ad Approvato.
10. Su un Budget Approvato privo di eventi dipendenti, annullare l'Approvazione con Nota e verificare
    ritorno in Preparazione e conservazione di Data, Approvatore e contenuto storico.

## Scenario 4 — Progetto Pluriennale

1. Creare un Progetto 2025 con una Spesa avente Pianificazione Corrente €10.000 ed Effettivi €4.000.
2. Registrare nel 2026 una Spesa appartenente al Progetto: deve concorrere al 2025 e mostrare sia
   Anno di Competenza sia Data reale.
3. Richiedere il passaggio al 2026: la preview deve proporre una Continuazione, non lo Spostamento.
4. Confermare la nuova Riga Stima per-Spesa suggerita di €6.000 o modificarla, verificando che il
   Progetto non persista un importo monetario proprio.
5. Verificare Progetto 2025 ancora aperto, Progetto 2026 collegato e viste **Solo Questo Progetto** /
   **Intero Percorso** riconciliate; chiudere poi l'origine con un'azione manuale separata.
6. Ripetere con un Progetto senza Effettivi e verificare lo Spostamento atomico.
7. Tentare una seconda Continuazione dallo stesso Progetto, un self-link e un ciclo: ogni operazione
   deve fallire senza persistenza parziale e senza esporre dati di un altro Tenant.

## Scenario 5 — Contratti e Scadenziario

### Annuale

1. Creare un Contratto da €1.200 con Rinnovo 13 febbraio 2025 e periodo fino al 12 febbraio 2026.
2. Verificare una Riga Effettivo da €1.200 nel Budget 2025, senza ripartizione sul 2026.
3. Verificare nello Scadenziario Data, periodo, preavviso e collegamento alla Spesa.
4. Creare una futura occorrenza 2026 modificata manualmente, richiedere cessazione nel 2025 e
   verificare che la preview la mostri senza eliminarla automaticamente.
5. Cessare dopo un Effettivo già generato: l'Effettivo deve restare; un eventuale adeguamento è
   esplicito e su Budget Chiuso produce Rettifica con Nota.

### Mensile

1. Creare un Contratto da €100/mese con inizio 13 marzo 2025.
2. Verificare una sola Spesa Contrattuale 2025 con dieci Righe datate marzo–dicembre e Totale €1.000.
3. Modificare la sola mensilità di giugno e verificare che le altre non cambino.
4. Cessare il Contratto dal 1 settembre e verificare che marzo–agosto restino e le mensilità
   successive non vengano generate, senza pro-rata giornaliero.
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
2. Verificare che scompaia da Budget e Plafond e compaia nel Cestino Spese Multi-Anno.
3. Se la Spesa è generata da Contratto, rieseguire il job e verificare che la Source Key soppressa
   impedisca la rigenerazione.
4. Tentare il Restore con una dipendenza ancora eliminata: deve essere bloccato con link alla
   dipendenza.
5. Risolvere le dipendenze bloccanti indicate dalla preview e poi ripristinare la Spesa; in Anno
   Chiuso verificare la Rettifica prodotta.
6. Verificare che gli Allegati binari eliminati terminalmente non vengano ricreati dal Restore e che
   la UI comunichi il limite.
7. Aprire Revisioni, scegliere uno Snapshot e verificare i campi modificati nella normale vista
   documento.
8. Ripristinare lo Snapshot e verificare una nuova Revisione senza recupero binario degli Allegati.
9. Eseguire due volte il job di retention Revisioni e verificare idempotenza. Per gli Allegati,
   verificare invece il purge terminale sincrono della normale Action di cancellazione e un retry
   sicuro senza side effect duplicati; non dichiarare né eseguire un job di purge Allegati o Spese.
10. Creare più Revisioni del limite operativo e verificare che quelle eccedenti non siano più
   consultabili o ripristinabili nella Cronologia operativa.
11. Verificare che tutte le Revisioni ancora visibili restino ripristinabili.
12. Richiedere un Report storico con cutoff precedente alle Revisioni espulse e verificare la
    ricostruzione corretta dagli `snapshot_contents` persistiti.
13. Verificare che `RevisionBatch`, `RevisionBatchItem`, audit e snapshot necessari alla storia
    annuale non siano stati eliminati dalla manutenzione delle `Version`.

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
