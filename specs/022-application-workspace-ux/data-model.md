# Modello Dati Target: Dominio Budget e Workspace Annuale

**Stato**: Design di Programma per Slice Verticali  
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
9. Extra Budget, Rettifica e Copertura Plafond sono dimensioni indipendenti quando il Dominio lo
   consente; non devono essere compressi in un singolo Stato.
10. Delete e Restore sono mutazioni dell'intero aggregato, atomiche e versionate.

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
| Stato Budget | `Preparazione`, `Approvato`, `Finale` |
| Storico Attivato Dal | Consente di caricare Anni passati ancora modificabili |
| Attivo | Controlla disponibilità operativa, non sostituisce lo Stato Budget |
| Versione Concorrente | Incrementata dalle mutazioni protette |

`Budget in Lavorazione` e `Budget Proposto` sono viste della fase `Preparazione`, non due ulteriori
Stati persistenti. `Budget Proposto` è la composizione presentabile calcolata nel momento corrente.

**Transizioni**:

```text
Preparazione ──Approva──> Approvato ──Chiudi──> Finale
     ^                         |
     └──Annulla Approvazione───┘  solo senza eventi dipendenti

Approvato <──Riapri── Finale       solo senza Rettifiche successive
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
| Data di Registrazione | Data di Dominio distinta dai timestamp tecnici |
| Riga Previsionale Corrente | Zero o una Stima/Preventivo selezionata |
| Lock Version | Obbligatoria per update concorrenti |
| Metadati Cestino | Eliminata Da, Eliminata Il, Motivazione, Eliminazione Definitiva Dal |

Una Spesa può appartenere a un Progetto e derivare da un Contratto collegato al medesimo Progetto;
non deve esistere un vincolo XOR che renda impossibile questa provenienza.

### Riga di Spesa

| Campo logico | Regola |
|---|---|
| Tipo | `Stima`, `Preventivo`, `Effettivo` |
| Posizione e Descrizione | Obbligatorie |
| Note di Riga | Obbligatorie per Extra Budget; disponibili negli altri casi |
| Fornitore | Opzionale secondo il tipo di inserimento |
| Quantità, Prezzo Unitario, Importo Inserito | Decimali esatti; quantità × prezzo oppure importo diretto |
| IVA Inclusa e Aliquota | Producono Netto, IVA e Lordo riconciliati |
| Data della Spesa | Obbligatoria quando il tipo o l'origine richiedono granularità temporale |
| Periodo | Facoltativo e informativo; non ripartisce automaticamente un Rinnovo Annuale |
| Extra Budget | Booleano indipendente dalla Copertura |
| Origine | Manuale, Contratto, Continuazione, Riproposta o altra origine esplicita |
| Source Key | Identificatore idempotente per generazioni automatiche |
| Metadati Override | Indicano che una Riga automatica è stata raffinata manualmente |
| Lock Version e Soft Delete | Obbligatori |

**Regole di selezione**:

- Stima e Preventivo non si sommano tra loro nel Budget Proposto;
- il Preventivo Corrente prevale sulla Stima Corrente quando selezionato;
- più Effettivi si sommano;
- dopo l'Approvazione, Stime e Preventivi rimangono Valutazioni informative e non riscrivono il
  Previsto;
- soltanto Effettivi consumano realmente un Plafond.

### Quota di Copertura Plafond

Relazione esplicita tra una Riga Ordinaria e una Spesa Plafond.

| Campo logico | Regola |
|---|---|
| Riga Coperta | Stima, Preventivo o Effettivo non eliminato |
| Plafond | Spesa di Natura Plafond dello stesso Tenant, Anno e Centro di Costo |
| Posizione | Mantiene l'ordine scelto dall'Utente |
| Quota Netta, IVA e Lorda | Valori esatti riconciliati; consentono un cambio Base prima del blocco |
| Creata/Modificata Da | Audit dell'allocazione |

**Vincoli**:

- una Riga può avere zero o più Quote;
- se almeno una Quota è presente, la loro somma nella Base ufficiale deve coincidere con l'intero
  Importo della Riga; in caso contrario il salvataggio è bloccato e l'input resta in Modifica;
- `Extra Budget` non impedisce la Copertura;
- lo Sforamento della Capienza non blocca, ma richiede conferma e aggiunge la motivazione alle Note
  Generali con il prefisso `Note Sforamento Plafond:`;
- il cambio Stima → Preventivo → Effettivo produce una proposta di riallocazione, mai una modifica
  silenziosa.

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

- senza Effettivi: Spostamento atomico del Progetto e delle Spese nel nuovo Anno;
- con Effettivi: chiusura dell'origine e creazione di una Continuazione nel nuovo Anno;
- l'Importo da Riproporre è `max(Valutazione Corrente − Totale Effettivo, 0)`, suggerito e sempre
  confermabile/modificabile dall'Utente;
- la Fase non attiva mai automaticamente queste azioni.

### Contratto, Termine e Scadenza

#### Contratto

Conserva Fornitore, Centro di Costo, eventuale Progetto, Titolo, Descrizione, Data di Inizio,
Rinnovo Automatico, termine di preavviso, Data di Cessazione e metadati di Cestino/Lock.

#### Termine Contrattuale

Definisce un intervallo di validità, Periodicità `Annuale` o `Mensile`, Importo per occorrenza,
Quantità/Prezzo opzionali, IVA e origine manuale/automatica. Termini sovrapposti che produrrebbero due
Importi per la stessa Scadenza sono invalidi.

#### Scadenza Contrattuale

È una proiezione deterministica dei Termini, identificata da una Source Key stabile. Presenta Data,
Importo, termine di preavviso, stato di generazione e collegamento alla Riga di Spesa. Persistono
soltanto la Riga generata e le eccezioni esplicite necessarie a escludere una Scadenza.

**Generazione**:

- unicità di una Spesa Contrattuale per `Tenant + Contratto + Anno Economico`;
- Periodicità Annuale: una Riga per la Data di Rinnovo, interamente attribuita al relativo Anno;
- Periodicità Mensile: una Riga per ogni Scadenza Mensile dell'Anno, tutte nella stessa Spesa;
- Anno futuro: Righe Preventivo;
- Anno corrente o precedente: Righe Effettivo;
- ingresso nell'Anno: aggiunta idempotente degli Effettivi corrispondenti, senza eliminare i
  Preventivi storici;
- Cessazione o Rinnovo Automatico disattivato impediscono soltanto generazioni future;
- generazione in Anno Finale produce Rettifiche con Nota obbligatoria.

### Approvazione del Budget

Aggregato immutabile composto da intestazione e Voci di Snapshot.

| Campo logico | Regola |
|---|---|
| Tenant, Anno, Data, Approvatore | Obbligatori |
| Base Economica | Copiata dal Tenant e immutabile |
| Totali | Netto, IVA, Lordo e Totale nella Base ufficiale |
| Stato | Attiva o Annullata, con autore/data dell'annullamento |
| Correlation ID | Unico per idempotenza |

Ogni Voce conserva almeno Spesa/Riga di origine, Natura, Centro di Costo, Progetto, Contratto,
Importi e classificazioni necessari a ricostruire il Previsto senza leggere dati mutabili.

L'Approvazione comprende l'intero Budget Proposto in una transazione. Non aggiorna un campo
`approved_amount` mutabile sulla Spesa.

### Rettifica

Registro canonico dell'effetto economico successivo all'Approvazione o alla Chiusura.

| Campo logico | Regola |
|---|---|
| Anno e origine | Collegamento alla Spesa/Riga e all'evento che l'ha prodotta |
| Momento e autore | Obbligatori |
| Nota | Obbligatoria nei casi stabiliti dal Dominio |
| Importo precedente, nuovo e delta | Netto, IVA, Lordo e valore nella Base ufficiale |
| Fase di origine | Dopo Approvazione o dopo Chiusura |

La Rettifica modifica il valore rappresentato del medesimo Budget Approvato/Finale; non crea un
`Budget Finale Rettificato`. Extra Budget rimane una classificazione distinta.

### Chiusura del Budget

Snapshot immutabile del Budget Finale con Tenant, Anno, Data, Utente, Base, Totali e Riepilogo delle
situazioni lasciate irrisolte. Una Chiusura può essere resa non corrente dalla Riapertura, ma non
viene cancellata. Una nuova Chiusura crea un nuovo Snapshot.

### Cestino

Il Cestino è una proiezione unificata dei metadati Soft Delete presenti sugli aggregati, non una
copia polimorfica dei documenti. Ogni aggregato recuperabile conserva `deleted_at`, `deleted_by`,
motivazione e `purge_after = deleted_at + 12 mesi`.

Il Ripristino:

- mantiene la stessa identità;
- coinvolge l'intero aggregato;
- è bloccato se una dipendenza obbligatoria è ancora nel Cestino;
- ricalcola il Budget corrente oppure produce una Rettifica se l'Anno è Finale;
- crea una Revisione.

### Cronologia delle Versioni

Riusa il sistema di Revisioni corrente. Uno Snapshot logico raggruppa tutte le modifiche dello stesso
aggregato e conserva autore, momento, operazione, Correlation ID e rappresentazione necessaria al
rendering del documento. Il Restore applica lo Snapshot come nuova mutazione e nuova Revisione.

Gli Allegati non vengono duplicati negli Snapshot; rimangono recuperabili con l'aggregato nel
Cestino, ma una Revisione non ricrea file eliminati.

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
- Quote di Copertura;
- Snapshot di Approvazione/Chiusura e Rettifiche quando la vista lo richiede.

Produce almeno:

- Budget Proposto;
- Previsto;
- Effettivo;
- Extra Budget;
- Rettifiche;
- per ogni Plafond: Impegnato, Copertura Prevista, Consumato, Residuo e Sforamento;
- raggruppamenti per Centro di Costo, Progetto, Contratto, Fornitore e Spesa;
- progressione mensile soltanto per Righe con Data realmente attribuibile a un Mese.

Le Query possono selezionare, autorizzare, ordinare, raggruppare identificatori e paginare, ma non
ridefiniscono formule o riconciliazioni.

## Ricostruzione Greenfield

- È consentito sostituire o consolidare le Migrazioni correnti.
- Non sono richiesti backup, import dei record legacy o mapping semantici da `open/closed`,
  `variation` e singolo Plafond.
- `migrate:fresh --seed` deve produrre uno schema valido e dati demo coerenti.
- I vincoli importanti devono essere provati su MySQL reale, non soltanto in memoria.
- Gli identificatori tecnici legacy possono rimanere temporaneamente soltanto all'interno della
  singola Slice e devono essere rimossi prima che la Slice sia dichiarata completata.
