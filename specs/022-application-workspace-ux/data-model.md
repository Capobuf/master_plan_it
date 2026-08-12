# Modello Dati Target: Dominio Budget e Workspace Annuale

**Stato**: `PROPOSED TARGET — Logical design; not implemented`
**Data**: 2026-08-12
**Fonte di Verità di Prodotto**: `../BUDGET-DOMAIN-REFINEMENT.md`

## Scopo

Questo documento descrive il Modello Dati logico target. Non impone nomi SQL definitivi e non è una
Migration pronta da eseguire. Ogni Slice Verticale deve introdurre soltanto le parti necessarie al
proprio risultato utente, mantenendo il database ricostruibile da zero.

## Invarianti Trasversali

1. Ogni record di Dominio Tenant-bound possiede `tenant_id`; le relazioni tra record Tenant-bound
   usano vincoli che impediscono riferimenti tra Tenant differenti.
2. Gli Importi autorevoli sono decimali esatti a due cifre e attraversano l'API come stringhe.
3. Ogni Riga Economica conserva Netto, IVA e Lordo riconciliati; la Base Economica ufficiale decide
   quale componente alimenta i calcoli.
4. Esiste un solo Motore Economico. Budget, Dashboard, Report, Export e Drill-Down consumano la sua
   proiezione e non replicano formule.
5. Soltanto Righe correnti e non eliminate alimentano il dataset economico corrente.
6. La Spesa non possiede gli Stati Aperta/Chiusa. La Chiusura appartiene all'Anno Economico.
7. Una Spesa appartiene a un solo Anno Economico. Una Riga Ordinaria non può spostare la Spesa in un
   altro Anno attraverso la sola Data.
8. Per una Spesa di Progetto, l'Anno Economico deriva dall'Anno del Progetto; la Data della Riga può
   appartenere a un anno civile differente.
9. Extra Budget, Rettifica e Copertura Plafond non devono essere compressi in un singolo Stato. La
   compatibilità tra Extra Budget e Copertura resta `OPEN QUESTION` e conserva la regola corrente
   finché il proprietario non decide.
10. Delete e Restore sono mutazioni dell'intero aggregato, atomiche e versionate.
11. Il modello non rappresenta pagamenti, fatture, ratei/risconti, classificazioni fiscali o
    avanzamento operativo dei Progetti.

## Entità e Aggregati

### Tenant

Responsabile delle Impostazioni comuni e del perimetro di isolamento.

| Campo logico | Regola |
|---|---|
| Identità, Nome, Codice, Stato | Codice univoco di Piattaforma; Stato Attivo/Inattivo |
| Valuta | Una valuta operativa per il Tenant; nessuna conversione valutaria implicita |
| Base Economica | `Netto` o `Lordo`; default `Netto` |
| Base Bloccata Dal | Data/ora della prima Approvazione; dopo tale istante la Base non cambia più |
| Aliquota IVA Predefinita | Valore iniziale modificabile sulle Righe |
| Limite Revisioni | Intero positivo; default `10` Snapshot per aggregato |
| Preferenze e Quote Allegati | Conservare le capacità correnti pertinenti |

**Vincoli**:

- la prima Approvazione e il blocco della Base avvengono nella stessa transazione;
- annullare l'Approvazione non sblocca la Base;
- prima del blocco, un cambio Base rivalida tutte le Coperture Plafond esistenti usando i valori
  Netto/IVA/Lordo già conservati.

### Anno Economico

Rappresenta il contenitore annuale del Budget per un Tenant.

| Campo logico | Regola |
|---|---|
| Anno | Univoco nel Tenant |
| Stato Budget | `Preparazione`, `Approvato`, `Chiuso` |
| Storico Attivato Dal | Consente di caricare Anni passati ancora modificabili |
| Attivo | Controlla disponibilità operativa, non sostituisce lo Stato Budget |
| Versione Concorrente | Incrementata dalle mutazioni protette |

`Budget in Lavorazione` e `Budget Proposto` sono viste della fase `Preparazione`, non due ulteriori
Stati persistenti. `Budget Proposto` è la composizione presentabile calcolata nel momento corrente.

La riga `PlanningYear` è il guard di serializzazione stabile del dataset economico
`Tenant + Anno`. Ogni mutazione che può cambiare una Spesa/Riga corrente o creare Effettivi, Extra
Budget, Rettifiche o Chiusure acquisisce il lock del medesimo guard prima di leggere gli invarianti
e persistere. Approva, Chiudi, Riapri e Annulla usano lo stesso ordine; dopo il lock ricostruiscono
il dataset o rivalidano i quattro gruppi. Per una mutazione multi-Anno i guard sono acquisiti in
ordine crescente di chiave, evitando deadlock. `Versione Concorrente`, hash di preview e guard
database sono complementari, non alternativi.

