# Raffinamento del Dominio Budget

**Stato**: `PROPOSED TARGET — Product decisions approved; not implemented`
**Data di consolidamento**: 2026-08-12
**Baseline verificata**: `laravel-replatform@b226a6a292e663aabf1167709aef8603c7b0ee94`

## Autorità e classificazione

Questo documento è la fonte di prodotto del target economico fino alla creazione e consegna delle
Slice Verticali. Non descrive il comportamento già disponibile come se fosse stato implementato.

- `VERIFIED CURRENT`: comportamento verificato nel codice della baseline.
- `PROPOSED TARGET`: decisione approvata ma non ancora implementata.
- `INFERRED`: conseguenza non dichiarata testualmente ma dedotta da decisioni approvate o da
  vincoli tecnici; deve essere resa esplicita e rivalidata nella Slice proprietaria.
- `CONFLICT`: comportamento corrente o artefatto precedente incompatibile con il target.
- `DEPRECATED`: precedente regola documentale sostituita e non più valida come target.
- `OPEN QUESTION`: decisione di prodotto realmente irrisolta.
- `MIGRATION-ONLY`: comportamento temporaneo ammesso esclusivamente durante il refactor.

Codice, migration, test e contratti API correnti restano autorità sul comportamento implementato
fino alla consegna delle Slice. Laravel resta l'unico proprietario della semantica economica; React
presenta dati e impatti senza implementare un secondo Motore Economico.

## Principi di Prodotto

> Master Plan IT gestisce il Budget IT sotto il profilo gestionale e decisionale. Non è un sistema
> contabile, fiscale, di pagamento o di Project Management. L'Anno Economico indica il Budget al
> quale una disponibilità o una decisione economica viene attribuita. La Data della Spesa indica
> quando l'evento si è verificato e può appartenere a un anno civile differente senza cambiare
> automaticamente l'attribuzione al Budget.

Da questo principio discendono le seguenti regole `PROPOSED TARGET`:

- nessuna gestione di ratei e risconti o ripartizione contabile automatica tra anni;
- nessuno stato `Pagato`, `Da Pagare` o equivalente;
- nessuna gestione di task, milestone o percentuali di avanzamento Progetto;
- nessuna classificazione fiscale obbligatoria delle Rettifiche;
- Anno Economico, Data della Spesa e Data di Registrazione restano informazioni separate;
- Progetto e Contratto forniscono contesto e generazione, ma non sono sorgenti monetarie parallele;
- gli importi autorevoli usano stringhe decimali e la Base ufficiale del Tenant;
- Budget, Dashboard, Report e Drill-Down consumano un'unica proiezione economica Laravel.

Il `Forecast` non appartiene al dominio: non esistono entità, stati, misure o formule Forecast.
Stime e Preventivi successivi all'Approvazione restano Valutazioni informative.

### Base Economica e valori

Ogni Tenant usa una sola Base ufficiale, `Netto` o `Lordo`, con `Netto` predefinito. La Base è un
parametro dell'unico Motore Economico, viene bloccata definitivamente dalla prima Approvazione e
non si sblocca con il suo Annullamento. Ogni Riga conserva Netto, IVA e Lordo; la preferenza
personale delle colonne visibili non cambia la Base o i calcoli.

Una Spesa può contenere Stime, Preventivi ed Effettivi. Prima dell'Approvazione una sola
pianificazione corrente alimenta il Budget Proposto; Stima e Preventivo non si sommano. Gli
Effettivi possono essere multipli e positivi o negativi quando il dominio lo consente. Il Previsto
fissato dall'Approvazione non viene riscritto da Valutazioni successive.

## Baseline corrente verificata

Sono `VERIFIED CURRENT` nella baseline:

- un solo record Anno Economico per Tenant e anno, con stati tecnici `preparation`, `approved` e
  `closed`;
- un singolo riferimento opzionale `funded_plafond_expense_id` sulla Riga di Spesa;
- un limite operativo costante di dieci revisioni per aggregato;
- ogni `RevisionBatchItem` conserva `snapshot_contents` completo;
- il limite operativo controlla consultazione, confronto e restore;
- la manutenzione scollega e rende eliminabili soltanto le `Version` tecniche ridondanti;
- `RevisionBatch`, `RevisionBatchItem`, audit e storia annuale non vengono eliminati da tale
  manutenzione;
- la proiezione annuale a cutoff legge gli snapshot persistiti e non dipende dal limite delle
  revisioni operative.

Sono `CONFLICT` rispetto al target, tra gli altri, la compatibilità corrente con più Plafond dello
stesso Centro/Anno, lo Sforamento conteggiato a posteriori, `approved_amount` mutabile tramite
Variazioni, l'assenza di Riapertura formale e la generazione contrattuale limitata a Preventivi.

## Anno Economico e Budget unico

### Un solo contenitore annuale

Per ogni Tenant e Anno esiste un solo Budget, coincidente con l'Anno Economico:

```text
Preparazione → Approvato → Chiuso
```

Approvazione, Chiusura e Rettifiche non creano Budget alternativi. `Budget in Lavorazione` e
`Budget Proposto` sono viste della Preparazione; `Budget Finale` è il medesimo Budget annuale nello
stato Chiuso.

### Budget Approvato

Il valore corrente è:

```text
Budget Approvato = Approvazione iniziale + Rettifiche successive all'Approvazione
```

Una voce dimenticata entra tramite Rettifica. L'Approvazione iniziale resta ricostruibile tramite
operazione, item e snapshot tecnico, ma non è un secondo contenitore di prodotto.

### Budget Finale

Il valore corrente è:

```text
Budget Finale = Situazione alla Chiusura + Rettifiche successive alla Chiusura
```

Una Spesa registrata successivamente e attribuita a quell'Anno entra come Rettifica dello stesso
Budget Finale. Sono esclusi `Budget Finale Rettificato`, nuovi Budget, versioni fiscali o
contenitori separati per documenti tardivi.

### Rettifica

Esiste una sola semantica di Rettifica, indipendente dalla causa fiscale o amministrativa. Conserva
almeno Budget/Anno, Spesa o Riga, valore precedente, valore successivo, delta, data, autore, Nota e
fase di registrazione (`dopo Approvazione` o `dopo Chiusura`). Non richiede enum per fattura tardiva,
nota di credito, omissione, rimborso, errore o storno.

| Situazione | Comportamento |
|---|---|
| Modifica durante Preparazione | Modifica ordinaria |
| Effettivo di una Spesa prevista dopo Approvazione | Effettivo ordinario |
| Voce dimenticata nell'Approvazione | Rettifica |
| Nuova esigenza nata dopo Approvazione | Extra Budget |
| Modifica economica dopo Chiusura | Rettifica |
| Spesa tardiva attribuita a un anno Chiuso | Rettifica |
| Cancellazione o Ripristino con effetto economico su anno Chiuso | Rettifica |
| Cambio Centro di Costo, Fornitore, Descrizione o Note | Modifica revisionata, non Rettifica |

### Riapertura e Annullamento

La Riapertura del Budget Finale è un'azione esplicita e autorizzata. È ammessa solo finché non
esistono Rettifiche successive alla Chiusura, non elimina dati economici, rende non corrente lo
snapshot di Chiusura, conserva Data, Utente, riepilogo e Cronologia, crea una Revisione e richiede
una Nota. Una nuova Chiusura produce un nuovo evento/snapshot.

L'Annullamento dell'Approvazione è ammesso quando non esistono eventi dipendenti definiti dal
dominio. Non elimina l'Approvazione precedente, conserva Data, Approvatore e contenuto storico,
riporta il Budget in Preparazione, crea una Revisione e richiede una Nota.