**Transizioni**:

```text
Preparazione ──Approva──> Approvato ──Chiudi──> Chiuso
     ^                         |
     └──Annulla Approvazione───┘  solo senza Effettivi, Extra, Rettifiche o Chiusure storiche

Approvato <──Riapri── Chiuso       solo senza Rettifiche successive e con Nota
```

### Spesa

Aggregato economico principale e documento mostrato all'Utente.

| Campo logico | Regola |
|---|---|
| Tenant e Anno Economico | Obbligatori |
| Natura | `Ordinaria` o `Plafond` |
| Titolo e Note Generali | Note libere; prefissi automatici solo nei casi decisi |
| Centro di Costo | Obbligatorio |
| Progetto | Opzionale; se presente governa l'Anno Economico |
| Contratto | Opzionale; identifica una Spesa Contrattuale Annuale |
| Spesa Precedente | Opzionale; collega riproposte ordinarie tra Anni |
| Data di Registrazione | Data distinta dai timestamp tecnici e dalla Data della Spesa |
| Riga Previsionale Corrente | Zero o una Stima/Preventivo selezionata |
| Lock Version | Obbligatoria per update concorrenti |
| Metadati Cestino | Eliminata Da, Eliminata Il e Motivazione; nessuna scadenza di purge implicita |

Una Spesa può appartenere a un Progetto e derivare da un Contratto collegato al medesimo Progetto;
non deve esistere un vincolo XOR che renda impossibile questa provenienza.

### Riga di Spesa

| Campo logico | Regola |
|---|---|
| Tipo | `Stima`, `Preventivo`, `Effettivo` oppure `Variazione Allocazione`; quest'ultimo è ammesso soltanto nella Spesa Plafond |
| Posizione e Descrizione | Obbligatorie |
| Note di Riga | Obbligatorie per Extra Budget; disponibili negli altri casi |
| Fornitore | Opzionale secondo il tipo di inserimento |
| Quantità, Prezzo Unitario, Importo Inserito | Decimali esatti; quantità × prezzo oppure importo diretto |
| IVA Inclusa e Aliquota | Producono Netto, IVA e Lordo riconciliati |
| Data della Spesa | Obbligatoria quando il tipo o l'origine richiedono granularità temporale |
| Periodo | Facoltativo e informativo; non ripartisce automaticamente un Rinnovo Annuale |
| Extra Budget | Booleano; compatibilità con Copertura Plafond ancora `OPEN QUESTION` |
| Intento Post-Approvazione | `Voce Dimenticata` oppure `Nuova Esigenza` per una nuova decisione economica dopo l'Approvazione |
| Origine | Manuale, Contratto, Continuazione, Riproposta o altra origine esplicita |
| Source Key | Identificatore idempotente per generazioni automatiche |
| Metadati Override | Indicano che una Riga automatica è stata raffinata manualmente |
| Lock Version e Soft Delete | Obbligatori |
| Plafond di Copertura | Zero o un riferimento singolo compatibile; copertura sempre integrale |

**Regole di selezione**:

- Stima e Preventivo non si sommano tra loro nel Budget Proposto;
- il Preventivo Corrente prevale sulla Stima Corrente quando selezionato;
- più Effettivi si sommano;
- dopo l'Approvazione, Stime e Preventivi rimangono Valutazioni informative e non riscrivono il
  Previsto;
- soltanto Effettivi consumano realmente un Plafond;
- una parte coperta e una scoperta della stessa decisione sono modellate come due Righe.
- l'Intento Post-Approvazione è una scelta esplicita del mutatore, non viene inferito da titolo,
  Data o importo; `Voce Dimenticata` produce una Rettifica, `Nuova Esigenza` imposta Extra Budget,
  mentre un Effettivo relativo a una voce già prevista resta un Effettivo ordinario;
- dopo la Chiusura qualunque mutazione economica resta una Rettifica con Nota, anche quando conserva
  la classificazione Extra Budget della Riga.

### Plafond e Righe di Allocazione

Il Plafond è una Spesa di Natura Plafond, ma non è un costo Effettivo. Per la combinazione
`Tenant + Anno Economico + Centro di Costo` esiste al massimo un Plafond corrente.