### Note obbligatorie

La Nota è obbligatoria almeno per Rettifica, Extra Budget, Riapertura, Annullamento
dell'Approvazione, cancellazione di una Spesa con Effettivi, modifica economica su Budget Chiuso e
modifica o cessazione contrattuale che produce una Rettifica.

## Progetti

### Natura e attribuzione annuale

Un Progetto rappresenta la porzione economica di un'iniziativa attribuita a un determinato Budget
annuale. Non è uno strumento di Project Management operativo.

```text
Anno Economico della Spesa collegata a un Progetto = Anno del Progetto
Data della Spesa = data reale dell'evento
```

Un Progetto 2025 può ricevere Spese datate 2025 o anni successivi, ma tutte continuano a concorrere
al Budget 2025. La UI mostra entrambe le informazioni. Se il Budget 2025 è Chiuso, la nuova Spesa è
una Rettifica 2025 con Nota.

La Fase del Progetto può restare descrittiva e manuale, ma non determina Anno, Budget, Previsto,
Effettivo, Chiusura o Continuazione.

### Continuazione

Una Continuazione è la nuova porzione economica finanziata dal Budget dell'anno successivo:

```text
Progetto 2025
└── Continuazione: Progetto 2026
```

Il nuovo Progetto usa un semplice riferimento al precedente; non esiste `ProjectFamily`. La
creazione della Continuazione crea il Progetto nell'anno di destinazione, collega i due Progetti,
non sposta gli Effettivi, abilita nuove Spese nel nuovo Budget e **non chiude automaticamente** il
Progetto originario. La chiusura dell'origine è un'azione separata e manuale.

Le viste sono `Solo Questo Progetto` e `Intero Percorso`. `Intero Percorso` può sommare gli
Effettivi delle Continuazioni, ma mantiene la scomposizione annuale dei Previsti per evitare il
doppio conteggio degli importi riproposti.

## Contratti

### Effettivo gestionale automatico

Un Effettivo generato da Contratto è un costo contrattuale considerato certo ai fini del Budget;
non implica fattura, pagamento, registrazione contabile o esigibilità fiscale.

```text
Rinnovo di anno futuro              → Preventivo
Rinnovo di anno corrente o precedente → Effettivo
Ingresso dell'anno futuro nel corrente → aggiunge Effettivo e conserva il Preventivo
```

Sconti, maggiorazioni e differenze modificano l'Effettivo della relativa Riga contrattuale. La
causa non usa una tassonomia obbligatoria; la Nota la documenta ed è obbligatoria se l'operazione
produce una Rettifica su Budget Chiuso.

I Rinnovi Annuali appartengono interamente all'Anno della Data di Rinnovo, senza pro-rata. I
Contratti Mensili usano una Spesa Contrattuale Annuale con una Riga datata per ogni mensilità.

### Cessazione anticipata

La Data effettiva di cessazione interrompe le occorrenze future e conserva quelle precedenti. Gli
Effettivi già generati non vengono cancellati: ogni adeguamento è esplicito sulla relativa Riga e,
su Budget Chiuso, produce una Rettifica con Nota.

- nessun pro-rata giornaliero;
- per i mensili restano le mensilità con Data precedente alla cessazione;
- una Riga futura modificata manualmente appare nella Vista di Impatto e non viene eliminata senza
  una scelta esplicita;
- penali, rimborsi o differenze finali sono Effettivi positivi o negativi;
- la generazione resta idempotente tramite Source Key.

La Slice Contratti deve definire:

```text
POST /api/v1/contracts/{contract}/cessation-preview
POST /api/v1/contracts/{contract}/cease
```

## Plafond

### Natura e unicità

Il Plafond è un'allocazione gestionale assegnata a un Centro di Costo. Può essere rappresentato
come Spesa di Natura `Plafond`, ma non è un costo Effettivo. Esiste al massimo un Plafond corrente
per:

```text
Tenant + Anno Economico + Centro di Costo
```

Sono `DEPRECATED` più Plafond sulla stessa combinazione, il loro ordinamento, Quote multiple,
`coverage_allocations`, ripartizione di una Riga tra Plafond, consumo sequenziale e `Completa
Copertura` come azione separata.

### Righe additive dell'allocazione

Il valore cambia aggiungendo Righe allo stesso aggregato:

```text
Allocazione iniziale: +3.000
Aumento:              +1.000
Riduzione:              -500
Allocazione corrente:  3.500
```

Il modello logico usa una `ExpenseRow` della medesima Spesa Plafond con semantica dedicata, per
esempio `AllocationAdjustment`; il nome SQL e il discriminatore definitivo sono decisione della
Slice. Non introduce un'entità monetaria parallela: Spesa e relative Righe restano l'unica sorgente
monetaria. Ogni Riga conserva importo positivo o negativo, data, autore, Netto/IVA/Lordo e
l'eventuale natura di Rettifica dopo Approvazione o Chiusura. La Nota è obbligatoria quando la Riga
costituisce Rettifica o ricade in un altro caso già definito come motivato; durante Preparazione
resta disponibile ma non viene resa obbligatoria senza una decisione di prodotto. La Riga di
allocazione non riutilizza in modo ambiguo Stima, Preventivo o Effettivo.

### Copertura integrale della singola Riga

Una Riga di Spesa è interamente coperta dal Plafond oppure non coperta. Non esiste copertura
parziale. Se una Spesa contiene una parte coperta e una non coperta, usa due Righe distinte.

La Riga coperta contiene un solo riferimento esplicito al Plafond dello stesso Tenant, Anno
Economico e Centro di Costo. Non esiste una tabella di Quote multiple.

### Capienza insufficiente

Se il Plafond non dispone della capienza necessaria:

- il salvataggio è bloccato e non persiste alcuna mutazione parziale;
- nessuna copertura parziale o Sforamento è consentito;
- la UI mostra Allocazione, Disponibile, Importo della Riga e Importo Mancante;
- tutti gli input restano disponibili e la sezione Plafond è evidenziata;
- l'utente può aumentare l'allocazione con una nuova Riga, ridurre l'importo, dividere la Spesa in
  due Righe o rimuovere la copertura.

La precedente regola “consentire lo Sforamento con Avviso e motivazione” è `DEPRECATED`.

Una riduzione dell'allocazione che invaliderebbe coperture esistenti è bloccata con Vista di Impatto;
non rimuove né modifica silenziosamente le coperture.

### Valori e doppio conteggio

Il Motore Economico conserva separatamente `Allocazione`, `Copertura Prevista`, `Consumato` e
`Residuo`:

- l'Allocazione entra una sola volta nel Budget previsto/approvato;
- la pianificazione delle Spese coperte descrive l'uso previsto ma non aumenta il totale;
- gli Effettivi delle Spese coperte alimentano il Consumato;
- pianificazione ed Effettivo non vengono sommati tra loro;
- il calcolo del Disponibile per nuove coperture appartiene allo stesso Motore Economico.

Decisione confermata il 2026-08-12: il Disponibile diminuisce soltanto con gli Effettivi coperti.
Stime e Preventivi coperti alimentano la Copertura Prevista ma non prenotano capienza. Quindi:

```text
Disponibile = Allocazione corrente - somma degli Effettivi coperti correnti
```

L'inserimento o modifica di un Effettivo coperto applica il blocco atomico per capienza; Stima e
Preventivo possono dichiarare la copertura prevista anche quando la loro somma supera il
Disponibile, senza produrre Sforamento reale. La UI mostra separatamente Copertura Prevista e
Disponibile per rendere visibile il rischio futuro.

## Previsto Ricostruito

Il Previsto Ricostruito è ammesso esclusivamente per anni storici privi di Budget originario:

```text
Effettivo non Extra Budget → Previsto Ricostruito uguale all'Effettivo
Effettivo Extra Budget     → escluso dal Previsto Ricostruito
```

Previsto ed Effettivo coincidono per le Spese ordinarie. Lo scopo è distinguere retrospettivamente
Spese ordinarie ed Extra Budget, non misurare la qualità della pianificazione. La UI usa sempre
`Previsto Ricostruito dagli Effettivi`, non lo presenta come Budget Approvato e non inventa Data di
Approvazione o Approvatore.

## Cancellazione e Cestino

La cancellazione esclude la Spesa dal dataset economico corrente, dal Budget, dai Report e dal
Consumato del Plafond. La Spesa entra nel Cestino, conserva versioning, autore, data e Nota ed è
ripristinabile fino all'eliminazione definitiva.

Non sono richiesti relazioni obbligatorie con una sostitutiva, classificazioni contabili, workflow
di storno, entità `Superseded` o riconciliazioni automatiche di duplicati. Una Spesa con Effettivi
richiede sempre Nota; su Budget Chiuso cancellazione e Ripristino producono una Rettifica.

Una Spesa generata da Contratto cancellata non viene ricreata dal job successivo: la Source Key
resta soppressa oppure il Cestino conserva il controllo equivalente.

Il Cestino delle Spese è Tenant-bound e può comprendere più anni. Il brief approvato non estende il
restore ad altri aggregati e non definisce una scadenza né una politica di eliminazione definitiva:
nessuna Slice può introdurre un purge automatico o irreversibile delle Spese senza una nuova
decisione di prodotto. Il purge terminale degli Allegati già implementato resta una capacità
separata; la cancellazione della Spesa continua a rimuovere il payload binario secondo il contratto
corrente e il restore non promette di ricrearlo.

## Revisioni, snapshot e retention

Il comportamento descritto come baseline nella sezione iniziale è `VERIFIED CURRENT`. Il futuro
limite configurabile per Tenant sostituisce il valore costante operativo, ma non elimina gli
snapshot necessari alla storia annuale.

La retention non è una catena di diff che richiede tutte le `Version`: `snapshot_contents` è la
fonte persistita degli snapshot annuali. La Slice Revisioni deve provare, oltre il limite, che le
revisioni eccedenti non siano più accessibili operativamente, quelle visibili restino
ripristinabili e i Report storici a cutoff continuino a essere ricostruibili.

## Regole di supersessione

- `specs/010-projects`: baseline implementata per stage e classificazione; qualunque regola che
  attribuisce alla Fase effetti economici o chiude automaticamente l'origine è `DEPRECATED` come
  target.
- `specs/017-budget-annual-approval-and-expense-lifecycle`: baseline implementata corrente;
  Sforamento valido, Effettivo vincolato allo stesso anno civile, Variazioni su `approved_amount` e
  assenza di Riapertura sono `CONFLICT` con il target.
- `specs/018-dashboard-ux` e `specs/019-reporting-analytics`: baseline UI implementata; copy
  `Actual`, overrun e KPI di stato Spesa restano descrizione del corrente ma sono `DEPRECATED` per
  le future Slice.
- `specs/020-expense-workspace-ux`: baseline workspace implementata; singolo collegamento Plafond e
  regole correnti restano `VERIFIED CURRENT` fino alla Slice, ma non definiscono il target.
- `specs/021-tenant-general-settings`: impostazioni Tenant e Base ufficiale sono `VERIFIED CURRENT`;
  il futuro limite Revisioni configurabile è `PROPOSED TARGET`.

## Questioni aperte

Nessuna `OPEN QUESTION` di prodotto blocca la creazione delle Slice Verticali. I nomi SQL, la forma
interna degli snapshot e altri dettagli reversibili restano decisioni tecniche delle rispettive
Slice, non decisioni di prodotto da inventare qui.