| Campo logico | Regola |
|---|---|
| Plafond | Spesa dello stesso Tenant e Anno; compatibilità tra Centri di Costo `OPEN QUESTION` |
| Righe di Allocazione | `ExpenseRow` additive della Spesa Plafond con semantica logica `AllocationAdjustment` |
| Importo | Positivo per aumento, negativo per riduzione |
| Data e Autore | Obbligatori per ogni variazione dell'allocazione |
| Nota | Obbligatoria per Rettifica e negli altri casi motivati dal dominio; facoltativa in Preparazione |
| Netto, IVA e Lordo | Valori esatti coerenti con la Base ufficiale |
| Fase | Ordinaria, Rettifica dopo Approvazione o Rettifica dopo Chiusura |

**Vincoli**:

- una Riga Ordinaria possiede zero o un riferimento singolo al Plafond compatibile;
- se il riferimento è presente, l'intero Importo della Riga è coperto;
- il Disponibile deve essere sufficiente prima di persistere un Effettivo coperto o una riduzione
  dell'Allocazione, non prima di salvare Stime/Preventivi coperti;
- capienza insufficiente nei due casi bloccanti precedenti ferma atomicamente e non salva dati
  parziali;
- una riduzione che invaliderebbe coperture esistenti è bloccata da una Vista di Impatto;
- create, Restore, collegamento di copertura e variazione di Allocazione serializzano la
  combinazione stabile `Tenant + Anno + Centro di Costo` nella stessa transazione, bloccano il
  Plafond e le Righe coperte pertinenti e ricalcolano la capienza dopo il lock; il solo
  `lock_version` della Spesa non impedisce due coperture concorrenti;
- l'unicità del Plafond corrente è garantita anche dal database; Soft Delete libera lo slot e
  Restore lo riacquisisce soltanto dopo la stessa verifica atomica di unicità e capienza;
- `Copertura Prevista` somma Stime/Preventivi correnti coperti ma non prenota capienza;
  `Consumato` somma soltanto gli Effettivi coperti e `Disponibile = Allocazione - Consumato`;
- soltanto create/update/Restore di un Effettivo coperto e riduzioni dell'Allocazione applicano il
  blocco per Disponibile insufficiente; una previsione coperta può superare il Disponibile e viene
  mostrata come rischio futuro, non come Sforamento reale;
- non esistono `CoverageAllocation`, Quote multiple, ordinamento tra Plafond o Sforamento.

### Progetto

| Campo logico | Regola |
|---|---|
| Anno del Progetto | Obbligatorio e governa le Spese collegate |
| Centro di Costo, Titolo, Descrizione | Informazioni principali |
| Fase del Progetto | `Idea`, `Proposto`, `Approvato`, `Rinviato`, `Rifiutato`; solo descrittiva |
| Chiuso Il | Nullo per Progetto Aperto |
| Progetto Precedente | Collegamento opzionale alla Continuazione originaria |
| Metadati Cestino e Lock | Come gli altri aggregati |

**Azioni di Dominio**:

- `Progetto Precedente` è Tenant-bound e univoco tra i successori: ogni Progetto può avere al
  massimo una sola Continuazione successiva;
- il predecessore appartiene all'Anno immediatamente precedente
  (`anno continuazione = anno predecessore + 1`) e la creazione verifica l'intera ascendenza;
  self-link, salti di Anno e cicli sono vietati;

- senza Effettivi: Spostamento atomico del Progetto e delle Spese nel nuovo Anno;
- con Effettivi: creazione di una Continuazione nel nuovo Anno; la chiusura dell'origine resta
  un'azione manuale separata;
- per ogni Spesa candidata la preview può suggerire una nuova Riga Stima nella Spesa della
  Continuazione pari a `max(Pianificazione Corrente della Spesa − suoi Effettivi, 0)`; il valore è
  derivato esclusivamente da `ExpenseRow`, resta confermabile/modificabile e il Progetto non
  possiede importi propri;
- la Fase non attiva mai automaticamente queste azioni.

### Contratto, Termine e Scadenza

#### Contratto

Conserva Fornitore, Centro di Costo, eventuale Progetto, Titolo, Descrizione, Data di Inizio,
Rinnovo Automatico, termine di preavviso, Data di Cessazione e metadati di Cestino/Lock.

Quando un'Occorrenza è collegata a un Progetto, l'Anno del Progetto deve coincidere con l'Anno della
Data dell'Occorrenza. Un mismatch è rifiutato: il collegamento deve usare la Continuazione
pertinente o restare nullo, senza cambiare silenziosamente l'attribuzione annuale.

#### Termine Contrattuale

Definisce un intervallo di validità, Periodicità `Annuale` o `Mensile`, Importo per occorrenza,
Quantità/Prezzo opzionali, IVA e origine manuale/automatica. Termini sovrapposti che produrrebbero due
Importi per la stessa Scadenza sono invalidi.

#### Scadenza Contrattuale

È una proiezione deterministica dei Termini, identificata da una chiave base di Occorrenza stabile.
Ogni Riga generata usa inoltre un ruolo `Preventivo` o `Effettivo`; la Source Key persistita è unica
per `Occorrenza + Ruolo`, così l'ingresso nell'Anno aggiunge l'Effettivo senza sovrascrivere il
Preventivo e ogni sincronizzazione resta idempotente. Presenta Data, Importo, termine di preavviso,
stato di generazione e collegamento alla Riga di Spesa. Persistono soltanto la Riga generata e le
eccezioni esplicite necessarie a escludere una Scadenza.

**Generazione**:

- unicità di una Spesa Contrattuale per `Tenant + Contratto + Anno Economico`;
- Periodicità Annuale: una Riga per la Data di Rinnovo, interamente attribuita al relativo Anno;
- Periodicità Mensile: una Riga per ogni Scadenza Mensile dell'Anno, tutte nella stessa Spesa;
- Anno futuro: Righe Preventivo;
- Anno corrente o precedente: Righe Effettivo;
- ingresso nell'Anno: aggiunta idempotente degli Effettivi corrispondenti, senza eliminare i
  Preventivi storici;
- Cessazione o Rinnovo Automatico disattivato impediscono soltanto generazioni future;
- gli Effettivi già generati restano; una modifica è esplicita sulla Riga;
- la Cessazione mensile conserva le scadenze precedenti e non genera quelle successive;
- una Riga futura modificata manualmente richiede una scelta esplicita nella Vista di Impatto;
- generazione o modifica in Anno Chiuso produce Rettifiche con Nota obbligatoria.

### Approvazione del Budget

Aggregato immutabile composto da intestazione e Voci di Snapshot.

| Campo logico | Regola |
|---|---|
| Tenant, Anno, Data, Approvatore | Obbligatori |
| Base Economica | Copiata dal Tenant e immutabile |
| Totali | Netto, IVA, Lordo e Totale nella Base ufficiale |
| Stato | Attiva o Annullata, con autore/data/Nota dell'annullamento |
| Correlation ID | Unico per idempotenza |

Ogni Voce conserva almeno Spesa/Riga di origine, Natura, Centro di Costo, Progetto, Contratto,
Importi e classificazioni necessari a ricostruire il Previsto senza leggere dati mutabili.

L'Approvazione comprende l'intero Budget Proposto in una transazione. Non aggiorna un campo
`approved_amount` mutabile sulla Spesa.

L'Approvazione `Attiva` è l'unica annullabile. La proiezione di blocco è sempre limitata allo stesso
Tenant/Anno e comprende quattro insiemi, senza una tabella o un tipo evento residuale:

| Gruppo | Presenza bloccante |
|---|---|
| Effettivi | Ogni Riga Effettivo positiva/negativa, manuale/contrattuale, inclusa la Riga o Spesa nel Cestino |
| Extra Budget | Ogni Spesa o Riga Extra Budget, inclusa quella eliminata logicamente |
| Rettifiche | Ogni Rettifica dopo Approvazione o dopo Chiusura |
| Chiusure | Ogni evento/snapshot di Chiusura già eseguito, anche se non più corrente dopo Riapertura |

Un Annullamento consentito marca l'Approvazione `Annullata` con autore, data e Nota, riporta il
Budget in Preparazione e crea Revisione e Audit. Non elimina snapshot o contenuto, non riusa la
fotografia per una successiva Approvazione e non modifica `Base Bloccata Dal`. La preview è una
proiezione read-only: la mutazione acquisisce il Lock Version e ricalcola i quattro gruppi nella
stessa transazione della transizione di stato.

### Rettifica

Registro canonico dell'effetto economico successivo all'Approvazione o alla Chiusura.

| Campo logico | Regola |
|---|---|
| Anno e origine | Collegamento alla Spesa/Riga e all'evento che l'ha prodotta |
| Momento e autore | Obbligatori |
| Nota | Obbligatoria nei casi stabiliti dal Dominio |
| Importo precedente, nuovo e delta | Netto, IVA, Lordo e valore nella Base ufficiale |
| Fase di origine | Dopo Approvazione o dopo Chiusura |

La Rettifica modifica il valore rappresentato del medesimo Budget Approvato/Chiuso; non crea un
`Budget Finale Rettificato`. Extra Budget rimane una classificazione distinta.

### Chiusura del Budget

Snapshot immutabile del Budget Finale con Tenant, Anno, Data, Utente, Base, Totali e Riepilogo delle
situazioni lasciate irrisolte. Una Chiusura può essere resa non corrente dalla Riapertura, ma non
viene cancellata. La Riapertura richiede Nota, crea una Revisione ed è vietata dopo Rettifiche
successive. Una nuova Chiusura crea un nuovo Snapshot.

### Cestino

Il Cestino è una proiezione Tenant-bound dei metadati Soft Delete della Spesa, non una copia
polimorfica dei documenti. Ogni Spesa recuperabile conserva almeno `deleted_at`, `deleted_by` e
motivazione. Non esiste `purge_after` senza una futura decisione di prodotto.

Il Ripristino:

- mantiene la stessa identità;
- coinvolge l'intero aggregato;
- è bloccato se una dipendenza obbligatoria è ancora nel Cestino;
- ricalcola il Budget corrente oppure produce una Rettifica se l'Anno è Chiuso;
- crea una Revisione.

Per una Spesa generata da Contratto, il Cestino conserva anche la soppressione della Source Key o
un controllo equivalente; la generazione idempotente non può ricreare l'aggregato cancellato.

### Cronologia delle Versioni

Riusa il sistema di Revisioni corrente. Uno Snapshot logico raggruppa tutte le modifiche dello stesso
aggregato e conserva autore, momento, operazione, Correlation ID e rappresentazione necessaria al
rendering del documento. Il Restore applica lo Snapshot come nuova mutazione e nuova Revisione.

Gli Allegati non vengono duplicati negli Snapshot. La cancellazione della Spesa mantiene il purge
terminale binario `VERIFIED CURRENT`; il Cestino può mostrare metadati storici minimizzati, ma il
Restore della Spesa e una Revisione non ricreano file eliminati.

`VERIFIED CURRENT`: ogni `RevisionBatchItem` conserva `snapshot_contents`; il limite operativo
costante controlla history/compare/restore, mentre la manutenzione può eliminare le sole `Version`
tecniche ridondanti. Batch, item, audit e storia annuale restano. `PROPOSED TARGET`: il limite
diventa configurabile per Tenant senza cambiare questa indipendenza.

### Preferenza di Vista

Una configurazione personale identificata da `Tenant + Utente + View Key`, con colonne visibili,
ordine, dimensione pagina e opzioni specifiche ammesse dalla vista. La Preferenza Importi controlla
la visibilità di Netto/IVA/Lordo; la Preferenza Guida controlla le descrizioni contestuali. Nessuna
Preferenza modifica dati o formule.

### Evidenza Scheduler

Record tecnico minimale per ciascun job osservabile: nome stabile, ultima esecuzione iniziata,
ultima esecuzione completata, esito e messaggio diagnostico non sensibile. In assenza di un record
attendibile la UI mostra `Stato non verificabile`.

## Proiezione Economica Autorevole

Il Motore riceve:

- Tenant e Base Economica;
- Anno Economico;
- Righe correnti con Importi e classificazioni;
- riferimenti singoli di Copertura e Righe additive dell'Allocazione;
- Snapshot di Approvazione/Chiusura e Rettifiche quando la vista lo richiede.

Produce almeno:

- Budget Proposto;
- Previsto;
- Effettivo;
- Extra Budget;
- Rettifiche;
- per ogni Plafond: Allocazione, Copertura Prevista, Consumato e Disponibile;
- raggruppamenti per Centro di Costo, Progetto, Contratto, Fornitore e Spesa;
- progressione mensile soltanto per Righe con Data realmente attribuibile a un Mese.

Le Query possono selezionare, autorizzare, ordinare, raggruppare identificatori e paginare, ma non
ridefiniscono formule o riconciliazioni.

## Ricostruzione Greenfield

- Il proprietario ha confermato esplicitamente il 2026-08-12 che non esistono dati da preservare;
  è consentito sostituire o consolidare le Migrazioni correnti.
- Non sono richiesti backup, import dei record legacy o mapping semantici da `open/closed`,
  `variation` e Plafond legacy.
- `migrate:fresh --seed` deve produrre uno schema valido e dati demo coerenti negli ambienti di
  sviluppo/test protetti; il comando deve essere rifiutato negli altri ambienti.
- I vincoli importanti devono essere provati su MySQL reale, non soltanto in memoria.
- Gli identificatori tecnici legacy possono rimanere temporaneamente soltanto all'interno della
  singola Slice e devono essere rimossi prima che la Slice sia dichiarata completata.
