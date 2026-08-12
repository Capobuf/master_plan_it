# Raffinamento del Dominio Budget

**Stato**: Documento di Lavoro

**Creato**: 2026-08-12

**Scopo**: Consolidare progressivamente decisioni, comportamenti e Flussi UX del Dominio Budget prima di trasformarli in Feature, Piano di Implementazione o modifica del Codice.

Questo documento descrive il comportamento desiderato del Prodotto. Non rappresenta una Feature da
implementare, non confronta il comportamento con il Codice corrente e non costituisce ancora un Piano
Tecnico.

## Chiarimenti

### Sessione 2026-08-12

- D: Cosa accade quando un Effettivo supera il Residuo del Plafond? → R: Il Software mostra un
  Avviso, richiede una motivazione, registra comunque l'Effettivo e riporta la motivazione nelle Note
  Generali con il prefisso `Note Sforamento Plafond:`.
- D: Una singola Spesa può utilizzare più Plafond dello stesso Centro di Costo? → R: Sì, tramite
  una Copertura Ripartita Assistita che propone gli Importi di Copertura e permette all'Utente di
  confermarli o modificarli.
- D: La Spesa deve essere suddivisa quando usa due Plafond? → R: No, la Spesa e le sue Righe
  economiche restano uniche; viene parzializzata soltanto la Copertura tra i diversi Plafond.
- D: Come si aggiorna la ripartizione quando l'Effettivo differisce dalla Stima o dal Preventivo? →
  R: Il Software mantiene l'ordine dei Plafond, adegua la Quota necessaria e sottopone la nuova
  ripartizione alla conferma dell'Utente.
- D: Una Spesa può essere registrata con una Copertura Plafond soltanto parziale? → R: No. Se viene
  scelta la Copertura Plafond, la somma delle Quote deve coincidere con l'intero importo della Riga;
  in alternativa la Spesa viene registrata senza Copertura Plafond.
- D: Cosa accade quando l'Utente tenta di salvare una Copertura Plafond incompleta? → R: Il
  Software mostra un Avviso, non salva e mantiene la Spesa in Modifica con i dati inseriti, affinché
  l'Utente possa correggere la Copertura.
- D: Una Rettifica successiva all'approvazione resta fuori dal Budget Approvato? → R: No. La
  Rettifica entra nel valore rappresentato del Budget Approvato e la relativa Riga resta visibilmente
  identificata come `Rettifica`; le Spese Extra Budget rimangono invece separate.
- D: La Spesa necessita degli Stati `Aperta` e `Chiusa`? → R: No. La Spesa può ricevere più
  Effettivi senza essere riaperta; la chiusura riguarda il Budget annuale e non la singola Spesa.
- D: Stime e Preventivi devono contribuire ai totali del Report? → R: Durante la preparazione, la
  Riga Previsionale Corrente contribuisce necessariamente al Budget Proposto. Dopo l'approvazione,
  il Report Principale confronta Budget Approvato ed Effettivo e uno Switch permette di sovrapporre
  le Valutazioni senza includerle nei totali.
- D: Cosa richiede inizialmente la selezione `Extra Budget`? → R: Soltanto la Nota già presente
  sulla Riga, resa obbligatoria per spiegare la motivazione. Richieste via Email, conferme e
  Notifiche sono rinviate a una seconda passata.
- D: Come viene registrata una Fattura o Nota di Credito relativa a un anno già chiuso? → R:
  L'Utente la inserisce come una normale Spesa indicando la data dell'anno precedente; il Software la
  classifica automaticamente come `Rettifica` e rende obbligatoria la Nota.
- D: Il Software deve classificare le cause degli scostamenti? → R: No. Calcola e mostra soltanto
  le differenze numeriche, senza cause, Tag o Workflow dedicati.
- D: Come deve funzionare il Confronto Annuale? → R: La pagina sovrappone due Serie, inizialmente
  proposte come Anno Precedente e Anno Attuale, in Grafici Predefiniti e offre una Vista di Dettaglio
  tabellare nella stessa pagina. Il click su un dato applica i Filtri contestuali e scorre
  automaticamente al dettaglio.
- D: Le serie del Confronto Annuale sono fisse? → R: No. Il Software propone una coppia contestuale,
  ma l'Utente può sempre scegliere autonomamente quali Anni e Valori confrontare, anche tra anni
  non consecutivi.
- D: A quale Budget appartengono le Spese di un Progetto che prosegue su più anni? → R: Tutte le
  Spese ereditano l'Anno del Progetto e concorrono al relativo Budget, anche quando la loro Data
  della Spesa appartiene a un anno successivo.
- D: Una Spesa di un Progetto registrata dopo la Chiusura del relativo Anno Economico è una
  Rettifica anche se la Data della Spesa appartiene a un anno successivo? → R: Sì. Modifica il
  Budget chiuso dell'Anno del Progetto, conserva la Data reale e richiede la Nota della Rettifica.
- D: Come viene portato all'anno successivo un Progetto con Spese Effettive? → R: Il Progetto
  originario viene chiuso nel proprio anno e viene creato un nuovo Progetto per l'anno successivo,
  collegato come Continuazione. Le Spese con Effettivi restano nel Progetto originario; il nuovo
  Progetto entra nel Budget Proposto dell'anno successivo.
- D: Come viene determinato l'Importo da Riproporre nella Continuazione? → R: Il Software suggerisce
  il residuo della Valutazione Corrente rispetto al Totale Effettivo; l'Utente deve confermare o
  modificare l'importo prima che venga creata la nuova Spesa nel Budget Proposto.
- D: Con quale tipo di Riga nasce l'Importo da Riproporre? → R: Nasce come Stima della nuova Spesa
  e contribuisce al Budget Proposto; con l'approvazione, il relativo valore viene fissato come
  Previsto senza trasformare o eliminare la Stima.
- D: A quale Anno Economico appartiene una Spesa generata da un Contratto? → R: All'anno della Data
  di Rinnovo prevista dai Termini, senza ripartizioni proporzionali per il periodo di servizio che
  può estendersi nell'anno successivo.
- D: Quando una Spesa di Rinnovo acquisisce un Effettivo? → R: Se l'Anno Economico del Rinnovo è
  quello corrente o un anno precedente, il Contratto crea l'Effettivo; per un anno futuro crea un
  Preventivo, che resta in Cronologia quando all'inizio del relativo anno viene aggiunto
  automaticamente l'Effettivo.
- D: Cosa accade quando il Contratto usa il Rinnovo Automatico? → R: Per ogni Anno Economico già
  esistente nel Software, il Contratto crea la Riga di Rinnovo mancante basandosi sulla precedente,
  senza duplicare o sovrascrivere Righe già presenti.
- D: Cosa accade se il Contratto inizia in un Anno Economico già chiuso? → R: Genera la Spesa con
  un Effettivo classificato come Rettifica; se il Rinnovo Automatico è attivo, applica la stessa
  regola a ogni Rinnovo ricadente negli anni chiusi esistenti.
- D: Cosa accade a una Spesa Ordinaria senza Effettivi alla fine dell'anno? → R: Non viene riportata
  automaticamente. Nel Budget dell'anno successivo compare in una Vista derivata dalla quale
  l'Utente può riproporla, registrare un Effettivo, eliminarla oppure aprirne il dettaglio.
- D: Una Spesa con Effettivi può essere eliminata? → R: Sì. L'eliminazione è logica, conserva la
  Cronologia ma esclude la Spesa e tutti i suoi Effettivi dai calcoli correnti, compreso il Consumato
  dei Plafond.
- D: La Nota è obbligatoria per eliminare una Spesa con Effettivi? → R: Sì, anche quando l'Anno
  Economico è ancora aperto. Senza Nota il Software non salva e mantiene la Spesa in Modifica.
- D: Le situazioni non consolidate impediscono la Chiusura del Budget? → R: No. Il Software mostra
  un Riepilogo di Chiusura con spiegazioni e azioni contestuali per ogni elemento, ma l'Utente può
  confermare la Chiusura anche senza risolverli tutti.
- D: Il Residuo di un Plafond può essere riproposto nell'anno successivo? → R: Sì, tramite un'azione
  esplicita che crea un nuovo Plafond con il Residuo come Importo suggerito e modificabile, senza
  alterare il Plafond originario.
- D: L'Approvazione riguarda l'intero Budget Proposto o singole Spese? → R: L'intero Budget Proposto
  con un'unica operazione. Prima dell'approvazione, l'Utente può modificare manualmente la
  composizione generata dal Software aggiungendo o rimuovendo Spese, Contratti e Progetti.
- D: Cosa comporta escludere un Contratto dal Budget? → R: Il Contratto non viene Disattivato, ma
  viene disattivato il Rinnovo Automatico; pertanto non può più generare nuove Righe di Rinnovo o
  nuove Spese. Per il Progetto, l'Esclusione Annuale non comporta invece la Chiusura automatica.
- D: Cosa accade alle Righe e alle Spese future già generate quando un Contratto viene escluso? →
  R: Tutte quelle prive di Effettivi dall'Anno Economico escluso in avanti vengono escluse logicamente;
  gli Effettivi e la Cronologia precedente restano invariati.
- D: Come vengono trattati gli Anni Storici creati durante l'avvio di un nuovo Tenant? → R: Nascono
  modificabili anche se cronologicamente passati e vengono chiusi esplicitamente dall'Utente dopo la
  ricostruzione; soltanto da quel momento le modifiche successive diventano Rettifiche.
- D: Come si costruisce il confronto storico quando non esisteva un Budget Approvato? → R: Gli
  Effettivi delle Spese non marcate Extra Budget formano un Previsto Ricostruito; gli Effettivi Extra
  Budget restano fuori dalla base e rappresentano ciò che non era previsto.
- D: Come viene trattata una modifica della classificazione Extra Budget dopo la Chiusura di un Anno
  con Previsto Ricostruito? → R: La classificazione cambia senza Rettifica e senza Nota; il Software
  ricalcola la composizione e registra automaticamente la modifica nella Cronologia.
- D: Il Previsto Ricostruito deve poter essere sostituito successivamente da un Budget originario
  recuperato? → R: No. La casistica non appartiene al Dominio e non viene previsto alcun Flusso di
  sostituzione o doppia base di confronto.
- D: Una Spesa Ordinaria può contenere Effettivi di Anni Economici differenti? → R: No. Ogni Spesa
  appartiene a un solo Anno Economico; la parte attribuita a un anno successivo richiede una nuova
  Spesa collegata alla precedente.
- D: Cosa accade quando l'Utente tenta di registrare un Effettivo di un altro anno? → R: Il
  Software non salva la Riga sulla Spesa originaria e propone la creazione assistita di una nuova
  Spesa collegata, mantenendo i dati già inseriti.
- D: Come viene trattata la modifica del Centro di Costo dopo la Chiusura? → R: Come semplice
  Riclassificazione analitica, senza Rettifica e senza Nota; i Report vengono ricalcolati e la
  Cronologia conserva Centro precedente, Centro nuovo, Data e Utente.
- D: Quali informazioni possono essere modificate dopo la Chiusura come Riclassificazioni
  analitiche? → R: Centro di Costo, Fornitore, Descrizione e Note Generali, sempre con Cronologia ma
  senza Rettifica o Nota obbligatoria.
- D: Una Spesa eliminata deve poter essere recuperata? → R: Sì. L'Eliminazione sposta la Spesa nel
  Cestino, dal quale può essere Ripristinata fino all'Eliminazione Definitiva.
- D: Come deve essere presentata la Cronologia delle Versioni? → R: Con un Flusso simile alla
  Cronologia delle Versioni di Google Drive: l'Utente seleziona uno Snapshot e vede la normale Vista
  della Spesa in sola lettura, con i Campi differenti dallo stato corrente messi in evidenza.
- D: Per quanto tempo un elemento resta recuperabile nel Cestino? → R: Per 12 mesi dalla propria
  Data di Eliminazione. Lo Scheduler verifica periodicamente le scadenze e non usa una data annuale
  comune.
- D: Il Cestino deve essere distinto per Entità o per Anno? → R: No. Ogni Tenant possiede un unico
  Cestino, Multi-Entità e Multi-Anno, che raccoglie Spese, Contratti, Progetti, Fornitori, Centri di
  Costo e le altre Entità di Dominio per le quali è prevista l'Eliminazione recuperabile.
- D: Cosa accade quando si tenta di Ripristinare un elemento la cui Dipendenza obbligatoria è ancora
  nel Cestino? → R: Il Software blocca il Ripristino, mostra le Dipendenze necessarie e permette
  all'Utente di aprirle e Ripristinarle prima, senza riattivarle automaticamente.
- D: Cosa accade quando si tenta di eliminare un'Entità ancora utilizzata? → R: Se esistono
  riferimenti operativi attivi, il Software blocca l'Eliminazione e mostra gli elementi da sistemare.
  Se restano soltanto riferimenti storici, consente l'Eliminazione conservando la Denominazione minima
  necessaria alla lettura dello storico.
- D: Quanti Snapshot deve rendere disponibile la Cronologia delle Versioni? → R: Il limite è
  configurabile nelle Impostazioni e usa come valore predefinito `10 Versioni` per ogni Entità.
- D: L'impostazione del numero di Versioni è personale o comune? → R: È un'Impostazione del Tenant,
  unica per tutte le Entità e modificabile soltanto dagli Utenti autorizzati. Non varia per Utente o
  per Tipo di Entità.
- D: Quali Grafici Predefiniti compongono il Confronto Annuale? → R: Il Report riusa dal Flusso
  corrente il Confronto per Raggruppamento e lo Scostamento, aggiungendo Progressione Mensile
  Cumulativa ed evidenza condizionale di Extra Budget e Rettifiche. I Totali restano nei KPI senza un
  Grafico duplicato. Un solo Selettore di Dimensione sostituisce Grafici duplicati per Centro di
  Costo, Progetto, Contratto, Fornitore e Spesa.
- D: Qual è la Base Economica di Budget, Plafond, Scostamenti e Report? → R: È configurabile per
  Tenant tra `Netto` e `Lordo`, usa `Netto` come valore predefinito e viene bloccata definitivamente
  dalla prima Approvazione. Tutte le superfici usano la stessa Base; la visibilità personale delle
  colonne Netto, IVA e Lordo non modifica la Base ufficiale.
- D: La scelta delle colonne Netto, IVA e Lordo è comune o personale? → R: È una Preferenza personale
  per ciascun Utente. Il Netto è visibile per impostazione predefinita e la scelta non influenza gli
  altri Utenti del Tenant.
- D: Chi prepara un Budget può anche Approvare e Chiudere lo stesso Budget? → R: Sì. Qualsiasi Utente
  autorizzato può svolgere tutte e tre le operazioni; non è richiesta una separazione obbligatoria
  tra chi prepara e chi conferma.
- D: L'Approvazione può essere annullata? → R: Sì, ma soltanto finché non esistono Effettivi,
  Rettifiche, Spese Extra Budget o altri eventi successivi che abbiano fatto affidamento sul Previsto.
  L'annullamento riporta il Budget in Proposta e conserva l'Approvazione annullata nella Cronologia.
- D: Il Budget Finale può essere riaperto? → R: Sì, ma soltanto finché non esistono Rettifiche
  successive alla Chiusura. La Riapertura conserva la fotografia e i dati della Chiusura nella
  Cronologia; una nuova Chiusura crea una nuova fotografia.
- D: Gli Stati `Idea`, `Proposto`, `Approvato`, `Rinviato` e `Rifiutato` possono restare sui
  Progetti? → R: Sì, come **Fase del Progetto** descrittiva e modificata manualmente, purché non
  determini Anno Economico, inclusione nel Budget, Previsto, Effettivo, Chiusura, spostamento o
  Continuazione.
- D: Il Contratto deve mantenere uno Scadenziario anche se il Budget usa Totali annuali? → R: Sì.
  Lo **Scadenziario Contratti** conserva e presenta Data di Inizio, Date di Rinnovo, eventuali
  termini di preavviso, Cessazione e collegamenti alle Spese generate. La collocazione temporale
  degli eventi resta distinta dalla regola con cui il relativo Importo concorre al Budget.
- D: Un Rinnovo Annuale che copre un periodo a cavallo tra due anni deve essere ripartito? → R: No.
  L'intero Importo concorre al Budget dell'Anno della Data di Rinnovo. Il periodo coperto resta
  visibile nello Scadenziario, senza introdurre Ratei, Risconti o ripartizioni automatiche.
- D: Come viene rappresentato un Contratto Mensile? → R: Per ogni Anno viene generata una sola
  **Spesa Contrattuale Annuale**, contenente una Riga distinta per ciascuna Scadenza Mensile ricadente
  nell'Anno. Ogni Riga conserva la propria Data, compare nello Scadenziario ed è modificabile senza
  moltiplicare le Spese nel Registro.

## Convenzioni di Linguaggio

- I termini importanti del Dominio usano il Title Case: `Budget in Lavorazione`, `Budget Proposto`,
  `Budget Approvato`, `Budget Corrente`, `Budget Finale`, `Previsto`, `Effettivo`, `Spesa`, `Contratto`,
  `Progetto`, `Extra Budget`, `Valutazione`, `Rettifica`, `Previsto Ricostruito`, `Cestino`,
  `Cronologia delle Versioni` e `Snapshot`.
- Il termine `Actual` non deve essere usato nell'interfaccia o nella documentazione di Prodotto. Il
  termine canonico è `Effettivo`.
- Il termine `Forecast` non identifica un valore o un'entità autonoma. Quando è necessario descrivere
  ciò che era stato pianificato si usa `Previsto`.
- Il semplice termine `Budget` indica la situazione annuale corrente quando il contesto non richiede
  una distinzione più precisa.
- I termini devono essere comprensibili all'Imprenditore e al Consulente senza richiedere conoscenze
  contabili o tecniche specialistiche.

## Principi di Prodotto

1. Il Software costruisce e presenta automaticamente il Budget a partire dai dati inseriti: Spese,
   Contratti, Progetti, Rettifiche ed Effettivi.
2. La Spesa è l'unità economica di dettaglio. Contratti e Progetti forniscono contesto e continuità,
   ma non producono totali economici paralleli.
3. Il Previsto non viene riscritto durante l'anno.
4. Gli eventi successivi all'approvazione devono spiegare la differenza rispetto al Previsto, non
   modificarne retroattivamente il significato.
5. Il Budget deve risultare comprensibile senza procedure manuali di riporto da un anno al successivo.
6. Il Modello deve preferire pochi concetti stabili a Workflow, stati e contenitori ridondanti.
7. La Reportistica deve consentire di partire da una Panoramica e raggiungere progressivamente il
   dettaglio che spiega ogni differenza.
8. Cestino, Cronologia delle Versioni e Rettifiche hanno responsabilità differenti e non devono
   sostituirsi tra loro.
9. Ogni Tenant possiede una sola Base Economica autorevole, `Netto` o `Lordo`, predefinita su `Netto`
   e non più modificabile dalla prima Approvazione; tutte le superfici consumano il medesimo calcolo.

## Concetti Economici Canonici

### Base Economica e Colonne degli Importi

La Base Economica autorevole è un'Impostazione del Tenant scelta tra `Netto` e `Lordo`. Il valore
predefinito è `Netto`. Budget, Previsto, Effettivo, Scostamento, Plafond, Rettifica, Extra Budget,
KPI, Grafici, Confronti Annuali, Snapshot ed Export usano sempre la medesima Base ufficiale.

La Base può essere modificata soltanto prima della prima Approvazione di un Budget del Tenant. Da
quel momento resta bloccata definitivamente, anche se l'Approvazione viene successivamente annullata,
per evitare che lo storico cambi significato. Il Software mostra la Base ufficiale nelle
Impostazioni e in ogni vista sintetica o documento esportato in cui un Importo privo di contesto
potrebbe risultare ambiguo.

Ogni Riga economica conserva comunque in modo esatto:

- Importo Netto;
- Importo IVA;
- Importo Lordo.

Le Impostazioni personali controllano soltanto quali colonne informative vengono mostrate
nell'interfaccia. Per impostazione predefinita è visibile il Netto; ogni Utente può rendere visibili
anche IVA e Lordo nelle superfici che supportano il dettaglio degli Importi, senza modificare la
Vista degli altri Utenti del Tenant.

La scelta personale delle colonne:

- non cambia la Base Economica;
- non ricalcola né riscrive Spese, Contratti, Budget o Plafond;
- non crea Rettifiche, Snapshot o eventi economici;
- non cambia Scostamenti, Residui, Sforamenti o Percentuali;
- non altera i Confronti tra Anni;
- mostra sempre un'etichetta esplicita `Netto`, `IVA` o `Lordo`, evitando Importi privi di Base.

Esempio con Base ufficiale `Netto`:

```text
Riga Economica
Netto: €100
IVA:    €22
Lordo: €122

Importo che alimenta Budget e Plafond: €100
```

Se viene mostrata la colonna Lordo, il valore €122 è informativo. Il Budget Approvato, l'Effettivo,
lo Scostamento e il Consumo del Plafond restano €100 sulla Base ufficiale Netto. Se la Base ufficiale
fosse Lordo, i medesimi valori sarebbero invece €122. Nascondere la colonna corrispondente alla Base
non cambia i calcoli e le viste sintetiche devono comunque dichiarare la Base usata.

La configurazione di visualizzazione può essere modificata anche dopo l'Approvazione o la Chiusura,
perché non modifica il significato economico o lo storico.

La Preferenza viene applicata in modo coerente alle superfici economiche dello stesso Utente. Non è
necessario riconfigurare separatamente Registro Spese, Budget e Report; le singole schermate possono
comunque nascondere una colonna non pertinente al proprio livello di dettaglio.

### Anno Economico e Data della Spesa

L'Anno Economico determina a quale Budget appartiene una Spesa. La Data della Spesa indica quando
il costo si è verificato. Le due informazioni normalmente coincidono nell'anno, ma non sono lo stesso
concetto.

- Per una Spesa non collegata a un Progetto, l'Anno Economico deriva dalla Data della Spesa.
- Gli Effettivi di una Spesa Ordinaria devono appartenere allo stesso Anno Economico della Spesa.
- Per una Spesa collegata a un Progetto, l'Anno Economico è sempre l'Anno del Progetto.
- La Data della Spesa conserva il giorno reale dell'evento e non viene alterata per farla coincidere
  con l'Anno del Progetto.
- La Data di Registrazione conserva quando l'Utente ha inserito l'informazione nel Software e resta
  disponibile nella Cronologia.

Questa distinzione permette di rappresentare una Spesa sostenuta nel 2026 ma appartenente, dal punto
di vista del Budget, a un Progetto 2025 senza falsificare alcuna Data.

### Previsto

Il Previsto rappresenta quanto era stato pianificato e approvato per una Spesa.

- Durante la preparazione, Stima e Preventivo determinano il valore presentato nel Budget Proposto.
- Con l'approvazione del Budget, il valore applicabile viene fissato come Previsto.
- Non viene aggiornato per seguire Preventivi successivi, Sconti, variazioni di costo o Effettivi.
- Rimane la base di confronto per tutto l'anno e dopo la chiusura.
- Una Spesa non presente nel Budget Approvato non acquisisce retroattivamente un Previsto originario.

`Previsto Approvato` non è un termine distinto del Dominio. Al momento dell'approvazione, il valore
applicabile della Spesa viene fissato come `Previsto` all'interno del `Budget Approvato`. Dopo tale
momento si continua a chiamarlo semplicemente `Previsto`.

Il Previsto consente di rispondere alla domanda: **“Che cosa avevamo deciso inizialmente?”**

### Previsto Ricostruito

Il Previsto Ricostruito è usato esclusivamente per un Anno Storico nel quale il Cliente non gestiva
un Budget Approvato o non dispone più dei relativi dati.

Non è un nuovo contenitore o una nuova entità economica: è il Previsto con origine
`Ricostruito dagli Effettivi`. Non rappresenta un'approvazione avvenuta e non viene chiamato
`Budget Approvato`. Permette di
distinguere, sulla base della classificazione fornita dall'Utente, le Spese considerate ordinarie da
quelle riconosciute come Extra Budget.

Per ogni Spesa storica:

```text
Spesa non Extra Budget → Previsto Ricostruito = Totale Effettivo
Spesa Extra Budget     → Previsto Ricostruito = €0
```

Il calcolo aggregato continua a rispettare le normali regole di esclusione delle Spese eliminate,
Copertura dei Plafond e assenza di Doppio Conteggio.

Il Previsto Ricostruito consente di rispondere alla domanda limitata: **“Sulla base della
ricostruzione disponibile, quanto apparteneva alle Spese ordinarie e quanto è stato riconosciuto come
Extra Budget?”**

Non consente invece di misurare Sconti, Maggiorazioni o differenze rispetto agli importi realmente
approvati all'epoca, perché tali importi non sono disponibili.

### Riclassificazione Extra Budget negli Anni Ricostruiti

In un Anno Storico con Previsto Ricostruito, la selezione `Extra Budget` è una classificazione
analitica usata per costruire la base, non una modifica dell'Effettivo o di una fotografia realmente
approvata.

Anche dopo la Chiusura, l'Utente può quindi selezionare o rimuovere `Extra Budget`:

- senza creare una Rettifica;
- senza inserire una Nota obbligatoria;
- senza modificare il Totale Effettivo o il Budget Finale;
- con ricalcolo immediato di Previsto Ricostruito, Extra Budget e Differenza;
- con registrazione automatica in Cronologia di classificazione precedente, classificazione nuova,
  Data e Utente.

Esempio:

```text
Prima della Riclassificazione
Previsto Ricostruito: €1.000
Extra Budget:             €0
Effettivo:             €1.000

Dopo la selezione Extra Budget
Previsto Ricostruito:     €0
Extra Budget:         €1.000
Effettivo:            €1.000
```

Questa eccezione vale soltanto per il Previsto Ricostruito. Non elimina l'obbligo della Nota quando
si crea una nuova Spesa Extra Budget nel Flusso ordinario e non modifica le regole di Rettifica dei
Budget realmente approvati.

### Effettivo

L'Effettivo rappresenta un costo economicamente riconosciuto e attribuito all'anno, non lo Stato del
relativo Pagamento.

- Può essere registrato in uno o più momenti.
- Può essere inferiore, uguale o superiore al Previsto.
- Non sostituisce né riscrive il Previsto.
- Concorre alla situazione corrente e, alla chiusura, al Budget Finale.
- Per una Spesa Ordinaria viene registrato dall'Utente quando l'importo è noto.
- Per una Spesa generata da un Contratto può essere creato automaticamente quando il relativo Anno
  Economico diventa corrente, perché il costo contrattuale è considerato certo per il Budget.
- Il Software non determina se l'importo sia stato materialmente pagato e non gestisce Stati come
  `Da Pagare` o `Pagato`.

L'Effettivo consente di rispondere alla domanda: **“Quali costi sono stati effettivamente riconosciuti
nel Budget dell'anno?”**

### Struttura della Spesa

La Spesa è la decisione economica principale. Le sue caratteristiche non devono essere compresse in
un unico Stato rigido: sono dimensioni indipendenti che possono combinarsi senza creare un Workflow
obbligatorio.

| Dimensione | Valori o Comportamento |
|---|---|
| Natura | Spesa Ordinaria oppure Plafond |
| Righe | Stima, Preventivo ed Effettivo |
| Collocazione | Nel Budget Approvato, Rettifica oppure Extra Budget |
| Copertura | Nessun Plafond oppure riferimento esplicito a un Plafond |
| Contesto | Anno Economico, Centro di Costo ed eventuali Progetto, Contratto e Fornitore |

Quando è presente un Progetto, l'Anno Economico non è una seconda scelta indipendente: viene
ereditato dall'Anno del Progetto. L'Utente continua invece a indicare liberamente la Data reale della
Spesa.

Queste dimensioni sono ortogonali. Per esempio, una Spesa può essere contemporaneamente:

- Ordinaria;
- Extra Budget;
- collegata a un Centro di Costo;
- coperta da un Plafond;
- descritta inizialmente da una Stima;
- successivamente documentata da un Preventivo e da uno o più Effettivi.

### Righe della Spesa

Una Spesa può contenere Righe di tipo `Stima`, `Preventivo` ed `Effettivo`.

- La Stima esprime una valutazione preliminare.
- Il Preventivo esprime una valutazione più precisa ricevuta prima dell'acquisto.
- L'Effettivo registra il costo riconosciuto nell'Anno Economico.
- Non è obbligatorio attraversare tutti e tre i tipi.
- Una Spesa può avere più Righe e più Effettivi.
- Stima, Preventivo ed Effettivo possono differire senza produrre errori o richiedere una sequenza
  amministrativa rigida.
- Le Righe precedenti devono rimanere leggibili per spiegare come si è evoluta la conoscenza della
  Spesa.

Esempio:

```text
Spesa Extra Budget — Sostituzione PC
├── Stima: €400
├── Preventivo: €410
└── Effettivo: €450
```

La differenza può dipendere da informazioni emerse successivamente, come l'aggiunta di un mouse
richiesto insieme al PC. Il Modello deve consentire questo raffinamento senza imporre la creazione di
una nuova Spesa per ogni dettaglio accessorio della stessa decisione di acquisto.

La Spesa non possiede gli Stati `Aperta` e `Chiusa`. Può ricevere più Effettivi senza richiedere una
riapertura e la differenza rispetto alla Stima, al Preventivo o al Previsto resta sempre calcolabile.
La chiusura riguarda il Budget annuale; dopo la creazione del Budget Finale, eventuali registrazioni
tardive seguono le regole delle Rettifiche.

### Budget in Lavorazione

Il Budget in Lavorazione è la situazione annuale modificabile prima dell'approvazione.

È composto automaticamente dalle Spese:

- inserite manualmente;
- generate dai Rinnovi dei Contratti previsti nell'anno;
- appartenenti ai Progetti impostati sull'anno.

Il Budget in Lavorazione è il contenitore operativo. Non richiede la creazione di copie separate per
ogni modifica.

La composizione automatica è una base modificabile. L'Utente può:

- aggiungere una nuova Spesa manuale;
- includere un Contratto o un Progetto rilevante per l'anno;
- escludere dal Budget dell'anno una Spesa generata automaticamente;
- escludere con una sola azione tutte le Spese annuali appartenenti a un Contratto o a un Progetto;
- ripristinare un elemento precedentemente escluso.

Le Esclusioni Annuali restano memorizzate e impediscono che la successiva ricomposizione automatica
reinserisca silenziosamente lo stesso elemento. Non vengono creati totali manuali paralleli: anche
quando l'Utente agisce su un Contratto o un Progetto, l'effetto economico continua ad applicarsi alle
relative Spese.

L'effetto dell'Esclusione dipende dall'elemento di origine:

- una Spesa esclusa resta consultabile;
- per un Contratto, `Escludi dal Budget` disattiva contestualmente il `Rinnovo Automatico`;
- il Contratto può restare consultabile e non viene necessariamente classificato come Disattivato,
  ma non genera più nuove Righe di Rinnovo o nuove Spese;
- un Progetto escluso resta Aperto nel proprio Anno del Progetto;
- `Disattiva Contratto`, `Cessa Anticipatamente` e `Chiudi Progetto` restano azioni distinte per
  rappresentare la conclusione effettiva dell'elemento.

Prima di escludere un Contratto, il Software mostra esplicitamente che il Rinnovo Automatico verrà
disattivato. Una Vista di Impatto elenca inoltre tutte le Righe di Rinnovo e le Spese automatiche
senza Effettivi, dall'Anno Economico escluso in avanti, che verranno escluse logicamente. L'anno
mostrato nell'Etichetta deriva dal Budget in Lavorazione corrente.

L'Esclusione conserva:

- tutte le Righe e le Spese con Effettivi;
- i Budget Approvati e i Budget Finali precedenti;
- la Cronologia completa del Contratto;
- le Righe future escluse, consultabili come tali ma non incluse nei Budget o trasformate in
  Effettivi all'ingresso del relativo anno.

Se l'Utente riattiva successivamente il Rinnovo Automatico, il Software genera soltanto i Rinnovi
mancanti applicabili dalla Data di Riattivazione agli Anni Economici esistenti, usando i Termini
correnti del Contratto. Le Righe escluse restano nella Cronologia e non vengono riattivate
silenziosamente.

### Budget Proposto

Il Budget Proposto è la rappresentazione presentabile del Budget in Lavorazione.

- Non è un secondo contenitore economico.
- Non duplica le Spese.
- Può essere aggiornato fino all'approvazione.
- Evidenzia differenze rispetto agli anni precedenti e le motivazioni delle nuove decisioni.

Il Budget Proposto deve possedere un valore economico approvabile. Per ogni Spesa, usa una sola Riga
Previsionale Corrente:

```text
Valore Proposto della Spesa = Preventivo Corrente, se presente
                              altrimenti Stima Corrente
```

Stima e Preventivo non vengono sommati. Al momento dell'approvazione, il Valore Proposto applicabile
viene fissato come Previsto della Spesa nel Budget Approvato. Le Righe originarie restano nella
Cronologia e non vengono trasformate o eliminate. Il calcolo continua a rispettare la regola di
assenza di Doppio Conteggio per le Spese coperte da Plafond.

Questa regola è distinta dalla Reportistica successiva all'approvazione: durante la preparazione, le
Valutazioni formano necessariamente il totale del Budget Proposto; dopo l'approvazione, eventuali
nuove Stime o nuovi Preventivi sono soltanto Valutazioni informative e non modificano il Previsto o
i totali principali.

### Riepilogo di Approvazione

L'Approvazione riguarda sempre l'intero Budget Proposto risultante in quel momento. Non sono previsti
Stati `Approvata` o `Non Approvata` sulle singole Spese.

Preparazione, Approvazione e Chiusura sono controllate dalle rispettive Autorizzazioni, non
dall'identità di chi ha inserito o modificato i dati. Lo stesso Utente può quindi preparare,
Approvare e successivamente Chiudere il Budget se possiede tutte le Autorizzazioni necessarie. Non
viene imposto un principio dei quattro occhi e non viene creato un Workflow di assegnazione a un
secondo Utente.

Prima della conferma, il Riepilogo di Approvazione mostra:

- Totale Proposto;
- Spese incluse automaticamente;
- Spese aggiunte manualmente;
- Spese escluse manualmente;
- Contratti e Progetti inclusi o esclusi con il relativo effetto economico;
- effetto delle Esclusioni sul Rinnovo Automatico dei Contratti e sui Progetti ancora Aperti;
- confronto con il Budget selezionato come riferimento;
- Data di Approvazione, Approvatore ed eventuali Note.

L'Utente può tornare alla composizione, aggiungere, rimuovere o modificare gli elementi e quindi
riaprire il Riepilogo aggiornato. La conferma crea una sola fotografia del Budget Approvato e fissa
come Previsti tutti i Valori Proposti inclusi.

### Annullamento dell'Approvazione

Un Utente autorizzato può annullare l'Approvazione soltanto quando il Budget non possiede alcun
evento successivo dipendente dal Previsto approvato. Sono condizioni bloccanti almeno:

- presenza di uno o più Effettivi dell'Anno Economico;
- presenza di Rettifiche;
- presenza di Spese Extra Budget inserite dopo l'Approvazione;
- Chiusura già eseguita;
- altri eventi automatici o manuali che abbiano usato il Budget Approvato come riferimento stabile.

Prima della conferma, il Software mostra una Vista di Impatto. Se esiste una condizione bloccante,
non annulla l'Approvazione, elenca gli elementi che impediscono l'operazione e mantiene invariato il
Budget.

Quando l'annullamento è consentito:

- il Budget torna alla fase di Proposta;
- i Previsti fissati dall'Approvazione cessano di essere la base attiva;
- Spese, Contratti e Progetti tornano modificabili secondo le regole della Preparazione;
- la fotografia annullata non è più il Budget Approvato corrente;
- Data, Approvatore, Note e fotografia annullata restano consultabili nella Cronologia;
- l'operazione registra Data, Utente ed eventuale Nota dell'annullamento;
- una successiva Approvazione crea una nuova fotografia con nuova Data e nuovo Approvatore.

L'annullamento non elimina o riscrive la fotografia precedente e non viene rappresentato come una
Rettifica, perché avviene prima che esistano eventi economici successivi da riconciliare.

Per un Contratto escluso, il Riepilogo conferma che il Rinnovo Automatico è stato disattivato e che
non verranno generate nuove Spese. Non esiste quindi un Effettivo Extra Budget generato
automaticamente dal medesimo Contratto. Per un Progetto escluso ma ancora Aperto, il Riepilogo
spiega invece che un successivo Effettivo privo di Previsto sarà Extra Budget. L'Utente può
confermare comunque l'Approvazione oppure tornare alla composizione.

Dopo l'approvazione, un elemento escluso non può essere reinserito riscrivendo la fotografia:
un'Omissione segue il Flusso di Rettifica, mentre una nuova esigenza segue il Flusso Extra Budget.

### Budget Approvato

Il Budget Approvato è la fotografia immutabile e attiva del Budget accettato in una determinata data.

Conserva almeno:

- le Spese previste;
- il Previsto di ciascuna Spesa;
- i Contratti e i Progetti rilevanti;
- le classificazioni economiche necessarie alla Reportistica;
- la data di approvazione;
- chi ha approvato;
- eventuali note dell'approvazione.

Il Budget Approvato non viene modificato direttamente. Se non esiste alcun evento successivo può
essere annullato attraverso il Flusso esplicito descritto sopra; in caso contrario un errore o
un'omissione scoperti dopo l'Approvazione producono una Rettifica esplicita.

Il valore rappresentato del Budget Approvato comprende la fotografia originaria e gli effetti delle
Rettifiche successive:

```text
Budget Approvato = Valore Approvato Originario + Rettifiche
```

La fotografia alla data di approvazione resta consultabile e non viene sovrascritta. Nella schermata
del Budget, tuttavia, il totale `Budget Approvato` incorpora le Rettifiche e ogni Riga aggiunta o
modificata in questo modo mostra l'indicazione `Rettifica`.

Esempio:

```text
Valore Approvato Originario: €100.000
Rettifica:                    +€1.200
Budget Approvato:            €101.200
Spese Extra Budget:             +€900
```

La Spesa Extra Budget non confluisce nel Budget Approvato e continua a essere mostrata
separatamente.

### Budget Corrente

Il Budget Corrente è la situazione viva dell'anno.

Comprende, senza perdere la distinzione tra le diverse origini:

- il Budget Approvato, già comprensivo delle Rettifiche;
- le Spese Extra Budget;
- gli Effettivi registrati;
- le differenze numeriche maturate durante l'anno.

Il Budget Corrente non sostituisce il Budget Approvato. Deve essere sempre possibile confrontare i
due e spiegare la differenza.

### Budget Finale

Il Budget Finale è la fotografia immutabile e attiva creata con la Chiusura dell'anno.

- Rappresenta la situazione economica consolidata alla data di chiusura.
- Mantiene visibile il confronto con il Budget Approvato.
- Evidenzia Extra Budget, Rettifiche e differenze economiche.
- Non viene modificato direttamente dopo la Chiusura.
- Finché non esistono Rettifiche successive, la Chiusura può essere annullata attraverso il Flusso
  esplicito di Riapertura.
- Dopo la prima Rettifica successiva, il Budget Finale non può più essere riaperto e ogni ulteriore
  correzione resta una Rettifica esplicita.

Il Budget Finale consente di rispondere alla domanda: **“Come si è concluso l'anno e quanto è diverso
da ciò che avevamo approvato?”**

### Riapertura del Budget Finale

Un Utente autorizzato può riaprire il Budget Finale soltanto quando non esiste alcuna Rettifica
registrata dopo la Chiusura. Prima della conferma, il Software verifica la condizione e mostra una
Vista di Impatto.

Se esiste almeno una Rettifica successiva:

- la Riapertura è bloccata;
- il Budget Finale resta attivo;
- il Software mostra le Rettifiche che impediscono l'operazione;
- le correzioni continuano a seguire il normale Flusso di Rettifica.

Quando la Riapertura è consentita:

- l'Anno torna alla situazione precedente alla Chiusura;
- il Budget Approvato, i Previsti, gli Effettivi, le Spese Extra Budget e gli altri dati economici non
  vengono cancellati o ricalcolati dalla sola Riapertura;
- la fotografia del Budget Finale riaperto non è più la fotografia finale attiva;
- Data, Utente, Riepilogo e fotografia della Chiusura restano consultabili nella Cronologia;
- l'operazione di Riapertura registra Data e Utente;
- le registrazioni successive seguono nuovamente le regole dell'Anno non chiuso e non sono
  Rettifiche soltanto per effetto della precedente Chiusura;
- una nuova Chiusura crea una nuova fotografia del Budget Finale senza sovrascrivere quella riaperta.

La Riapertura non modifica la fotografia precedente e non viene rappresentata come una Rettifica,
perché è ammessa soltanto prima che esistano Rettifiche da riconciliare.

### Documenti Tardivi Dopo la Chiusura

Una Fattura, una Nota di Credito o un altro documento ricevuto dopo la chiusura viene inserito con il
normale Flusso di registrazione della Spesa. L'Utente indica la data economica corretta; se tale data
appartiene a un anno già chiuso, il Software classifica automaticamente la Spesa come `Rettifica`.

Esempio:

```text
Data di ricezione: febbraio 2026
Data indicata sulla Spesa: dicembre 2025
Classificazione automatica: Rettifica del Budget Finale 2025
```

Regole:

- per una Spesa non collegata a un Progetto, l'Anno Economico deriva dalla Data della Spesa e non
  dalla Data di Registrazione;
- per una Spesa collegata a un Progetto, l'Anno Economico deriva dall'Anno del Progetto anche quando
  la Data della Spesa appartiene a un anno successivo;
- se l'Anno Economico è chiuso, la classificazione `Rettifica` viene applicata automaticamente;
- l'Utente non deve creare un contenitore, una versione o un tipo speciale di Spesa;
- la Nota della Spesa diventa obbligatoria e deve spiegare il motivo della registrazione tardiva;
- se la Nota manca, il Software mostra un Avviso, non salva e mantiene la Spesa in Modifica con i
  dati inseriti;
- la Rettifica modifica l'importo mostrato del medesimo Budget Finale senza crearne uno nuovo;
- una Nota di Credito tardiva segue la stessa regola e produce una Rettifica negativa;
- il Budget dell'anno di ricezione non deve conteggiare nuovamente il documento come propria Spesa.

La schermata continua a usare il solo nome `Budget Finale`:

```text
Budget Finale = Importo alla Chiusura + Rettifiche
```

La Riga usa esclusivamente la normale indicazione `Rettifica`. Non vengono introdotti Tag o Label
come `Rettifica Successiva alla Chiusura`. Il Drill-Down permette di leggere la Nota obbligatoria.

## Immutabilità e Rettifiche

La stessa regola si applica al Budget Approvato e al Budget Finale:

1. la fotografia originale resta immutabile;
2. una correzione economica non riscrive la fotografia;
3. la correzione economica viene registrata come Rettifica;
4. la vista corrente incorpora gli effetti della Rettifica;
5. il confronto continua a mostrare sia il valore originale sia gli effetti successivi.

Le sole Riclassificazioni analitiche espressamente definite non sono Rettifiche, perché non cambiano
Importi, Anno Economico o appartenenza al Budget; restano comunque tracciate nella Cronologia.

Una Rettifica conserva almeno:

- il Budget a cui si riferisce;
- la data;
- l'autore;
- la motivazione;
- gli elementi aggiunti, corretti o rimossi;
- l'effetto economico.

Per mantenere semplicità, la Rettifica è un unico meccanismo. Non richiede un albero di Versioni o un
Workflow di pubblicazione complesso.

### Riclassificazioni Analitiche

Centro di Costo, Fornitore, Descrizione e Note Generali sono informazioni analitiche o descrittive.
La loro correzione non cambia Importo, Anno Economico, Previsto, Effettivo o appartenenza della Spesa
al Budget.

Possono quindi essere modificati anche dopo la Chiusura:

- senza creare una Rettifica;
- senza richiedere una Nota;
- ricalcolando immediatamente gli eventuali raggruppamenti della Reportistica;
- applicando Centro di Costo e Fornitore correnti in modo coerente a Previsto ed Effettivi della
  Spesa;
- registrando automaticamente in Cronologia valore precedente, valore nuovo, Data e Utente.

La fotografia monetaria del Budget Approvato e del Budget Finale resta immutata. La classificazione
o descrizione originaria rimane ricostruibile dalla Cronologia, mentre le Viste correnti usano i
valori corretti.

Non sono Riclassificazioni analitiche libere:

- Anno Economico;
- Progetto o Contratto di appartenenza;
- Natura Ordinaria o Plafond;
- Importi e Righe economiche;
- Copertura Plafond;
- classificazione Extra Budget, salvo l'eccezione del Previsto Ricostruito.

Questi elementi seguono i rispettivi Flussi di Dominio.

Se la Spesa possiede una Copertura Plafond incompatibile con il nuovo Centro di Costo, il Software:

1. mostra le Quote incompatibili;
2. non salva la Riclassificazione;
3. mantiene la Spesa in Modifica con i dati inseriti;
4. richiede di scegliere Plafond compatibili con il nuovo Centro oppure di rimuovere la Copertura;
5. aggiorna Consumato e Residuo soltanto dopo la conferma della nuova configurazione.

La correzione della Copertura resta tracciata nella Cronologia ma non diventa una Rettifica, poiché
non modifica il Totale Effettivo annuale.

### Omissione Dopo l'Approvazione

Se dopo l'approvazione si scopre che una Spesa, un Contratto o un'altra voce già prevista al momento
della decisione era stata dimenticata:

1. il Budget Approvato originario non viene modificato;
2. viene registrata una Rettifica per Omissione;
3. la voce entra nel valore rappresentato del Budget Approvato e, di conseguenza, nel Budget
   Corrente;
4. nella schermata del Budget, la Riga resta identificata come `Rettifica`;
5. la Reportistica la distingue dalle esigenze nate realmente durante l'anno.

L'Omissione non deve essere confusa automaticamente con l'Extra Budget.

## Spese Extra Budget

Una Spesa Extra Budget nasce da un'esigenza emersa dopo l'approvazione e non presente nel Budget
Approvato.

Esempio: durante l'anno viene richiesto un nuovo PC non previsto inizialmente.

Il Flusso Semplificato è:

1. l'Utente registra la nuova Spesa;
2. inserisce le informazioni economiche disponibili in quel momento, che possono essere una Stima,
   un Preventivo o direttamente un Effettivo;
3. seleziona `Extra Budget` sulla Riga e compila obbligatoriamente la Nota della Riga, spiegandone il
   motivo;
4. la Spesa entra nel Budget Corrente mantenendo separate Valutazioni ed Effettivi;
5. aggiunge o raffina le Righe quando diventano disponibili informazioni migliori;
6. a fine anno la Spesa resta chiaramente identificata come Extra Budget.

Regole:

- la Spesa Extra Budget non modifica il Previsto originario;
- non le viene attribuito retroattivamente un Previsto del Budget Approvato;
- la disponibilità di una Copertura non è obbligatoria;
- la selezione `Extra Budget` rende obbligatoria la Nota già disponibile sulla Riga;
- la Nota deve spiegare sinteticamente perché la Spesa non era compresa nel Budget Approvato;
- se la Nota manca, il Software mostra un Avviso, non salva e mantiene la Riga in Modifica con i dati
  inseriti;
- la natura Extra Budget non dipende dal tipo delle Righe e non cambia quando una Stima viene
  raffinata da un Preventivo o da un Effettivo;
- il Budget Corrente e il Budget Finale devono mostrare separatamente l'impatto complessivo delle
  Spese Extra Budget.

Nella prima passata non sono previsti Richieste di Conferma via Email, Workflow di Approvazione o
Notifiche. Queste capacità saranno raffinate separatamente in una seconda passata e non condizionano
la registrazione corrente della Spesa Extra Budget.

## Plafond e Copertura

### Natura del Plafond

Il Plafond è una Spesa positiva che rappresenta una disponibilità assegnata a un Centro di Costo.

- La sua natura è distinta dalla Spesa Ordinaria.
- Appartiene a un solo Anno Economico.
- È associato a un Centro di Costo.
- Più Plafond possono appartenere allo stesso Centro di Costo.
- Il totale disponibile per il Centro di Costo è la somma dei relativi Plafond applicabili.
- L'estensione di un Plafond viene registrata creando un nuovo Plafond, senza modificare
  retroattivamente quello originario.

Esempio:

```text
Centro di Costo — Servizi IT
├── Plafond Gennaio: €3.000
└── Plafond Settembre: €1.000

Impegnato Totale: €4.000
```

La creazione del secondo Plafond mantiene visibile che l'estensione è avvenuta a settembre e non
riscrive la decisione di gennaio.

### Uso del Plafond

L'assegnazione di una Spesa a un Centro di Costo non implica il consumo automatico dei Plafond di
quel Centro di Costo.

Una Spesa consuma un Plafond soltanto quando l'Utente seleziona esplicitamente il Plafond da usare
come Copertura e registra un Effettivo. Il semplice collegamento al Plafond non costituisce ancora
un consumo reale.

Quando la Spesa collegata contiene una Stima o un Preventivo, il relativo importo viene mostrato
come Copertura Prevista. Soltanto gli Effettivi alimentano il Consumato e riducono il Residuo.

Pertanto sono validi entrambi i casi:

```text
Spesa A → Centro di Costo Servizi IT → Nessun Plafond
Spesa B → Centro di Costo Servizi IT → Coperta dal Plafond Gennaio
```

Regole:

- la Copertura è una scelta esplicita e non una conseguenza del Centro di Costo;
- una Stima o un Preventivo collegati al Plafond alimentano la Copertura Prevista senza ridurre il
  Residuo;
- un Effettivo collegato al Plafond aumenta il Consumato e riduce immediatamente il Residuo;
- un Effettivo che supera il Residuo non viene bloccato: prima della registrazione il Software mostra
  un Avviso e richiede una motivazione;
- la motivazione viene aggiunta alle Note Generali nel formato
  `Note Sforamento Plafond: <motivazione>`, senza introdurre un campo dedicato;
- lo Sforamento del Plafond non rende automaticamente la Spesa Extra Budget;
- la Spesa coperta mantiene la propria identità e le proprie Righe;
- il Plafond selezionato mantiene separati Impegnato, Copertura Prevista, Consumato e Residuo;
- più Spese possono prevedere o realizzare consumi sullo stesso Plafond;
- una singola Spesa può ripartire la propria Copertura tra più Plafond dello stesso Centro di Costo;
- più Plafond dello stesso Centro di Costo restano distinti, anche se la Reportistica può mostrarne
  la disponibilità complessiva;
- la Copertura da Plafond e la natura Extra Budget sono indipendenti;
- una Spesa può essere Extra Budget e usare una Copertura Plafond, ma quando la Copertura viene
  selezionata la somma delle Quote deve coprire integralmente l'Importo della Riga;
- una Spesa può appartenere allo stesso Centro di Costo di un Plafond senza consumarlo.

### Copertura Ripartita tra Più Plafond

L'Utente non usa un semplice Multi-Select, perché per ogni Plafond deve essere comprensibile anche
l'importo attribuito. Nella Spesa, la sezione `Copertura Plafond` contiene una o più righe:

```text
Copertura Plafond
├── Plafond Gennaio   Residuo €100    Importo di Copertura €100
├── Plafond Settembre Residuo €1.000 Importo di Copertura €400
├── Copertura Totale                         €500
└── Importo da Coprire                        €0
```

Il Flusso UX è:

1. l'Utente sceglie `Copri con Plafond`;
2. seleziona il primo Plafond;
3. se questo non basta, il Software mostra gli altri Plafond disponibili dello stesso Centro di
   Costo e propone `Completa Copertura`;
4. il Software propone la ripartizione, consumando al massimo il Residuo disponibile del primo
   Plafond e attribuendo la parte restante ai Plafond aggiunti;
5. l'Utente può confermare la proposta oppure modificare manualmente gli Importi di Copertura;
6. durante la compilazione sono sempre visibili Copertura Totale e Importo da Coprire;
7. il salvataggio con Copertura Plafond è consentito soltanto quando l'Importo da Coprire è pari a
   zero;
8. se l'Utente tenta comunque di salvare una Copertura incompleta, il Software mostra un Avviso,
   non salva la Spesa e mantiene aperta la Modifica senza perdere i valori inseriti;
9. la sezione `Copertura Plafond` viene evidenziata e mostra l'importo ancora da coprire, così
   l'Utente può aggiungere una Quota, modificare gli importi oppure rimuovere la Copertura.

L'Utente può anche scegliere direttamente un Plafond capiente e attribuirgli l'intero importo. Il
Software assiste la ripartizione ma non impone di esaurire prima il Plafond con il Residuo minore.

Questa interazione mantiene esplicita la scelta della Copertura e non consuma automaticamente tutti
i Plafond del Centro di Costo. Ogni riga conserva il collegamento tra Spesa, Plafond e Importo di
Copertura senza richiedere di dividere artificialmente la Spesa.

La Parzializzazione si applica quindi alla Copertura e non genera nuove Spese né nuove Righe
economiche. Per una Spesa da €500 rimangono uniche la Stima, il Preventivo o l'Effettivo; a esse si
affiancano le Quote di Copertura:

```text
Spesa Ordinaria: €500
└── Quote di Copertura
    ├── Plafond 1: €100
    └── Plafond 2: €400
```

Regole della Parzializzazione:

- ogni Quota di Copertura indica un Plafond e un importo;
- la somma delle Quote determina la Copertura Totale della Spesa;
- la somma delle Quote deve coincidere esattamente con l'importo della Riga economica a cui si
  riferisce;
- una Quota può superare il Residuo del proprio Plafond soltanto seguendo il Flusso di Sforamento
  con Avviso e motivazione;
- una differenza tra importo della Riga e Copertura Totale resta visibile come Importo da Coprire
  durante la compilazione, ma impedisce di salvare la Riga con Copertura Plafond;
- il tentativo di salvataggio mostra un Avviso, non produce una registrazione parziale e lascia
  l'Utente nella schermata di Modifica con tutti i dati già inseriti;
- se non vuole completare la Copertura, l'Utente può rimuoverla e registrare la Riga senza Copertura
  Plafond;
- la Cronologia e la Reportistica mostrano la singola Spesa e, nel suo dettaglio, le Quote attribuite
  ai diversi Plafond.

La Copertura Integrale e la Capienza del Plafond sono due controlli distinti:

- **Copertura Integrale**: la somma delle Quote deve essere uguale all'importo della Riga; in caso
  contrario il salvataggio con Copertura Plafond viene bloccato;
- **Capienza**: una Quota che supera il Residuo può essere comunque confermata attraverso l'Avviso di
  Sforamento e la motivazione obbligatoria.

Pertanto una Riga da €500 non può essere salvata con sole Quote per €400. Può invece essere salvata:

- con Quote complete per €500;
- con Quote complete per €500 che producono uno Sforamento motivato;
- senza alcuna Copertura Plafond.

### Adeguamento delle Quote all'Effettivo

Le Quote collegate alla Stima o al Preventivo rappresentano la Copertura Prevista. Quando viene
registrato un Effettivo, il Software le usa come base per proporre le Quote di Consumo Effettivo,
senza modificare la ripartizione previsionale originaria.

Esempio:

```text
Preventivo: €500
├── Plafond 1: €100
└── Plafond 2: €400

Effettivo: €450
├── Plafond 1: €100
└── Plafond 2: €350  ← Quota proposta dal Software
```

Regole della proposta:

- il Software mantiene l'ordine dei Plafond già scelto dall'Utente;
- se l'Effettivo è inferiore, conserva le prime Quote fino a concorrenza dell'importo, riduce la
  Quota in cui viene raggiunto il totale ed esclude le successive;
- se l'Effettivo è superiore, attribuisce inizialmente la differenza all'ultimo Plafond selezionato;
- se la proposta supera il Residuo, mostra gli altri Plafond disponibili e le alternative di
  Copertura prima dell'eventuale conferma dello Sforamento;
- l'Utente deve confermare la proposta e può modificare Plafond e Importi di Copertura;
- soltanto le Quote confermate dell'Effettivo alimentano il Consumato;
- le Quote della Stima o del Preventivo restano consultabili e continuano a spiegare la Copertura
  Prevista;
- ogni Effettivo, quando la Spesa ne contiene più di uno, conserva la propria ripartizione.

### Impegnato, Copertura Prevista, Consumato e Residuo

Per il Plafond si usano cinque valori semplici:

- **Impegnato**: importo positivo stanziato con la creazione del Plafond;
- **Copertura Prevista**: somma dei valori correnti di Stima o Preventivo delle Spese collegate al
  Plafond;
- **Consumato**: somma degli Effettivi delle Spese collegate al Plafond;
- **Residuo**: differenza tra Impegnato e Consumato; può diventare negativo;
- **Sforamento**: parte del Consumato che supera l'Impegnato.

```text
Residuo = Impegnato − Consumato
Sforamento = max(Consumato − Impegnato, 0)
```

Esempio di Sforamento:

```text
Plafond Servizi IT
├── Impegnato: €3.000
├── Consumato: €3.140
├── Residuo: −€140
└── Sforamento: €140
```

La registrazione dell'Effettivo resta possibile perché il Software deve rappresentare ciò che è
realmente accaduto. Prima della conferma, l'Avviso rende esplicito lo Sforamento e la motivazione
obbligatoria ne conserva la spiegazione nelle Note Generali.

Esempio sul singolo Plafond:

```text
Plafond Servizi IT di Gennaio
├── Impegnato: €3.000
├── Copertura Prevista: €100
├── Consumato: €0
└── Residuo: €3.000
```

In questo primo momento esiste una Spesa Ordinaria con una Stima di €100 collegata al Plafond, ma
non è ancora stato sostenuto alcun costo.

Quando arriva un Preventivo di €150, il Preventivo sostituisce la Stima come Riga Previsionale
Corrente. La Stima resta consultabile ma non viene sommata:

```text
Plafond Servizi IT di Gennaio
├── Impegnato: €3.000
├── Copertura Prevista: €150
├── Consumato: €0
└── Residuo: €3.000
```

Quando viene registrato un Effettivo di €140, soltanto l'Effettivo consuma il Plafond:

```text
Plafond Servizi IT di Gennaio
├── Impegnato: €3.000
├── Copertura Prevista: €150
├── Consumato: €140
├── Residuo: €2.860
└── Differenza rispetto al Preventivo: −€10
```

Stima, Preventivo ed Effettivo rimangono quindi confrontabili, ma non vengono mai sommati tra loro.

Gli stessi valori possono essere aggregati per Centro di Costo senza perdere il dettaglio dei
singoli Plafond:

```text
Centro di Costo — Servizi IT
├── Plafond Gennaio:  Impegnato €3.000
├── Plafond Settembre: Impegnato €1.000
├── Impegnato Totale: €4.000
├── Copertura Prevista Totale: €150
├── Consumato Totale: €140
└── Residuo Totale: €3.860
```

Un'estensione aumenta l'Impegnato Totale creando un nuovo Plafond. Non modifica l'Impegnato del
Plafond precedente.

### Assenza di Doppio Conteggio

Il Plafond rappresenta una disponibilità già inclusa nel Budget. Una Spesa coperta dal Plafond ne
spiega l'utilizzo e non deve aggiungere una seconda volta lo stesso importo al totale del Budget.

La Reportistica deve quindi distinguere:

- Impegnato complessivo dei Plafond;
- Copertura Prevista;
- Consumato;
- Residuo;
- Spese coperte;
- Spese non coperte;
- eventuale importo che supera la Copertura disponibile.

Quando la Spesa coperta contiene più Stime o Preventivi, una sola Riga Previsionale Corrente
contribuisce alla Copertura Prevista. Le alternative restano consultabili ma non vengono sommate.
Tutti gli Effettivi contribuiscono invece al Consumato.

### Report dei Plafond

Il dettaglio dell'utilizzo dei Plafond appartiene a un Report dedicato e non deve appesantire il
Report principale del Budget.

L'Utente può aprire il Report dei Plafond:

- per un singolo Plafond;
- per più Plafond selezionati;
- per un Centro di Costo, aggregando tutti i suoi Plafond.

Il Report mostra almeno:

- Impegnato;
- Copertura Prevista;
- Consumato;
- Residuo;
- Sforamento;
- elenco delle Spese collegate;
- Riga Previsionale Corrente di ogni Spesa;
- Effettivi registrati;
- differenza tra valore previsto ed Effettivo.

Il Report principale può continuare a mostrare i valori economici generali del Budget senza esporre
questo dettaglio operativo. Il Drill-Down verso il Report dei Plafond rimane disponibile quando
l'Utente vuole comprendere l'utilizzo della disponibilità.

## Differenze Durante l'Anno

Il Previsto non cambia. Il Software calcola e mostra le differenze numeriche tra i valori rilevanti,
senza classificare o richiedere la causa dello scostamento.

Esempio:

```text
Previsto:  €1.000
Effettivo:   €950
Differenza:  −€50
```

Il valore `−€50` non viene classificato automaticamente come Sconto, Risparmio, Rinvio o altra
causa. Non sono previsti un campo `Causa dello Scostamento`, Tag dedicati o un Workflow obbligatorio
di spiegazione. Le Note ordinarie restano disponibili quando l'Utente desidera aggiungere contesto;
sono obbligatorie soltanto nei casi espressamente definiti, come Extra Budget e Rettifica su un anno
chiuso.

## Contratti Pluriennali

Il Contratto è pluriennale e non appartiene a un singolo Budget annuale.

- Possiede una Data di Inizio e le successive Date di Rinnovo.
- Le Righe del Contratto permettono di indicare l'Importo applicabile a ciascun Rinnovo.
- Ogni Rinnovo genera una Spesa di Rinnovo nell'Anno Economico della propria Data.
- Il periodo di servizio coperto dal Rinnovo può proseguire nell'anno successivo senza ripartire o
  spostare la Spesa tra due Budget.
- Non è richiesta una procedura manuale di Preparazione dell'Anno Successivo.
- La Disattivazione o la Cessazione Anticipata interrompe i Rinnovi futuri dalla relativa Data
  Effettiva.
- La Disattivazione o la Cessazione non cancella le Spese o gli Effettivi già generati.
- L'Importo Effettivo della Spesa di Rinnovo può differire dall'Importo indicato nella Riga del
  Contratto per Sconti, Maggiorazioni o altre circostanze.
- La modifica dell'Importo Effettivo permette una Nota facoltativa che spieghi la variazione, senza
  introdurre un campo strutturato per la causa.
- Anche senza Nota, la Cronologia registra automaticamente Importo precedente, Importo nuovo, Data
  della modifica e Utente.
- Se la modifica interessa un Anno Economico già chiuso, segue invece il normale Flusso di Rettifica
  e la Nota diventa obbligatoria.
- La modifica della Spesa di un singolo Rinnovo non riscrive automaticamente la Riga del Contratto o
  gli Importi dei Rinnovi successivi.
- Una modifica del Contratto influenza le Spese future applicabili e non riscrive i Budget già
  chiusi.

Esempio:

```text
Contratto: Servizio Cloud
Data di Inizio: 13/03/2025

Riga 2025 — Rinnovo 13/03/2025 — Importo €1.000 → Budget 2025
Riga 2026 — Rinnovo 13/03/2026 — Importo €1.100 → Budget 2026
Riga 2027 — Rinnovo 13/03/2027 — Importo €1.150 → Budget 2027
```

La Spesa del Rinnovo 2025 resta interamente nel Budget 2025 anche se il servizio coperto prosegue
fino al 12/03/2026. Non vengono creati Ratei, Risconti o Ripartizioni mensili automatiche.

### Scadenziario Contratti

Lo **Scadenziario Contratti** è una Vista operativa distinta dal calcolo del Budget e non viene
eliminato quando le Spese di Rinnovo sono aggregate annualmente.

Per ogni Contratto mostra almeno:

- Data di Inizio;
- Date di Rinnovo previste e relativo Importo;
- eventuale termine entro il quale comunicare Disdetta o mancato Rinnovo;
- Data di Cessazione prevista o anticipata, quando presente;
- stato della generazione della relativa Spesa: Da Generare, Preventivo generato, Effettivo generato
  oppure Escluso;
- collegamento al Contratto, alla Riga di Rinnovo e alla Spesa generata.

La Vista può essere filtrata per intervallo temporale, Contratto, Fornitore e Centro di Costo e può
alimentare le Segnalazioni della Dashboard. Il Selettore dell'Anno propone inizialmente l'intervallo
del relativo Anno, ma l'Utente può consultare anche scadenze a cavallo tra più anni. Lo Scadenziario
non modifica Importi, non genera ripartizioni economiche autonome e non rappresenta le Date di
Pagamento.

### Generazione delle Righe della Spesa di Rinnovo

Il tipo di Riga generato dipende dall'Anno Economico del Rinnovo al momento della creazione o
aggiornamento del Contratto:

```text
Rinnovo dell'Anno Corrente o di un Anno Precedente → Effettivo
Rinnovo di un Anno Futuro                         → Preventivo
```

Il Preventivo futuro alimenta il Budget in Lavorazione e il Budget Proposto dell'anno interessato.
Quando tale anno diventa l'Anno Corrente, il Software aggiunge automaticamente un Effettivo dello
stesso importo. Il Preventivo non viene trasformato o cancellato: resta nella Cronologia come
riferimento della previsione contrattuale.

Esempio:

```text
Creazione del Contratto nel 2025

Rinnovo 13/03/2025 — €1.000 → Effettivo 2025
Rinnovo 13/03/2026 — €1.100 → Preventivo 2026

Ingresso nell'Anno 2026
Rinnovo 13/03/2026 — Preventivo €1.100 + Effettivo automatico €1.100
```

La creazione dell'Effettivo non dipende dalla Data di Pagamento. Se il Contratto viene disattivato o
cessato prima che l'anno futuro diventi corrente, il relativo Effettivo non viene generato. Se la
Cessazione avviene dopo la generazione, l'Effettivo resta registrato finché l'Utente non ne adegua
l'importo alla situazione definitiva.

Se l'Importo Effettivo 2025 viene modificato da €1.000 a €950 per uno Sconto, la Spesa mostra la
differenza e può ricevere una Nota facoltativa. La Riga del Contratto da €1.000 resta il riferimento
originario del Rinnovo e non viene modificata automaticamente. Se il Budget 2025 è già chiuso, la
variazione è una Rettifica e la Nota è obbligatoria. In entrambi i casi, la Cronologia conserva
automaticamente il valore precedente e quello nuovo, la Data e l'Utente che ha eseguito la modifica.

Se la Spesa di Rinnovo usa una Copertura Plafond, il Preventivo alimenta la Copertura Prevista;
l'Effettivo automatico alimenta il Consumato secondo le normali regole del Plafond.

### Rinnovo Automatico

Il Contratto può avere l'opzione `Rinnovo Automatico`.

Quando l'opzione è attiva, il Software crea le Righe di Rinnovo mancanti soltanto per gli Anni
Economici già esistenti nel Software. Non genera una sequenza illimitata di Rinnovi futuri.

Per ogni nuova Riga:

- la Data di Rinnovo viene calcolata dalla cadenza del Contratto;
- l'Importo e le informazioni economiche applicabili vengono inizialmente copiati dalla Riga
  precedente;
- una Riga già presente per la medesima Data di Rinnovo non viene duplicata o sovrascritta;
- la Riga generata può essere modificata dall'Utente per rappresentare il nuovo Importo;
- una volta creata, la Riga conserva il proprio valore e non viene riscritta silenziosamente da una
  successiva modifica della Riga precedente;
- quando viene aggiunto un nuovo Anno Economico al Software, viene generata l'eventuale nuova Riga
  applicabile, purché il Contratto non sia stato Disattivato o Cessato.

La Riga più recente disponibile diventa quindi la base per il Rinnovo successivo. L'origine
`Generata da Rinnovo Automatico` resta visibile nella Cronologia, senza introdurre uno Stato o un
Workflow aggiuntivo.

Esempio:

```text
Anni Economici esistenti: 2025, 2026, 2027
Rinnovo Automatico: Sì

Riga inserita:
13/03/2025 — €1.000

Righe generate:
13/03/2026 — €1.000
13/03/2027 — €1.000
```

Se l'Utente modifica la Riga 2026 a €1.100 prima che venga generata la Riga 2027, la Riga 2027 usa
€1.100 come base. Se la Riga 2027 esiste già, non viene aggiornata automaticamente.

### Contratto Creato su Anni Chiusi

Se la Data di Inizio o una Data di Rinnovo appartiene a un Anno Economico già chiuso:

- il Software genera la normale Spesa di Rinnovo;
- la Spesa contiene un Effettivo, perché il Rinnovo appartiene a un anno corrente o precedente;
- l'Effettivo viene classificato automaticamente come Rettifica del relativo Budget Finale;
- la Nota della Rettifica è obbligatoria;
- il Budget dell'anno in cui l'Utente inserisce il Contratto non conteggia nuovamente la Spesa.

Con il Rinnovo Automatico attivo, la regola viene applicata a tutti i Rinnovi compresi negli Anni
Economici chiusi già esistenti nel Software.

Prima del salvataggio, una Vista di Impatto elenca le Righe, le Spese e le Rettifiche che verranno
generate per ciascun anno. Per non obbligare l'Utente a ripetere la stessa motivazione, il Flusso
richiede una sola Nota e la riporta nelle Note di tutte le Rettifiche generate dall'operazione. Se la
Nota manca, il Software non salva e mantiene il Contratto in Modifica con i dati inseriti.

## Progetti Pluriennali

Il Progetto può estendersi temporalmente su più anni, ma appartiene economicamente a un solo Anno del
Progetto.

- Il Progetto possiede un Anno del Progetto e può avere una Data di Chiusura.
- Un Progetto non ancora concluso resta disponibile anche negli anni successivi, senza essere
  duplicato o trasferito a un nuovo anno.
- Tutte le Spese collegate ereditano l'Anno Economico dal Progetto.
- La Data della singola Spesa può appartenere a un anno successivo senza cambiare l'attribuzione
  economica.
- Il passaggio del tempo non crea automaticamente un residuo, una nuova Spesa o un nuovo Progetto.
- Il dettaglio del Progetto mostra insieme Anno del Progetto, Date reali delle Spese e Cronologia.

Esempio:

```text
Progetto: Espansione Wi-Fi
Anno del Progetto: 2025

Spesa A — Data della Spesa: 15/09/2025 → Budget 2025
Spesa B — Data della Spesa: 10/03/2026 → Budget 2025
```

La Spesa B non concorre ai totali del Budget 2026. Nel Budget 2025 e nel dettaglio della Spesa deve
però essere immediatamente visibile che l'Effettivo si è verificato nel 2026.

Se il Budget 2025 è già chiuso quando la Spesa B viene registrata, la Spesa B è una `Rettifica` del
Budget Finale 2025. La Data della Spesa resta `10/03/2026`, la Nota della Rettifica è obbligatoria e
il Budget 2026 non conteggia l'importo. La classificazione deriva dall'effetto su un Anno Economico
chiuso, non dall'anno della Data della Spesa.

### Flusso UX per una Spesa con Anno Differente

Quando l'Utente collega una Spesa a un Progetto e la Data della Spesa appartiene a un anno diverso
dall'Anno del Progetto, il Software mostra un messaggio contestuale non bloccante:

```text
Questa Spesa sarà attribuita al Budget 2025 perché appartiene al Progetto
"Espansione Wi-Fi". La Data della Spesa resta 10/03/2026.
```

La stessa distinzione resta visibile:

- nel riepilogo prima del salvataggio;
- nella Riga della Spesa, con `Budget 2025` e `Data della Spesa 10/03/2026`;
- nel Drill-Down del Progetto e del Budget;
- nella Cronologia, insieme alla Data di Registrazione.

Il Software non modifica silenziosamente la Data e non chiede all'Utente di selezionare nuovamente
l'Anno Economico già determinato dal Progetto.

### Spostamento del Progetto a un Altro Anno

Lo spostamento dell'Anno del Progetto è un'Azione esplicita e non una modifica silenziosa di un
campo. Il comportamento dipende dalla presenza di Effettivi.

#### Progetto Senza Effettivi

Se nessuna Spesa del Progetto contiene Effettivi:

- il medesimo Progetto viene spostato al nuovo Anno del Progetto;
- tutte le Spese collegate restano le stesse e ne ereditano il nuovo Anno Economico;
- Stime e Preventivi vengono spostati insieme alle rispettive Spese;
- le Date delle Spese e la Cronologia non vengono modificate;
- non vengono creati una copia del Progetto o duplicati delle Spese;
- il Progetto e le Spese entrano nel Budget in Lavorazione e quindi nel Budget Proposto del nuovo
  anno.

Se il Progetto era già presente nel Budget Approvato dell'anno originario, la fotografia approvata
resta immutata. Il confronto dell'anno originario continua quindi a mostrare il Previsto e
l'assenza di Effettivi, mentre il nuovo anno mostra il Progetto nel proprio Budget Proposto.

#### Progetto Con Effettivi

Se almeno una Spesa contiene un Effettivo, gli importi già sostenuti non vengono riclassificati in un
altro anno:

1. il Progetto originario resta nel proprio Anno Economico;
2. le Spese con Effettivi restano collegate al Progetto originario;
3. il Progetto originario viene chiuso alla Data indicata dall'Utente;
4. il Software crea un nuovo Progetto nell'anno di destinazione;
5. il nuovo Progetto conserva lo stesso nome e mostra l'indicazione
   `Continuazione del Progetto 2025` senza concatenare tale testo al nome;
6. il nuovo Progetto entra nel Budget in Lavorazione e nel Budget Proposto dell'anno di destinazione;
7. le due istanze restano collegate tramite il solo riferimento `Progetto Precedente`.

Il riferimento inverso al Progetto successivo e l'intera sequenza delle continuazioni vengono
ricavati dal Software. Non viene introdotta un'ulteriore entità `Famiglia di Progetti`.

Le Spese senza Effettivi possono essere spostate al nuovo Progetto senza duplicarle. Una Spesa che
possiede già Effettivi resta interamente nel Progetto originario; un eventuale importo ancora da
riproporre viene rappresentato da una nuova Spesa nel Progetto di continuazione, senza spostare o
riscrivere le Righe storiche.

#### Importo da Riproporre

Per ogni Spesa con Effettivi dalla quale resta un importo da riproporre, il Software calcola:

```text
Base della Riproposta = Preventivo Corrente, se presente
                       altrimenti Stima Corrente, se presente
                       altrimenti Previsto

Importo da Riproporre Suggerito =
max(Base della Riproposta − Totale Effettivo, €0)
```

Il risultato è soltanto un suggerimento. Prima della creazione della nuova Spesa, l'Utente deve:

- confermare l'Importo da Riproporre Suggerito; oppure
- inserire un importo differente, compreso zero se non intende riproporre quella Spesa.

L'importo confermato alimenta la nuova Spesa nel Budget Proposto dell'anno di destinazione. Non
modifica il Previsto, gli Effettivi o lo Scostamento del Progetto originario.

La nuova Spesa nasce con una Stima pari all'Importo da Riproporre confermato. La Stima contribuisce
al valore del Budget Proposto del nuovo anno. Se il Budget viene approvato, tale valore viene fissato
come Previsto; un eventuale nuovo Preventivo potrà essere aggiunto successivamente senza riscrivere
la Stima originaria.

Esempio:

```text
Previsto 2025:                 €10.000
Preventivo Corrente:            €8.000
Totale Effettivo 2025:          €2.000
Importo da Riproporre Suggerito: €6.000

Budget Approvato 2025: mantiene il Previsto di €10.000
Budget Finale 2025: mostra Effettivo €2.000 e Scostamento −€8.000
Budget Proposto 2026: riceve la nuova Spesa di €6.000 dopo la conferma
```

I €6.000 non vengono detratti dal Budget Approvato 2025 e non costituiscono una Rettifica negativa:
sono una nuova proposta economica per completare il Progetto nel 2026.

#### Anteprima dello Spostamento

Prima della conferma, il Software presenta una singola Vista di Impatto con:

- Anno del Progetto originario e anno di destinazione;
- Spese senza Effettivi che verranno spostate;
- Spese con Effettivi che resteranno nel Progetto originario;
- Effettivo complessivo che rimarrà attribuito all'anno originario;
- importi che l'Utente intende riproporre nel Budget Proposto del nuovo anno;
- Coperture Plafond che richiedono una correzione;
- Progetto originario da chiudere e Progetto di continuazione da creare.

L'Utente conferma l'operazione soltanto dopo avere verificato questa anteprima. Se una Spesa spostata
usa un Plafond dell'anno originario, la Copertura non viene rimossa automaticamente: il Software
mantiene l'Utente nella Vista di Impatto finché non seleziona un Plafond compatibile con il nuovo anno
oppure rimuove esplicitamente la Copertura.

### Vista delle Continuazioni

Il dettaglio di un Progetto collegato offre due ambiti di lettura:

- `Intero Percorso`, che comprende il Progetto selezionato, i precedenti e le eventuali
  continuazioni;
- `Solo Questo Progetto`, che mostra esclusivamente l'istanza dell'anno selezionato.

Nell'ambito `Intero Percorso`, l'Utente può inoltre filtrare per Anno Economico. La Vista mostra:

- Totale Effettivo dell'intero Percorso;
- Effettivo per ciascun Anno del Progetto;
- Budget Approvato, Budget Proposto ed Effettivo separati per anno;
- tutte le Spese, con Progetto e Anno Economico di appartenenza;
- collegamenti navigabili tra Progetto precedente e successivo.

Non viene mostrato un unico `Previsto Totale` privo di scomposizione annuale, perché la somma del
Previsto originario e degli importi riproposti potrebbe conteggiare due volte la stessa intenzione di
spesa. Il Totale Effettivo dell'intero Percorso rimane invece sempre calcolabile senza duplicazioni.

## Composizione Automatica del Budget Annuale

Quando esistono dati applicabili a un anno, il Software compone automaticamente il relativo Budget
in Lavorazione usando:

- Spese generate dai Rinnovi dei Contratti previsti nell'anno;
- Spese appartenenti a Progetti il cui Anno del Progetto corrisponde all'anno del Budget, anche se la
  Data della Spesa appartiene a un anno diverso;
- Spese non collegate a Progetti con Data della Spesa o pianificazione economica riferita all'anno;
- nuove Spese inserite manualmente.

Non sono previsti:

- una Copia Manuale dell'Anno Precedente;
- un Wizard obbligatorio di Rollover;
- stati annuali come `Incompleto`, `In Verifica` o `Verificato`;
- la duplicazione annuale dei Progetti.

Parallelamente alla gestione dell'anno corrente, l'Utente può lavorare sul Budget in Lavorazione
dell'anno successivo. Questo Budget si alimenta automaticamente dai Contratti e dalle Spese future
già note, senza alterare il Budget Corrente dell'anno in corso.

## Avvio di un Nuovo Tenant e Ricostruzione Storica

La Data corrente non chiude automaticamente gli Anni Economici creati durante l'avvio di un nuovo
Tenant. Un anno cronologicamente passato nasce modificabile e permette di ricostruire la situazione
storica usando i normali Flussi di Spese, Contratti, Progetti e Plafond.

Esempio nel 2026:

```text
Creazione Anno Economico 2024 → Modificabile
Creazione Anno Economico 2025 → Modificabile
Anno Economico 2026            → Corrente
```

Durante la ricostruzione del 2024 o del 2025:

- gli Effettivi vengono registrati normalmente e non sono Rettifiche;
- i Contratti possono generare le Spese relative ai Rinnovi storici;
- i Progetti e le loro Spese vengono attribuiti al rispettivo Anno del Progetto;
- l'Utente può ricostruire il Budget Proposto e approvarlo, quando dispone dei dati originari;
- se non dispone di un Budget originario, il Software ricava il Previsto Ricostruito dagli
  Effettivi e dalla classificazione Extra Budget;
- il Software non usa Stati come `Incompleto`, `In Verifica` o `Verificato`.

Esempio senza Budget originario:

```text
Effettivi non Extra Budget: €80.000 → Previsto Ricostruito €80.000
Effettivi Extra Budget:      €5.000 → Previsto Ricostruito      €0

Previsto Ricostruito: €80.000
Effettivo Totale:      €85.000
Extra Budget:           €5.000
Differenza:             +€5.000
```

Il Riepilogo di Chiusura indica chiaramente `Previsto Ricostruito dagli Effettivi` e richiede la
conferma dell'Utente. Non registra una Data di Approvazione o un Approvatore inesistenti.

Quando la ricostruzione dell'anno è terminata, l'Utente esegue esplicitamente la Chiusura attraverso
il normale Riepilogo di Chiusura. Da quel momento:

- viene creato il Budget Finale;
- ogni nuova registrazione o modifica economica riferita all'anno segue il Flusso di Rettifica;
- la Nota diventa obbligatoria nei casi già previsti per le Rettifiche;
- l'anno non viene riaperto per completare dati dimenticati.

La Chiusura degli anni storici può avvenire in ordine cronologico, così che Contratti, Progetti da
continuare, Spese da riproporre e Plafond suggeriti alimentino correttamente gli anni successivi.
L'ordine cronologico è consigliato dal Software, ma l'assenza di una Chiusura precedente non assegna
uno Stato di errore all'anno successivo.

Nei Report e nei Selettori di Confronto, il valore viene indicato come `Previsto Ricostruito` e non
come `Budget Approvato`. Può essere confrontato con Effettivo e Budget Finale di qualsiasi anno. Un
indicatore informativo spiega che la base deriva dagli Effettivi non Extra Budget e non da una
fotografia approvata all'epoca.

## Flusso Annuale Semplificato

### 1. Preparazione

1. Il Software compone il Budget in Lavorazione dai dati disponibili.
2. L'Utente aggiunge o corregge Spese, Contratti e Progetti.
3. Il Software aggiorna automaticamente totali e Reportistica.
4. Il Budget Proposto presenta la situazione e le differenze rilevanti.

### 2. Approvazione

1. Il Budget Proposto viene accettato.
2. Vengono registrati data, approvatore ed eventuali note.
3. Viene creata la fotografia immutabile del Budget Approvato.
4. I Previsti approvati diventano la base di confronto stabile dell'anno.

### 3. Gestione Durante l'Anno

1. Vengono registrati gli Effettivi.
2. Le nuove esigenze vengono registrate come Spese Extra Budget con la Nota obbligatoria sulla Riga.
3. Errori e omissioni vengono gestiti tramite Rettifiche.
4. Il Software calcola le differenze numeriche senza classificarne le cause.
5. Il Budget Corrente e la Reportistica si aggiornano automaticamente.

### 4. Chiusura

1. Il Software apre il Riepilogo di Chiusura.
2. L'Utente consulta le situazioni rilevanti e può agire sui singoli elementi.
3. Il Riepilogo aggiorna immediatamente impatti e totali dopo ogni azione.
4. L'Utente può confermare la Chiusura anche senza risolvere tutte le situazioni elencate.
5. Il Software crea il Budget Finale immutabile.
6. Finché non esistono Rettifiche, un Utente autorizzato può annullare la Chiusura; in alternativa,
   le correzioni successive vengono registrate come Rettifiche al Budget Finale e ne impediscono la
   Riapertura.

### Riepilogo di Chiusura

Il Riepilogo di Chiusura è una Vista assistita e non bloccante. Non assegna Stati come `Incompleto`
o `Da Verificare` all'Anno Economico e non obbliga l'Utente a completare un Wizard.

Mostra almeno:

- Budget Approvato, Effettivo, Extra Budget e Rettifiche;
- Spese eliminate e relativo effetto economico;
- Spese senza Effettivi;
- Progetti ancora aperti;
- Contratti che proseguiranno nell'anno successivo;
- Impegnato, Copertura Prevista, Consumato, Residuo e Sforamento dei Plafond.

Ogni sezione spiega cosa accade se l'Utente non interviene. Le azioni sono facoltative e
`Conferma Chiusura` resta disponibile. Un'azione completata aggiorna il Riepilogo senza uscire dal
Flusso di Chiusura.

#### Spese Senza Effettivi

Per ogni Spesa senza Effettivi sono disponibili:

- `Ripropone nel 2026`, che applica le regole delle Spese Ordinarie Non Realizzate;
- `Registra Effettivo`;
- `Elimina`;
- `Visualizza`.

Se l'Utente non interviene, la Spesa resta nell'anno che si sta chiudendo con Effettivo zero e il
relativo Scostamento. Non viene riportata automaticamente nel nuovo anno.

#### Progetti Aperti

Per ogni Progetto Aperto, il Riepilogo mostra Anno del Progetto, Previsto, Totale Effettivo, Spese
senza Effettivi e l'eventuale Importo da Riproporre.

Se il Progetto non possiede Effettivi, sono disponibili:

- `Sposta nel 2026`, che sposta il medesimo Progetto e le relative Spese;
- `Chiudi Progetto`, che lo conclude nell'anno corrente senza Continuazione;
- `Mantieni Aperto nel 2025`;
- `Visualizza`.

Se il Progetto possiede Effettivi, sono disponibili:

- `Crea Continuazione nel 2026`, che chiude il Progetto originario e applica il Flusso di
  Continuazione già definito;
- `Chiudi Progetto`, che lo conclude nell'anno corrente senza Continuazione;
- `Mantieni Aperto nel 2025`;
- `Visualizza`.

L'anno mostrato nelle Etichette delle azioni deriva dal Budget che si sta chiudendo e dal relativo
anno successivo.

Se l'Utente sceglie `Mantieni Aperto nel 2025`, il Progetto resta economicamente attribuito al 2025.
Dopo la Chiusura del Budget, ogni nuovo Effettivo collegato al Progetto sarà quindi una Rettifica del
Budget Finale 2025, anche se la Data della Spesa appartiene al 2026. Questa conseguenza viene mostrata
accanto all'azione prima della conferma.

#### Contratti che Proseguono

Per ogni Contratto Attivo, il Riepilogo mostra la prossima Data di Rinnovo, l'Importo della Riga
applicabile e l'eventuale Rinnovo Automatico.

Sono disponibili:

- `Visualizza`;
- `Disattiva`;
- `Cessa Anticipatamente`.

Se l'Utente non interviene, il Contratto continua normalmente. Con Rinnovo Automatico, la Riga del
nuovo anno alimenta il Budget Proposto come Preventivo e diventa Effettivo quando il nuovo anno
diventa corrente, secondo le regole già consolidate.

#### Plafond

Per ogni Plafond, il Riepilogo mostra Impegnato, Copertura Prevista, Consumato, Residuo e Sforamento,
con accesso a:

- `Ripropone nel 2026`, quando il Residuo è positivo;
- `Visualizza Spese Coperte`;
- `Apri Report dei Plafond`.

La presenza di un Residuo o di uno Sforamento non impedisce la Chiusura. Il Riepilogo deve spiegare
esplicitamente che nessun valore viene modificato automaticamente dalla Chiusura.

L'azione `Ripropone nel 2026` crea un nuovo Plafond nell'anno successivo:

- il Residuo del Plafond originario viene proposto come Importo iniziale;
- l'Utente deve confermare l'Importo o modificarlo;
- l'Importo confermato viene registrato come Stima del nuovo Plafond e alimenta il Budget Proposto;
- il nuovo Plafond conserva il collegamento `Riproposto dal Plafond 2025`;
- Impegnato, Consumato, Residuo e Coperture del Plafond originario restano invariati;
- le Spese e le Quote di Copertura non vengono trasferite automaticamente al nuovo Plafond.

Se il Residuo è zero o negativo, l'azione di Riproposta non viene mostrata. L'Utente può comunque
creare normalmente un nuovo Plafond per l'anno successivo.

#### Conferma Finale

Prima della conferma definitiva, il Software mostra i totali aggiornati e riepiloga le azioni
eseguite. La conferma registra Data e Utente e crea il Budget Finale.

## Spese Ordinarie Non Realizzate

Una Spesa Ordinaria senza Effettivi, non collegata a un Progetto o a un Contratto, non viene riportata
automaticamente nell'anno successivo. Il Software non può dedurre se sia stata rinviata, abbandonata
o semplicemente non ancora registrata.

Nel Budget in Lavorazione dell'anno successivo viene mostrata la Vista derivata
`Spese Non Realizzate dell'Anno Precedente`. Non è uno Stato della Spesa, non modifica il Budget
precedente e non costituisce un Wizard obbligatorio.

La Vista include le Spese che:

- appartengono all'Anno Economico precedente;
- non hanno Effettivi;
- non appartengono a un Progetto o a un Contratto;
- non sono state eliminate;
- non sono già state riproposte nell'anno di destinazione.

Per ogni Spesa sono disponibili:

- `Ripropone nel 2026`;
- `Registra Effettivo`;
- `Elimina`;
- `Apri Dettaglio`.

L'anno indicato dall'azione `Ripropone` corrisponde all'Anno Economico del Budget in Lavorazione
corrente e non è un'etichetta fissa.

### Riproporre la Spesa

L'azione `Ripropone` crea una nuova Spesa nell'anno di destinazione con una Stima iniziale. Il
Software propone, nell'ordine, il Preventivo Corrente, la Stima Corrente oppure il Previsto della
Spesa originaria; l'Utente può confermare o modificare l'importo.

La nuova Spesa:

- alimenta il Budget Proposto dell'anno di destinazione;
- conserva un collegamento `Riproposta dalla Spesa 2025` alla Spesa originaria;
- non modifica Previsto, Effettivi o Scostamento dell'anno precedente;
- non conserva automaticamente una Copertura su un Plafond dell'anno precedente.

Se l'Utente desidera una Copertura Plafond, deve selezionare un Plafond compatibile con il nuovo Anno
Economico e completare le normali Quote di Copertura.

### Registrare un Effettivo

L'azione `Registra Effettivo` apre la Spesa originaria direttamente nella sezione delle Righe:

- se il relativo Anno Economico è ancora aperto, l'Effettivo viene registrato normalmente;
- se l'Anno Economico è chiuso, l'Effettivo viene classificato come Rettifica e la Nota è
  obbligatoria.

Dopo la registrazione dell'Effettivo, la Spesa non compare più nella Vista delle Spese Non
Realizzate.

### Eliminare una Spesa

L'azione `Elimina` è disponibile anche quando la Spesa possiede Effettivi. L'Eliminazione sposta
l'intero aggregato della Spesa nel Cestino: Spesa, Righe, Collegamenti e Allegati non sono più
visibili nelle normali Viste operative, ma restano recuperabili fino all'Eliminazione Definitiva.
La Spesa viene immediatamente esclusa dal dataset economico corrente.

Quando la Spesa era compresa in un Budget Approvato, l'eliminazione non cancella la fotografia
approvata:

```text
Budget Approvato 2025: €1.500
Effettivo registrato:  €1.500
Effettivo conteggiato:     €0
Scostamento 2025:      −€1.500
Spesa: Eliminata
```

Effetti dell'eliminazione:

- Previsto e fotografia originaria del Budget Approvato restano immutati;
- Stime, Preventivi ed Effettivi della Spesa non partecipano più ai calcoli correnti;
- l'eventuale Effettivo Extra Budget viene escluso dal totale Extra Budget;
- le Quote Effettive non alimentano più il Consumato dei Plafond e la relativa disponibilità viene
  liberata;
- la Spesa non compare più tra le Spese Attive o nelle Spese Non Realizzate;
- il Cestino conserva valori, Righe, collegamenti e Allegati necessari al Ripristino;
- la Cronologia conserva Data, Utente, operazione ed eventuale Nota dell'Eliminazione;
- una Spesa generata da un Contratto non viene ricreata automaticamente dopo l'eliminazione.

Se la Spesa non contiene Effettivi, l'eliminazione non genera alcun effetto economico. Se contiene
Effettivi, la Nota dell'eliminazione è sempre obbligatoria. Prima della conferma, una Vista di Impatto
mostra almeno:

- il Totale Effettivo che verrà escluso;
- l'eventuale Effettivo Extra Budget che verrà escluso;
- le Quote di Consumo Plafond che verranno liberate;
- i Budget e i Report interessati.

Se la Nota manca, il Software mostra un Avviso, non salva e mantiene la Spesa in Modifica con i dati
inseriti. Se l'Anno Economico è aperto, dopo la conferma i Report correnti vengono ricalcolati
immediatamente.

Se contiene Effettivi e l'Anno Economico è già chiuso:

- l'eliminazione viene registrata come Rettifica;
- l'effetto economico della Rettifica è pari all'opposto del Totale Effettivo escluso;
- la Nota è obbligatoria;
- il Budget Finale continua a conservare sia l'Importo alla Chiusura sia l'effetto della Rettifica.

La Reportistica esclude per impostazione predefinita le Spese nel Cestino. Nel Drill-Down, il Filtro
`Mostra Eliminate` permette agli Utenti autorizzati di consultarle senza reinserirle nei totali. Dopo
l'Eliminazione Definitiva, il dettaglio della Spesa non è più disponibile; restano soltanto le
evidenze economiche non recuperabili necessarie a mantenere coerenti Budget Approvati, Budget Finali
e Rettifiche.

## Cestino ed Eliminazione Definitiva

### Responsabilità del Cestino

Il Cestino gestisce il recupero di un'Entità eliminata. Non è una Versione dell'Entità e non è una
Vista alternativa della Reportistica. È unico per Tenant, Multi-Entità e Multi-Anno: non vengono
creati Cestini separati per Spese, Contratti, Progetti, Fornitori, Centri di Costo o singoli Anni
Economici.

Il Cestino comprende tutte le Entità di Dominio per le quali il Software offre un'Eliminazione
recuperabile. Non comprende Snapshot, Revisioni, Budget Approvati o Budget Finali, che seguono le
rispettive regole di conservazione.

Una Spesa nel Cestino:

- mantiene la stessa Identità e lo stesso Anno Economico;
- conserva tutte le Righe, i Collegamenti e gli Allegati correnti;
- non partecipa a Budget, Report, Extra Budget, Copertura Prevista o Consumato dei Plafond;
- non può essere modificata direttamente;
- può essere soltanto visualizzata, Ripristinata oppure Eliminata Definitivamente;
- mostra Data di Eliminazione, Utente, eventuale Nota e data prevista per l'Eliminazione Definitiva.

Un Utente può vedere o recuperare esclusivamente gli elementi del Tenant corrente e soltanto se
possiede le Autorizzazioni richieste per la relativa Entità.

### Dipendenze Necessarie al Ripristino

Il Ripristino non può produrre un'Entità attiva collegata a una Dipendenza obbligatoria ancora nel
Cestino. Quando il Software rileva questa situazione:

- non Ripristina l'elemento selezionato;
- mostra un Avviso bloccante;
- elenca Tipo, Nome e Stato di ogni Dipendenza necessaria;
- offre l'azione `Apri nel Cestino` per ciascuna Dipendenza;
- permette di riprovare il Ripristino dopo che tutte le Dipendenze sono tornate attive.

Le Dipendenze non vengono Ripristinate automaticamente e non viene introdotto un Ripristino
ricorsivo dell'intera catena. Ogni Ripristino resta una decisione esplicita dell'Utente e produce la
propria Cronologia e gli eventuali Impatti Economici.

Se una Dipendenza è già stata Eliminata Definitivamente, il Software mantiene il blocco e spiega che
il Ripristino non è più possibile finché l'Utente non seleziona, quando ammesso dal Dominio, una nuova
Dipendenza valida. Se l'Utente non è autorizzato a Ripristinare una Dipendenza, può visualizzare
soltanto l'indicazione necessaria e deve rivolgersi a un Utente autorizzato.

### Eliminazione di un'Entità Referenziata

L'Eliminazione non si propaga automaticamente alle Entità dipendenti. Prima di spostare un elemento
nel Cestino, il Software distingue:

- **Riferimenti Attivi**: elementi operativi correnti o futuri che richiedono ancora l'Entità;
- **Riferimenti Storici**: elementi consolidati che devono soltanto continuare a mostrare il contesto
  con cui erano stati registrati.

Se esiste almeno un Riferimento Attivo, il Software:

- mostra un Avviso bloccante e non esegue l'Eliminazione;
- elenca Tipo, Nome, eventuale Anno Economico e collegamento agli elementi interessati;
- richiede all'Utente di riassegnare, chiudere o eliminare esplicitamente gli elementi dipendenti
  secondo le rispettive regole di Dominio;
- non modifica Collegamenti e non sposta automaticamente altre Entità nel Cestino.

Se restano soltanto Riferimenti Storici, l'Eliminazione è consentita. Le Viste storiche conservano
la Denominazione minima necessaria a comprendere il dato, ad esempio `Fornitore: Alfa S.r.l.`, senza
rendere nuovamente utilizzabile l'Entità eliminata. La Denominazione storica:

- non rappresenta un'Entità attiva selezionabile;
- non consente di modificare retroattivamente il riferimento;
- resta leggibile anche dopo l'Eliminazione Definitiva;
- contiene soltanto le informazioni minime necessarie alla comprensione dello storico.

Esempio: un Fornitore utilizzato da una Spesa dell'Anno corrente non può essere eliminato finché la
Spesa non viene riassegnata o gestita esplicitamente. Se il Fornitore compare soltanto in Spese di
Anni Chiusi, può essere spostato nel Cestino e i Report continuano a mostrarne la Denominazione
storica.

### Flusso di Ripristino dal Cestino

`Ripristina` riattiva la stessa Spesa; non crea una copia e non genera una nuova Identità. Prima della
conferma, una Vista di Impatto mostra almeno:

- Effettivi che rientreranno nei Totali;
- eventuale Importo Extra Budget che verrà reintrodotto;
- Quote Effettive che torneranno a consumare i Plafond;
- Contratto o Progetto collegato;
- Budget e Report interessati.

Il Ripristino recupera con un'unica operazione la Spesa, tutte le sue Righe, i Collegamenti e gli
Allegati ancora presenti nel Cestino. Non permette un Ripristino parziale delle sole Righe.

Se l'Anno Economico è aperto, il Software ricalcola immediatamente il Budget Corrente. Se l'Anno
Economico è chiuso, il Ripristino produce una nuova Rettifica di segno opposto all'effetto
dell'Eliminazione e richiede una Nota. In questo modo l'Eliminazione e il successivo Ripristino
restano entrambi leggibili senza riscrivere il Budget Finale.

Il Ripristino dal Cestino viene registrato nella Cronologia delle Versioni come una nuova operazione,
ma non equivale a `Ripristina Versione`.

### Eliminazione Definitiva

L'Eliminazione Definitiva rimuove il contenuto recuperabile della Spesa, comprese Righe e Allegati.
Dopo l'operazione:

- la Spesa non può essere recuperata dal Cestino;
- la Cronologia delle Versioni non può essere usata come percorso alternativo per ricrearla;
- eventuali Snapshot non più necessari al Budget storico non restano ripristinabili;
- i Totali e le Rettifiche già prodotti non cambiano;
- resta soltanto l'evidenza minima non ripristinabile richiesta per spiegare e mantenere coerente il
  risultato economico storico.

Lo Svuotamento Automatico del Cestino è una Manutenzione separata dalla Retention della Cronologia
delle Versioni. Ogni elemento viene Eliminato Definitivamente dopo 12 mesi dalla propria Data di
Eliminazione. La scadenza è quindi individuale e non dipende da una data annuale comune.

La Manutenzione deve essere idempotente, eseguita periodicamente dallo Scheduler e non deve
eliminare alcun elemento prima della propria scadenza. Il fatto che il Cestino sia Multi-Anno non
modifica la durata: un elemento del 2025 eliminato nel 2027 resta recuperabile per 12 mesi a partire
dalla Data di Eliminazione del 2027.

### Vista del Cestino

La Vista `Cestino` usa una Tabella unica con Ricerca e Filtri per Tipo di Entità, Anno Economico,
Data di Eliminazione e Utente. Il Filtro Anno si applica soltanto alle Entità che possiedono un Anno
Economico; per le altre viene mostrato `—` e l'elemento resta raggiungibile tramite Ricerca e Filtro
Tipo di Entità.

Ogni Riga mostra:

- Nome o Titolo;
- Tipo di Entità;
- Anno Economico, quando applicabile;
- eventuale Impatto Economico escluso;
- Data e Utente dell'Eliminazione;
- tempo residuo prima dell'Eliminazione Definitiva;
- azioni `Visualizza`, `Ripristina` ed eventualmente `Elimina Definitivamente`.

L'apertura del dettaglio usa la Vista in sola lettura propria dell'Entità selezionata. Non viene
creata una schermata generica che tenti di rappresentare nello stesso modo Spese, Contratti,
Progetti e Anagrafiche.

L'Eliminazione Definitiva manuale richiede una conferma esplicita che chiarisce che Versioning e
Cronologia non consentiranno il recupero. Lo Svuotamento Automatico non richiede interazione.

## Cronologia delle Versioni della Spesa

### Distinzione tra Cronologia e Cestino

La Cronologia delle Versioni racconta come è cambiata una Spesa esistente. Il Cestino permette
invece di recuperare una Spesa eliminata. La Rettifica rappresenta l'effetto economico di una
modifica successiva alla Chiusura. Le tre responsabilità non vengono fuse:

| Meccanismo | Domanda a cui risponde | Effetto sul Budget |
|---|---|---|
| Cronologia delle Versioni | Come era questa Spesa in un momento precedente? | Nessuno finché non viene eseguito un Ripristino |
| Cestino | Posso recuperare questa Spesa eliminata? | La Spesa resta esclusa finché non viene Ripristinata |
| Rettifica | Come è cambiato economicamente un Anno Chiuso? | Modifica il valore rappresentato del Budget Finale |

### Flusso UX in Stile Google Drive

Dalla Vista della Spesa, l'azione `Cronologia delle Versioni` apre un Pannello laterale con gli
Snapshot disponibili, ordinati dal più recente. Ogni Snapshot mostra almeno Data e Ora, Utente o
`Sistema`, tipo di operazione e un breve riepilogo.

Quando l'Utente seleziona uno Snapshot:

1. la normale Vista della Spesa resta il contenitore principale;
2. la Vista passa chiaramente in modalità `Snapshot del [Data e Ora]` e diventa di sola lettura;
3. vengono mostrati Intestazione, Righe e Collegamenti come risultavano nello Snapshot;
4. i Campi diversi dallo stato corrente vengono evidenziati direttamente nella loro posizione;
5. per ogni Campo evidenziato è possibile leggere Valore dello Snapshot e Valore Corrente;
6. le Righe aggiunte o rimosse sono rappresentate nello stesso elenco, senza una Tabella tecnica
   separata;
7. l'azione `Torna alla Versione Corrente` ripristina la normale Vista modificabile.

La selezione confronta lo Snapshot con la Versione Corrente. Non viene introdotto il confronto
arbitrario tra due Snapshot, così la schermata resta comprensibile e coerente con il comportamento
già previsto.

### Ripristinare una Versione

Se l'Utente è autorizzato, lo Snapshot offre l'azione `Ripristina Questa Versione`. La conferma
mostra le differenze che verranno applicate e gli eventuali impatti economici. Il Ripristino:

- applica l'intero aggregato `Spesa + Righe`, non singoli Campi isolati;
- rivalida le regole correnti della Spesa e delle Coperture Plafond;
- non cancella né riscrive gli Snapshot precedenti;
- crea un nuovo Snapshot che rappresenta lo stato raggiunto dopo il Ripristino;
- segue le normali regole di Rettifica e Nota se modifica un Anno Economico Chiuso;
- non è disponibile per una Spesa nel Cestino o Eliminata Definitivamente.

Gli Allegati correnti sono conservati dal Cestino, ma non diventano copie binarie dentro ogni
Snapshot. La Cronologia può indicare le operazioni sugli Allegati, ma `Ripristina Questa Versione`
non ricrea un file già eliminato. Questa separazione evita la duplicazione invisibile dei file a ogni
modifica della Spesa.

### Retention degli Snapshot

Il numero di Snapshot disponibili nella Cronologia delle Versioni è un'Impostazione del Tenant,
modificabile soltanto dagli Utenti autorizzati. Il valore predefinito è `10 Versioni` per ciascuna
Entità e si applica agli Snapshot logici completi, non al numero delle singole Righe tecniche prodotte
da una modifica aggregata.

Il valore configurato è comune a tutti gli Utenti e a tutti i Tipi di Entità del Tenant. Non vengono
introdotti limiti personali o configurazioni differenti per Spese, Contratti, Progetti e Anagrafiche.

Quando il limite viene raggiunto, gli Snapshot meno recenti eccedenti diventano indisponibili per
Consultazione, Confronto e Ripristino attraverso la normale Manutenzione schedulata. Questa Retention
resta indipendente dai 12 mesi del Cestino: modificare il numero delle Versioni non anticipa né
posticipa l'Eliminazione Definitiva degli elementi nel Cestino.

## Continuazione di una Spesa Ordinaria

Una Spesa Ordinaria non collegata a un Progetto o a un Contratto appartiene a un solo Anno
Economico. Più Effettivi sono consentiti soltanto quando appartengono al medesimo anno.

Se una parte della decisione economica deve essere attribuita all'anno successivo, il Software usa
due Spese collegate:

```text
Spesa 2025 — Installazione Impianto
├── Previsto:  €2.000
└── Effettivo:   €500

Spesa 2026 — Installazione Impianto
├── Collegata alla Spesa 2025
├── Stima:     €1.500
└── Effettivo: €1.500
```

La Spesa originaria conserva Previsto, Effettivi e Scostamento del proprio anno. La nuova Spesa:

- possiede il nuovo Anno Economico;
- conserva un riferimento `Continuazione della Spesa 2025` alla Spesa precedente;
- nasce con una Stima suggerita, confermabile o modificabile dall'Utente;
- usa come Importo suggerito il maggiore tra zero e la differenza fra Riga Previsionale Corrente o
  Previsto e Totale Effettivo della Spesa originaria;
- entra nel Budget Proposto se l'anno di destinazione è ancora in preparazione;
- è Extra Budget, con Nota obbligatoria, se il Budget di destinazione è già Approvato;
- è una Rettifica, con Nota obbligatoria, se l'Anno Economico di destinazione è già Chiuso.

La Copertura Plafond non viene trasferita. Se necessaria, l'Utente seleziona uno o più Plafond del
nuovo Anno Economico e conferma le relative Quote.

Le Spese collegate restano navigabili in entrambe le direzioni. Il dettaglio permette di vedere il
Totale Effettivo dell'intera sequenza e la scomposizione per Anno Economico, senza introdurre
un'entità autonoma `Famiglia di Spese`.

### Creazione Assistita della Spesa Collegata

Quando l'Utente inserisce su una Spesa Ordinaria un Effettivo la cui Data appartiene a un Anno
Economico differente, il Software mostra un Avviso bloccante per quel salvataggio:

```text
Questo Effettivo appartiene al 2026 e non può essere registrato nella Spesa 2025.

[Crea Spesa Collegata nel 2026] [Modifica Data] [Annulla]
```

- `Crea Spesa Collegata nel 2026` apre la nuova Spesa precompilata con Importo, Data, Descrizione e
  collegamento alla Spesa originaria;
- `Modifica Data` mantiene l'Utente sulla Riga corrente senza perdere i dati;
- `Annulla` chiude l'Avviso e mantiene la Spesa originaria invariata.

Prima del salvataggio della nuova Spesa, il Software mostra se confluirà nel Budget Proposto, sarà
Extra Budget oppure una Rettifica e applica gli eventuali obblighi di Nota. La nuova Copertura
Plafond deve essere scelta esplicitamente.

La Spesa collegata viene creata soltanto con la conferma finale dell'Utente. Se l'Utente abbandona il
Flusso, non vengono salvate Spese o Righe parziali.

## Reportistica e Flusso UX

La Reportistica deve sfruttare la natura interattiva dell'interfaccia React senza trasformare i
grafici in una seconda sorgente di calcolo.

### Regola dei Valori nel Report

Il Report Principale non deve trasformare Stime e Preventivi in valori equivalenti all'Effettivo.
Per impostazione predefinita, i totali e i grafici principali usano soltanto valori con significato
stabile:

- Budget Approvato, comprensivo delle Rettifiche;
- Effettivi registrati;
- Effettivi delle Spese Extra Budget, mantenuti distinguibili;
- Budget Finale, dopo la chiusura.

Una Stima o un Preventivo possono essere sovrastimati, sottostimati oppure includere elementi che
non saranno acquistati. Per questo motivo non determinano automaticamente il valore corrente della
Spesa e non vengono sommati ai totali principali.

La pagina dei Report dispone di un unico Switch `Mostra Valutazioni`. Quando è attivo:

- il Software sovrappone graficamente la Riga Previsionale Corrente di ogni Spesa;
- usa il Preventivo corrente, quando presente, oppure la Stima corrente;
- distingue visivamente Stima, Preventivo ed Effettivo attraverso colori, tratteggi, legenda e
  Tooltip coerenti con i componenti disponibili in TailAdmin e React;
- indica chiaramente `Valore informativo — non incluso nei totali`;
- non modifica KPI, totali o calcoli del Report Principale;
- permette il Drill-Down fino alla Spesa e alla Cronologia completa delle Righe.

Non vengono introdotti filtri separati che consentano di sommare liberamente Stime, Preventivi ed
Effettivi. Lo Switch modifica la visualizzazione, non la semantica economica del Report.

Esempio:

```text
Budget Approvato della Spesa: €1.200
Preventivo corrente:          €1.500  ← Valutazione opzionale
Effettivo registrato:            €400
```

Il Report Principale confronta €1.200 ed €400. Attivando `Mostra Valutazioni`, sovrappone anche
€1.500 senza concludere che rimangano da spendere €800 o €1.100. Un valore attendibile di spesa
futura richiederebbe un concetto autonomo simile al Forecast, che resta fuori dal Modello corrente.

In Reportistica, il Budget Corrente indica la vista viva dell'anno e non un terzo totale ottenuto
mescolando Previsto, Stima, Preventivo ed Effettivo. I suoi valori principali restano Budget
Approvato ed Effettivo; le Valutazioni costituiscono soltanto un livello grafico opzionale.

### Confronto Annuale

Il Confronto Annuale usa Grafici Predefiniti per sovrapporre visivamente due Budget selezionati. La
selezione iniziale propone normalmente Anno Precedente e Anno Attuale, ma non limita il confronto a
due anni consecutivi. Non è richiesto all'Utente di costruire grafici o configurare liberamente
serie e formule.

Il Software propone automaticamente il confronto più coerente con il momento di lavoro:

```text
Durante la preparazione:
Budget Finale Anno Precedente vs Budget Proposto Anno Attuale

Dopo l'approvazione:
Budget Finale Anno Precedente vs Budget Approvato Anno Attuale
                                  + Effettivo Anno Attuale sovrapposto, se scelto
```

Questa è soltanto la selezione iniziale. L'Utente mantiene sempre il controllo attraverso due
Selettori di Confronto:

```text
Confronta [Budget Finale · 2025 ▾] con [Budget Proposto · 2026 ▾]
```

Ogni selettore combina:

- Anno Economico;
- Valore da rappresentare: Budget Proposto, Budget Approvato, Previsto Ricostruito, Effettivo o
  Budget Finale, quando disponibile per l'anno scelto.

Nel Confronto Annuale, l'Anno selezionato è sempre l'Anno Economico. Una Spesa di un Progetto 2025
con Data della Spesa nel 2026 compare quindi nella Serie 2025 e non nella Serie 2026.

I due Selettori sono indipendenti. L'Utente può quindi confrontare, per esempio:

```text
Budget Finale 2024 vs Budget Approvato 2026
Budget Approvato 2025 vs Budget Finale 2025
Budget Finale 2023 vs Budget Finale 2026
```

Non esiste un vincolo di adiacenza tra gli anni. Nei Grafici e nella Tabella le serie assumono sempre
il nome effettivamente selezionato, evitando etichette fisse come `Anno Precedente` e `Anno Attuale`
quando non corrispondono alla scelta dell'Utente.

Il Budget Corrente non compare tra i Valori selezionabili perché rappresenta una vista e non una
misura economica autonoma. Stime e Preventivi continuano a essere gestiti esclusivamente dallo
Switch `Mostra Valutazioni` e non diventano serie principali del confronto.

Quando esistono Effettivi per l'anno usato come riferimento corrente nella selezione iniziale, lo
Switch `Mostra Effettivo` consente di sovrapporli alle due serie principali. È attivo nella selezione
iniziale successiva all'approvazione, ma l'Utente può sempre disattivarlo. Se l'Effettivo è già stato
scelto come una delle due serie principali, lo Switch non aggiunge una serie duplicata.

Quando l'Utente cambia uno dei Selettori:

- tutti i Grafici Predefiniti e la Tabella di Dettaglio si aggiornano insieme;
- le etichette riportano sempre sia l'Anno sia il Valore rappresentato;
- vengono offerte soltanto combinazioni per cui esistono dati;
- la selezione contestuale effettuata in precedenza su un grafico viene azzerata, evitando di
  mantenere Filtri non più coerenti con il nuovo confronto.

La pagina è composta da due aree coordinate:

1. **Area Grafici**, che offre la lettura sintetica del confronto;
2. **Vista di Dettaglio**, preferibilmente tabellare, che spiega i dati rappresentati nei grafici.

La Tabella di Dettaglio mostra almeno:

- elemento o raggruppamento selezionato;
- valore della Prima Serie Selezionata;
- valore della Seconda Serie Selezionata;
- Differenza Assoluta;
- Differenza Percentuale, quando calcolabile;
- accesso al Drill-Down delle Spese che compongono il valore.

Grafici e Tabella usano gli stessi dati e gli stessi calcoli. La Tabella non costituisce un secondo
Report indipendente.

### Interazione dal Grafico al Dettaglio

Un click su una Barra, un Punto, un Segmento o un'altra unità dati del grafico rappresenta una
richiesta di approfondimento contestuale.

Il Flusso UX è:

1. l'Utente seleziona un dato nel grafico;
2. il Software ricava il relativo contesto, come Anno, Centro di Costo, Progetto, Contratto, periodo
   o altra dimensione rappresentata;
3. applica automaticamente i Filtri corrispondenti alla Vista di Dettaglio;
4. evidenzia il dato selezionato nel grafico;
5. scorre la pagina fino alla Vista di Dettaglio;
6. mostra sopra il dettaglio i Filtri Attivi in forma leggibile;
7. l'Utente può modificare o azzerare i Filtri e può aprire il Drill-Down fino alle singole Spese.

Esempio:

```text
Click: Centro di Costo Servizi IT — Budget Approvato 2026

↓ Scroll automatico

Vista di Dettaglio
Filtri Attivi: [Servizi IT] [Budget Approvato 2026]
├── Budget Finale 2024
├── Budget Approvato 2026
├── Differenza
└── Spese incluse
```

Il click sulla legenda o su un'area priva di dati non modifica i Filtri e non provoca lo Scroll. La
navigazione avviene nella stessa pagina, sfruttando lo stato interattivo di React e i componenti di
TailAdmin, senza obbligare l'Utente ad aprire un Report separato.

Un Filtro per Anno applicato dal Grafico usa l'Anno Economico e non un intervallo sulla Data della
Spesa. In caso contrario, il Drill-Down del Budget 2025 escluderebbe erroneamente le Spese dei
Progetti 2025 sostenute nel 2026. La Data della Spesa resta un Filtro secondario, esplicito e distinto.

### Valutazione dei Componenti TailAdmin Free

Verifica effettuata il 12 agosto 2026 sull'intero Repository React Free ufficiale di TailAdmin,
Commit `21dc917cb6cb22b5f1d12e5af57359a849d19aa8`, Versione `2.3.0`. L'analisi ha cercato tutti gli
import di `react-apexcharts` nell'intero albero `src`, non soltanto nella cartella `components/charts`.

La versione gratuita mette a disposizione direttamente:

- `BarChartOne`, basato su `react-apexcharts`, con Barre verticali e categorie;
- `LineChartOne`, nominato Line ma renderizzato come Grafico Area con due serie;
- `MonthlySalesChart`, Widget Dashboard basato su Barre verticali;
- `StatisticsChart`, Widget Dashboard basato su Grafico Area con due serie e selezione temporale;
- `MonthlyTarget`, Widget Dashboard basato su `RadialBar`;
- componenti Tabella per costruire la Vista di Dettaglio;
- Tooltip, Legenda, serie multiple e comportamento responsive personalizzabili.

Sono quindi presenti cinque Componenti Grafico gratuiti, riconducibili a tre tipi effettivamente
renderizzati: `Bar`, `Area` e `RadialBar`.

Le pagine dedicate `Charts` espongono soltanto `BarChartOne` e `LineChartOne`; `RadialBar` è comunque
realmente incluso nella Dashboard Free attraverso `MonthlyTarget` e può essere riutilizzato. Pie,
Donut, Radar, Heatmap e altri Grafici presentati nel catalogo commerciale generale TailAdmin non
sono presenti nel Repository React Free verificato e non devono essere trattati come componenti
gratuiti disponibili.

I componenti inclusi usano ApexCharts. Con la configurazione della libreria sottostante, senza
dipendere da un componente TailAdmin Pro, le basi gratuite possono essere adattate a:

- Barre verticali raggruppate;
- Barre orizzontali raggruppate;
- Barre con valori positivi e negativi;
- Barre impilate, da usare soltanto quando le parti formano realmente un totale;
- Linee o Aree con più serie;
- Grafici Misti con Colonne e Linee;
- selezione del singolo Punto Dati per attivare il Flusso verso la Vista di Dettaglio.

Questi adattamenti sono nuove configurazioni costruite sulla dipendenza ApexCharts già inclusa; non
devono essere presentati come ulteriori Componenti TailAdmin Free già pronti.

Fonti di riferimento:

- [Repository TailAdmin React Free](https://github.com/TailAdmin/free-react-tailwind-admin-dashboard);
- [Cartella dei Grafici Free](https://github.com/TailAdmin/free-react-tailwind-admin-dashboard/tree/21dc917cb6cb22b5f1d12e5af57359a849d19aa8/src/components/charts);
- [BarChartOne](https://github.com/TailAdmin/free-react-tailwind-admin-dashboard/blob/21dc917cb6cb22b5f1d12e5af57359a849d19aa8/src/components/charts/bar/BarChartOne.tsx);
- [LineChartOne](https://github.com/TailAdmin/free-react-tailwind-admin-dashboard/blob/21dc917cb6cb22b5f1d12e5af57359a849d19aa8/src/components/charts/line/LineChartOne.tsx);
- [MonthlyTarget RadialBar](https://github.com/TailAdmin/free-react-tailwind-admin-dashboard/blob/21dc917cb6cb22b5f1d12e5af57359a849d19aa8/src/components/ecommerce/MonthlyTarget.tsx);
- [StatisticsChart](https://github.com/TailAdmin/free-react-tailwind-admin-dashboard/blob/21dc917cb6cb22b5f1d12e5af57359a849d19aa8/src/components/ecommerce/StatisticsChart.tsx);
- [MonthlySalesChart](https://github.com/TailAdmin/free-react-tailwind-admin-dashboard/blob/21dc917cb6cb22b5f1d12e5af57359a849d19aa8/src/components/ecommerce/MonthlySalesChart.tsx);
- [Eventi dei Punti Dati ApexCharts](https://apexcharts.com/docs/options/chart/events/).

### Catalogo Proposto dei Grafici Predefiniti

Il Report già implementato offre una base utile: un Grafico a Barre Raggruppate per la Dimensione
selezionata, un Grafico dello Scostamento e una Tabella di Dettaglio coordinata. Questi elementi
restano il nucleo del Confronto Annuale, adattati alla terminologia e alle regole consolidate.

Non vengono invece mantenuti nel Confronto Annuale:

- il Donut `Ripartizione del Proposto`, perché mette in primo piano un Valore che dopo l'Approvazione
  non è il riferimento principale e non facilita il confronto tra due Serie;
- il Donut `Stato Spese`, perché gli Stati `Aperta` e `Chiusa` della Spesa sono esclusi dal Dominio;
- un Grafico separato per ogni Dimensione, perché duplicherebbe lo stesso comportamento;
- un Grafico separato dei Totali, perché ripeterebbe gli stessi valori già esposti chiaramente nei
  KPI;
- il RadialBar di Utilizzo come Grafico iniziale, perché ha significato soltanto in alcune
  combinazioni dello stesso Anno e la percentuale è già leggibile nei KPI e nel Dettaglio.

Ogni Grafico iniziale risponde a una domanda distinta e applica un contesto utile alla Vista di
Dettaglio. La configurazione predefinita comprende quattro Grafici.

Tutti e quattro sono **Configurazioni Free** costruite sui Componenti e sulla Versione ApexCharts già
inclusi nel Repository. Non sono Widget TailAdmin già pronti e non richiedono Componenti TailAdmin
Pro.

| Ordine | Grafico | Base | Disponibilità | Domanda a cui risponde | Contesto applicato al click |
|---|---|---|---|---|---|
| 1 | Confronto per Dimensione | Barre Orizzontali Raggruppate | Configurazione Free da `BarChartOne` | Quali elementi pesano maggiormente nelle due Serie? | Serie e elemento della Dimensione |
| 2 | Differenza per Dimensione | Barre Orizzontali Positive/Negative | Configurazione Free da `BarChartOne` | Dove si trovano le maggiori differenze? | Elemento della Dimensione |
| 3 | Progressione Mensile Cumulativa | Linee o Aree | Configurazione Free da `LineChartOne` | Come si forma il valore nel corso dell'anno? | Serie e Mese |
| 4 | Extra Budget e Rettifiche | Colonne Raggruppate | Configurazione Free da `BarChartOne` | Quanto incidono gli eventi non compresi nel riferimento originario? | Serie e classificazione |

`Confronto per Dimensione` e `Differenza per Dimensione` condividono un unico Selettore:

- Centro di Costo, come valore predefinito;
- Progetto;
- Contratto;
- Fornitore;
- Spesa.

Il cambio di Dimensione aggiorna entrambi i Grafici e la Tabella di Dettaglio. Non crea nuove Card o
una Dashboard differente. Nei Grafici vengono mostrati inizialmente i dieci elementi di maggiore
impatto; la Tabella conserva l'accesso all'insieme completo mediante Ricerca, Filtri e Paginazione.

`Differenza per Dimensione` è ordinato per valore assoluto della Differenza, così le variazioni più
rilevanti emergono anche quando una è positiva e un'altra negativa. Il colore segnala la direzione,
ma Tooltip e Tabella mostrano sempre il valore numerico e non affidano l'informazione al solo colore.

`Progressione Mensile Cumulativa` confronta Gennaio-Dicembre anche quando le Serie appartengono ad
anni diversi. Richiede dati realmente attribuibili a un mese. Se una Serie dispone soltanto
dell'Anno Economico, il Software non distribuisce artificialmente il Totale e mostra nel riquadro del
Grafico che la granularità mensile non è disponibile.

`Extra Budget e Rettifiche` è condizionale: compare soltanto quando almeno una Serie contiene dati
pertinenti. Extra Budget e Rettifiche restano categorie separate e il Grafico non suggerisce che
siano lo stesso evento.

Le due Serie principali non devono essere impilate tra loro, perché il confronto non rappresenta una
somma. Le Barre Impilate restano appropriate soltanto per composizioni interne reali, ad esempio le
parti Ordinaria ed Extra Budget dello stesso valore.

### KPI del Confronto Annuale

Prima dei Grafici, una Riga compatta di KPI mostra:

- Totale della Prima Serie;
- Totale della Seconda Serie;
- Differenza Assoluta;
- Differenza Percentuale, quando calcolabile;
- Totale Effettivo sovrapposto, quando lo Switch è attivo e non duplica una Serie principale;
- Totale Extra Budget e Totale Rettifiche, soltanto quando presenti.

I KPI non introducono ulteriori misure economiche: riassumono le stesse Serie e gli stessi Filtri dei
Grafici e della Tabella.

### Grafici Dedicati ai Plafond

Nel Report dei Plafond possono essere riutilizzati gli stessi componenti gratuiti per mostrare:

- Impegnato, Copertura Prevista e Consumato per Centro di Costo mediante Barre Raggruppate;
- Residuo per Plafond mediante Barre Orizzontali;
- Sforamenti mediante Barre positive mostrate soltanto quando presenti;
- progressione mensile del Consumato mediante Linee o Aree;
- percentuale di Consumato su Impegnato mediante RadialBar, mantenendo lo Sforamento come valore
  separato quando il consumo supera il 100%.

Questi Grafici restano nel Report dedicato ai Plafond e non appesantiscono il Confronto Annuale
principale.

### Nota sulla Licenza della Libreria Grafica

TailAdmin React Free è pubblicato con Licenza MIT. Il Repository verificato dichiara ApexCharts
`^4.1.0` e il relativo `package-lock.json` blocca `apexcharts` alla Versione `4.4.0` e
`react-apexcharts` alla Versione `1.7.0`, entrambe indicate come MIT nel Lockfile.

La Licenza ufficiale delle versioni correnti di ApexCharts è successivamente cambiata e prevede
condizioni legate a fatturato e distribuzione. Per evitare un aggiornamento involontario delle
condizioni, il Piano di Implementazione dovrà verificare e bloccare esplicitamente la Versione della
dipendenza oppure scegliere una libreria con licenza compatibile. Questa nota riguarda un eventuale
Upgrade; non trasforma i Componenti presenti nel Repository Free verificato in Componenti Pro.

### Livello 1 — Panoramica

La Panoramica mostra almeno:

- Budget Approvato, comprensivo delle Rettifiche;
- Effettivo;
- Budget Finale, quando l'anno è chiuso;
- Effettivo delle Spese Extra Budget;
- impatto complessivo delle Rettifiche.

I grafici devono sovrapporre visivamente:

- la base del Budget Approvato;
- la progressione degli Effettivi;
- l'eventuale livello opzionale delle Valutazioni;
- gli scostamenti positivi e negativi.

Il Budget Approvato deve restare visivamente sottostante come riferimento stabile.

Nel Drill-Down del Budget Approvato, l'Utente può distinguere le Righe originarie dalle Righe inserite
o corrette tramite Rettifica. Il totale mostrato nella Panoramica comprende entrambe, mentre le Spese
Extra Budget restano una componente separata.

### Livello 2 — Raggruppamento

L'Utente può approfondire per:

- Centro di Costo;
- Progetto;
- Contratto;
- Fornitore;
- natura Ordinaria o Extra Budget.

### Livello 3 — Dettaglio delle Spese

Il dettaglio mostra almeno:

- Previsto del Budget Approvato;
- Stime e Preventivi rilevanti;
- uno o più Effettivi;
- differenza;
- Anno Economico e Data della Spesa quando appartengono ad anni differenti;
- origine della Spesa;
- natura Ordinaria o Plafond;
- eventuali Plafond usati come Copertura e relative Quote.

### Livello 4 — Cronologia

La Cronologia di una Spesa deve raccontarne l'evoluzione con eventi comprensibili, ad esempio:

```text
10 Gen — Inserita nel Budget Proposto: €1.000
20 Gen — Inclusa nel Budget Approvato: €1.000
15 Mar — Effettivo Registrato: €950
```

Per una Spesa Extra Budget:

```text
10 Giu — Nuova Richiesta Extra Budget: Nuovo PC
10 Giu — Stima Registrata: €400
10 Giu — Nota Extra Budget: sostituzione urgente per guasto
15 Giu — Preventivo Registrato: €410
20 Giu — Effettivo Registrato: €450
```

Per una Spesa coperta da Plafond:

```text
05 Feb — Spesa Registrata: Acquisto Cavetti
05 Feb — Copertura Selezionata: Plafond Servizi IT di Gennaio
05 Feb — Copertura Prevista: €120
10 Feb — Effettivo Registrato: €120
10 Feb — Plafond Consumato: €120
```

## Complessità Esplicitamente Escluse

- Entità Forecast autonoma.
- Modifica continua del Previsto durante l'anno.
- Workflow di Approvazione multi-stadio.
- Obbligo di Copertura per ogni Spesa Extra Budget.
- Richieste di Conferma via Email e Notifiche per le Spese Extra Budget nella prima passata.
- Procedura manuale obbligatoria di Rollover annuale.
- Duplicazione annuale dei Progetti.
- Entità autonoma `Famiglia di Progetti`: le Continuazioni usano un semplice collegamento al
  Progetto Precedente.
- Stati di qualità o completezza dell'anno.
- Copia completa del Budget a ogni minima modifica.
- Alberi di Versioni o Branch del Budget.
- Corrispondenza automatica non verificabile tra Spese manuali di anni differenti.
- Prenotazioni, Movimenti o Stati contabili separati per il Plafond.
- Stati obbligatori `Aperta` e `Chiusa` per la singola Spesa.
- Classificazione delle cause degli scostamenti.
- Campo, Tag o Workflow `Causa dello Scostamento`.
- Gestione dello Stato di Pagamento delle Spese, come `Da Pagare`, `Pagato` o `Scaduto`.
- Sostituzione successiva del Previsto Ricostruito con un Budget originario recuperato o mantenimento
  simultaneo di due basi storiche.

## Decisioni Consolidate

1. Usare Title Case per i termini importanti del Dominio.
2. Usare `Effettivo` e non `Actual`.
3. Non introdurre un'entità Forecast; usare il Previsto come riferimento approvato iniziale.
4. Il Previsto non cambia durante l'anno.
5. Una Spesa Extra Budget entra nel Budget Corrente senza modificare il Previsto originario.
6. Nella prima passata, selezionare `Extra Budget` richiede soltanto la Nota della Riga, resa
   obbligatoria per spiegarne il motivo; Email, Conferme e Notifiche sono rinviate.
7. La fotografia originaria del Budget Approvato è immutabile; omissioni e correzioni producono
   Rettifiche che entrano nel valore rappresentato del Budget Approvato senza sovrascriverla.
8. La chiusura crea un Budget Finale immutabile; correzioni successive producono Rettifiche.
9. Ogni Rinnovo previsto dai Termini del Contratto genera automaticamente una Spesa nell'Anno
   Economico della propria Data di Rinnovo.
10. I Progetti possono estendersi su più anni ma appartengono economicamente all'Anno del Progetto;
    tutte le loro Spese ereditano tale Anno Economico anche se hanno una Data della Spesa successiva.
11. Non è previsto un Workflow obbligatorio di Preparazione dell'Anno Successivo.
12. Il Software costruisce, aggiorna e presenta il Budget automaticamente dai dati di Dominio.
13. La Reportistica usa grafici sovrapposti e Drill-Down progressivo fino alla singola Spesa e alla
    sua Cronologia.
14. Una Spesa può contenere Righe di tipo Stima, Preventivo ed Effettivo senza una progressione
    obbligatoria.
15. Prima dell'approvazione, la Riga Previsionale Corrente di ogni Spesa contribuisce al Budget
    Proposto; dopo l'approvazione, Stime e Preventivi sono Valutazioni informative e non
    contribuiscono ai totali del Report Principale.
16. La natura Extra Budget è indipendente dal tipo delle Righe e dalla Copertura.
17. Il Plafond è una Spesa positiva assegnata a un Centro di Costo.
18. Più Plafond possono essere assegnati allo stesso Centro di Costo; un'estensione è un nuovo
    Plafond e non la modifica retroattiva del precedente.
19. L'appartenenza di una Spesa a un Centro di Costo non consuma automaticamente un Plafond.
20. Una Spesa può consumare un Plafond soltanto tramite Effettivi associati a una scelta esplicita di
    Copertura.
21. Le Spese coperte spiegano l'utilizzo del Plafond e non devono produrre un doppio conteggio nel
    Budget.
22. L'Impegnato è l'importo positivo stanziato con la creazione del Plafond.
23. Stime e Preventivi delle Spese collegate alimentano la Copertura Prevista ma non consumano il
    Plafond.
24. Soltanto gli Effettivi delle Spese collegate alimentano il Consumato e riducono il Residuo.
25. Quando esistono più Stime o Preventivi, una sola Riga Previsionale Corrente contribuisce alla
    Copertura Prevista; tutti gli Effettivi contribuiscono al Consumato.
26. Il Residuo del Plafond è calcolato come `Impegnato − Consumato`.
27. Impegnato, Copertura Prevista, Consumato e Residuo sono disponibili sia per singolo Plafond sia
    come aggregato del Centro di Costo.
28. Il dettaglio tra Copertura Prevista e Consumato appartiene al Report dei Plafond e non appesantisce
    il Report principale del Budget.
29. Quando un Preventivo diventa la Riga Previsionale Corrente, sostituisce la Stima nel calcolo della
    Copertura Prevista senza cancellarla; l'Effettivo resta separato e determina il Consumato.
30. Un Effettivo può superare il Residuo del Plafond: il Software mostra un Avviso, richiede una
    motivazione e registra comunque l'Effettivo.
31. La motivazione dello Sforamento viene riportata nelle Note Generali con il prefisso
    `Note Sforamento Plafond:`, senza un campo dedicato.
32. Il Residuo può essere negativo; lo Sforamento è calcolato come
    `max(Consumato − Impegnato, 0)` e non classifica automaticamente la Spesa come Extra Budget.
33. Una singola Spesa può ripartire la Copertura tra più Plafond dello stesso Centro di Costo.
34. La Copertura Ripartita usa una riga per ogni Plafond con il relativo Importo di Copertura; il
    Software propone la ripartizione e l'Utente può confermarla o modificarla.
35. La ripartizione assistita non consuma automaticamente tutti i Plafond del Centro di Costo e non
    obbliga l'Utente a esaurire prima uno specifico Plafond.
36. La Parzializzazione riguarda esclusivamente la Copertura: la Spesa e le sue Righe economiche non
    vengono duplicate.
37. Ogni Quota di Copertura associa un Plafond a un importo; se viene scelta la Copertura Plafond,
    la somma delle Quote deve coincidere con l'intero importo della Riga economica.
38. Quando viene registrato un Effettivo, il Software propone le Quote di Consumo mantenendo l'ordine
    dei Plafond della Copertura Prevista e adeguando la Quota necessaria al nuovo importo.
39. L'adeguamento proposto deve essere confermato dall'Utente, può essere modificato e non riscrive
    le Quote della Stima o del Preventivo.
40. Soltanto le Quote confermate di ciascun Effettivo alimentano il Consumato dei rispettivi Plafond.
41. Una Copertura incompleta non può essere salvata: l'Utente deve completarla, confermare un
    eventuale Sforamento oppure rimuovere la Copertura Plafond dalla Riga.
42. Il controllo di Copertura Integrale è distinto dalla Capienza: una Quota completa può superare il
    Residuo soltanto tramite il Flusso di Sforamento con motivazione obbligatoria.
43. Se la Copertura è incompleta, il tentativo di salvataggio mostra un Avviso, non registra la
    Spesa e mantiene l'Utente in Modifica senza perdere i dati inseriti.
44. Il totale mostrato come Budget Approvato comprende il Valore Approvato Originario e gli effetti
    delle Rettifiche successive.
45. Le Righe interessate restano identificabili come `Rettifica` nella schermata del Budget; le
    Spese Extra Budget non confluiscono nel Budget Approvato e sono mostrate separatamente.
46. La Spesa non possiede gli Stati `Aperta` e `Chiusa`: può ricevere più Effettivi e viene
    consolidata dalla chiusura del Budget annuale.
47. La pagina dei Report offre lo Switch `Mostra Valutazioni`, che sovrappone graficamente la Riga
    Previsionale Corrente senza modificare KPI, totali o calcoli.
48. Il Preventivo corrente prevale sulla Stima nella visualizzazione opzionale; la Cronologia
    conserva entrambe le Righe.
49. Se manca la Nota obbligatoria di una Riga Extra Budget, il Software mostra un Avviso, non salva e
    mantiene la Riga in Modifica senza perdere i dati inseriti.
50. In Reportistica, il Budget Corrente è la vista viva dell'anno e non un totale autonomo ottenuto
    mescolando Previsto, Valutazioni ed Effettivo.
51. Una Fattura o Nota di Credito tardiva viene inserita come una normale Spesa con la data economica
    corretta; se l'anno è chiuso, il Software la classifica automaticamente come `Rettifica`.
52. Per una Rettifica su un anno chiuso, la Nota della Spesa è obbligatoria; in sua assenza il
    Software mostra un Avviso, non salva e mantiene la Spesa in Modifica.
53. La Rettifica modifica l'importo mostrato del medesimo Budget Finale: non vengono creati un
    `Budget Finale Rettificato`, una nuova versione o una Label speciale.
54. Le Note di Credito tardive producono Rettifiche negative e il Budget dell'anno di ricezione non
    le conteggia nuovamente.
55. Il Software calcola le differenze numeriche ma non gestisce, deduce o richiede le cause degli
    scostamenti.
56. Il Confronto Annuale sovrappone due Serie Selezionate mediante Grafici Predefiniti e offre una
    Vista di Dettaglio tabellare nella stessa pagina; la selezione iniziale propone normalmente Anno
    Precedente e Anno Attuale.
57. Il click su un dato del grafico applica i Filtri contestuali, evidenzia la selezione e scorre fino
    alla Vista di Dettaglio; l'Utente può poi modificare o azzerare i Filtri.
58. Grafici e Tabella di Dettaglio usano gli stessi dati e gli stessi calcoli; dalla Tabella è
    disponibile il Drill-Down fino alle singole Spese.
59. Il Software propone automaticamente le serie del Confronto Annuale, ma due Selettori sempre
    disponibili consentono all'Utente di scegliere Anno Economico e Valore per entrambi i lati.
60. I Valori selezionabili sono Budget Proposto, Budget Approvato, Previsto Ricostruito, Effettivo e
    Budget Finale quando disponibili; Budget Corrente e Valutazioni non sono serie principali
    selezionabili.
61. Lo Switch `Mostra Effettivo` controlla l'eventuale sovrapposizione dell'Effettivo alle due serie
    principali e non duplica una serie Effettivo già selezionata.
62. I due Selettori sono indipendenti e possono confrontare Budget dello stesso anno o di anni non
    consecutivi; Grafici e Tabella riportano le etichette reali delle serie selezionate.
63. Per una Spesa non collegata a un Progetto, l'Anno Economico deriva dalla Data della Spesa; per
    una Spesa collegata a un Progetto, deriva invece dall'Anno del Progetto.
64. Il Software conserva separatamente Anno Economico, Data della Spesa e Data di Registrazione,
    senza modificare le Date per farle coincidere con l'Anno del Progetto.
65. Quando la Data della Spesa appartiene a un anno diverso dall'Anno del Progetto, il Software
    mostra prima del salvataggio un messaggio contestuale non bloccante e mantiene la distinzione
    visibile nella Riga, nel Drill-Down e nella Cronologia.
66. I Selettori e i Filtri annuali dei Report usano l'Anno Economico; un eventuale Filtro sulla Data
    della Spesa resta distinto e non modifica l'attribuzione al Budget.
67. Una nuova Spesa che eredita da un Progetto un Anno Economico già chiuso viene classificata come
    Rettifica del relativo Budget Finale anche se la Data della Spesa appartiene a un anno
    successivo; conserva la Data reale e richiede la Nota obbligatoria.
68. Un Progetto senza Effettivi può essere spostato a un altro Anno: il Progetto e le medesime Spese
    acquisiscono il nuovo Anno Economico, mentre Date e Cronologia restano inalterate.
69. Se il Progetto possiede almeno un Effettivo, il Progetto originario e le Spese con Effettivi
    restano nel proprio anno; il Software chiude il Progetto originario e crea una Continuazione
    collegata nell'anno di destinazione.
70. La Continuazione usa un solo riferimento al Progetto Precedente; il Software ricava la relazione
    inversa e l'intero Percorso senza introdurre una Famiglia di Progetti autonoma.
71. Le Spese senza Effettivi possono essere spostate alla Continuazione; le Spese con Effettivi
    restano interamente nel Progetto originario e un eventuale importo da riproporre richiede una
    nuova Spesa nel nuovo Progetto.
72. Prima dello spostamento, una Vista di Impatto riepiloga ciò che resta, ciò che viene spostato, il
    Progetto da chiudere, la Continuazione da creare e le Coperture Plafond incompatibili.
73. La Vista di un Progetto permette di alternare `Intero Percorso` e `Solo Questo Progetto`, filtrare
    per Anno Economico e conoscere il Totale Effettivo dell'intera sequenza senza sommare in modo
    ambiguo i Previsti riproposti nei diversi anni.
74. L'Importo da Riproporre Suggerito è il maggiore tra zero e la differenza fra la Base della
    Riproposta e il Totale Effettivo; la Base usa, nell'ordine, Preventivo Corrente, Stima Corrente e
    Previsto.
75. L'Utente deve confermare o modificare l'Importo da Riproporre prima della creazione della nuova
    Spesa; l'importo scelto alimenta il Budget Proposto di destinazione senza modificare Previsto,
    Effettivi o Scostamento dell'anno originario.
76. La nuova Spesa della Continuazione nasce con una Stima pari all'Importo da Riproporre confermato;
    la Stima contribuisce al Budget Proposto e il relativo valore viene fissato come Previsto soltanto
    con l'approvazione del Budget.
77. Le Spese automatiche dei Contratti appartengono all'Anno Economico della Data di Rinnovo prevista
    dai Termini e non vengono ripartite proporzionalmente tra gli anni coperti dal servizio.
78. Le Righe del Contratto possono definire Importi differenti per ciascuna Data di Rinnovo.
79. Disattivazione e Cessazione Anticipata interrompono i Rinnovi futuri senza cancellare Spese o
    Effettivi già generati.
80. La modifica dell'Importo Effettivo generato dal Contratto permette una Nota facoltativa, resta
    visibile come differenza e non modifica automaticamente la Riga del Contratto o i Rinnovi futuri;
    la Nota è obbligatoria soltanto quando la modifica costituisce una Rettifica di un anno chiuso.
    La Cronologia registra comunque Importo precedente, Importo nuovo, Data e Utente.
81. Per un Rinnovo dell'Anno Corrente o di un anno precedente, il Contratto crea una Riga Effettiva;
    per un Rinnovo futuro crea un Preventivo che alimenta il relativo Budget Proposto.
82. Quando un anno futuro diventa corrente, il Software aggiunge automaticamente l'Effettivo
    contrattuale e conserva il Preventivo nella Cronologia senza trasformarlo o eliminarlo.
83. L'Effettivo contrattuale rappresenta un costo certo attribuito al Budget e non la conferma del
    Pagamento; il Software non gestisce Stati di Pagamento.
84. Se una Spesa di Rinnovo usa un Plafond, il Preventivo alimenta la Copertura Prevista e
    l'Effettivo automatico alimenta il Consumato.
85. Con `Rinnovo Automatico`, il Software genera le Righe mancanti soltanto per gli Anni Economici
    già esistenti, copiando inizialmente la Riga precedente e senza creare Rinnovi futuri illimitati.
86. Una Riga di Rinnovo già esistente non viene duplicata, sovrascritta o aggiornata silenziosamente;
    la Riga più recente disponibile viene usata soltanto come base per una nuova Riga non ancora
    generata.
87. Quando viene aggiunto un nuovo Anno Economico, il Software genera l'eventuale Riga di Rinnovo
    applicabile per ogni Contratto con Rinnovo Automatico ancora attivo.
88. Un Contratto creato con Data di Inizio o Rinnovi in Anni Economici chiusi genera Effettivi
    classificati come Rettifiche dei rispettivi Budget Finali.
89. Prima di salvare un Contratto retrodatato, una Vista di Impatto mostra tutte le Righe, Spese e
    Rettifiche che verranno generate negli anni interessati.
90. Quando una singola operazione genera più Rettifiche contrattuali, l'Utente inserisce una sola
    Nota obbligatoria che viene riportata nelle Note di tutte le Rettifiche generate.
91. Una Spesa Ordinaria senza Effettivi e senza Progetto o Contratto non viene riportata
    automaticamente nell'anno successivo.
92. Il Budget in Lavorazione mostra una Vista derivata `Spese Non Realizzate dell'Anno Precedente`
    senza introdurre un nuovo Stato della Spesa o un Wizard obbligatorio.
93. Dalla Vista, l'Utente può riproporre la Spesa, registrare un Effettivo, eliminarla oppure aprirne
    il dettaglio.
94. Riproporre crea una nuova Spesa collegata nell'anno di destinazione con una Stima confermabile o
    modificabile, senza alterare il Budget dell'anno originario.
95. Registrare un Effettivo su una Spesa di un anno chiuso produce una Rettifica con Nota
    obbligatoria; la Spesa esce quindi dalla Vista delle Spese Non Realizzate.
96. Eliminare una Spesa, anche in presenza di Effettivi, sposta l'intero aggregato nel Cestino e lo
    esclude dal dataset economico corrente senza modificare la fotografia del Budget Approvato.
97. Gli Effettivi di una Spesa eliminata non contribuiscono ai totali, all'Extra Budget o al Consumato
    dei Plafond; le Quote precedentemente consumate vengono liberate.
98. L'eliminazione di una Spesa con Effettivi in un Anno Economico chiuso è una Rettifica con effetto
    opposto al Totale Effettivo escluso e richiede la Nota obbligatoria.
99. I Report escludono normalmente le Spese nel Cestino; il Filtro `Mostra Eliminate` le rende
    consultabili nel Drill-Down senza reinserirle nei calcoli finché non sono Eliminate
    Definitivamente.
100. I generatori automatici conservano il riferimento alla Spesa eliminata e non la ricreano
     automaticamente.
101. Eliminare una Spesa con Effettivi richiede sempre una Nota, anche in un Anno Economico aperto;
     senza Nota il Software non salva e mantiene la Spesa in Modifica.
102. Prima dell'eliminazione di una Spesa con Effettivi, una Vista di Impatto mostra gli Importi
     esclusi, le Quote Plafond liberate e i Budget o Report interessati.
103. Spese senza Effettivi, Progetti Aperti, Contratti che proseguono, Residui o Sforamenti Plafond
     non impediscono la Chiusura del Budget.
104. Il Riepilogo di Chiusura è una Vista assistita non bloccante che spiega ogni situazione e offre
     azioni contestuali senza introdurre Stati annuali o un Wizard obbligatorio.
105. Per una Spesa senza Effettivi, l'Utente può riproporla, registrare un Effettivo, eliminarla o
     visualizzarla; senza intervento resta nell'anno in chiusura con Effettivo zero.
106. Per un Progetto senza Effettivi, l'Utente può spostarlo all'anno successivo, chiuderlo,
     mantenerlo aperto nel proprio anno o visualizzarlo.
107. Per un Progetto con Effettivi, l'Utente può creare una Continuazione nell'anno successivo,
     chiuderlo, mantenerlo aperto nel proprio anno o visualizzarlo.
108. Mantenere Aperto un Progetto nel proprio anno comporta che gli Effettivi registrati dopo la
     Chiusura siano Rettifiche del Budget Finale di tale anno; il Riepilogo mostra esplicitamente
     questa conseguenza.
109. Per un Contratto che prosegue, il Riepilogo consente di visualizzarlo, disattivarlo o cessarlo
     anticipatamente; senza intervento continua ad applicare le normali regole di Rinnovo.
110. Residui e Sforamenti dei Plafond non bloccano la Chiusura e non vengono modificati
     automaticamente.
111. Dopo ogni azione, il Riepilogo aggiorna i totali; la Conferma Finale registra Data e Utente e
     crea il Budget Finale.
112. Un Residuo Plafond positivo può essere riproposto esplicitamente nell'anno successivo, ma non
     viene trasferito automaticamente dalla Chiusura.
113. La Riproposta crea un nuovo Plafond con una Stima iniziale pari al Residuo e modificabile
     dall'Utente; il Plafond originario resta invariato.
114. Il nuovo Plafond conserva un collegamento al Plafond originario, ma Spese e Quote di Copertura
     non vengono trasferite automaticamente.
115. Se il Residuo è zero o negativo, il Riepilogo non propone la Riproposta; resta possibile creare
     manualmente un nuovo Plafond.
116. L'Approvazione riguarda l'intero Budget Proposto con una sola Data e un solo Approvatore, senza
     Stati di Approvazione sulle singole Spese.
117. Prima dell'Approvazione, l'Utente può modificare la composizione automatica aggiungendo,
     escludendo o ripristinando Spese, Contratti e Progetti.
118. L'esclusione di un Contratto o di un Progetto dal Budget Annuale opera economicamente sulle
     relative Spese e non introduce totali paralleli per le entità di contesto.
119. Le Esclusioni Annuali persistono e impediscono alla ricomposizione automatica di reinserire
     silenziosamente gli elementi esclusi.
120. Il Riepilogo di Approvazione distingue elementi automatici, aggiunte manuali ed esclusioni e
     mostra il relativo effetto sul Totale Proposto.
121. La conferma dell'Approvazione fissa come Previsti tutti i Valori Proposti inclusi e crea una sola
     fotografia del Budget Approvato.
122. Dopo l'Approvazione, un'Omissione viene gestita tramite Rettifica e una nuova esigenza tramite
     Extra Budget, senza approvazioni parziali o riscrittura della fotografia.
123. Escludere un Contratto dal Budget disattiva contestualmente il Rinnovo Automatico, senza
     classificare necessariamente il Contratto come Disattivato o Cessato.
124. Dopo l'Esclusione, il Contratto non genera nuove Righe di Rinnovo o nuove Spese; non può quindi
     produrre automaticamente Effettivi Extra Budget.
125. Escludere un Progetto dal Budget non lo Chiude automaticamente e non ne modifica l'Anno del
     Progetto; Chiusura e Continuazione restano azioni separate.
126. Il Riepilogo di Approvazione mostra l'effetto dell'Esclusione sul Rinnovo Automatico del
     Contratto e avverte che gli Effettivi manuali di un Progetto escluso saranno Extra Budget.
127. Quando un Contratto viene escluso, tutte le Righe di Rinnovo e le Spese automatiche senza
     Effettivi dall'Anno Economico escluso in avanti vengono escluse logicamente.
128. Effettivi, Righe storiche, Budget precedenti e Cronologia del Contratto restano invariati; le
     Righe future escluse non entrano nei calcoli e non diventano Effettivi all'ingresso dell'anno.
129. Prima della conferma, una Vista di Impatto mostra tutte le Righe e le Spese interessate
     dall'Esclusione.
130. La successiva riattivazione del Rinnovo Automatico non ripristina le Righe escluse: genera i
     Rinnovi mancanti dalla Data di Riattivazione usando i Termini correnti del Contratto.
131. Un Anno Storico creato durante l'avvio di un nuovo Tenant nasce modificabile anche se precedente
     all'anno corrente e non classifica le registrazioni iniziali come Rettifiche.
132. La Chiusura di un Anno Storico è esplicita e usa il normale Riepilogo di Chiusura; soltanto dopo
     tale operazione le modifiche economiche successive diventano Rettifiche.
133. Il Software consiglia di ricostruire e chiudere gli Anni Storici in ordine cronologico, senza
     introdurre Stati di completezza o bloccare automaticamente gli anni successivi.
134. Se per un Anno Storico non esiste un Budget Approvato, gli Effettivi non Extra Budget formano il
     Previsto Ricostruito e gli Effettivi Extra Budget restano esclusi dalla base.
135. Il Previsto Ricostruito non viene presentato come Budget Approvato e non possiede Data di
     Approvazione o Approvatore.
136. Il Riepilogo di Chiusura richiede la conferma della base ricostruita e la Reportistica ne mostra
     sempre l'origine `Ricostruito dagli Effettivi`.
137. Il confronto storico può usare Previsto Ricostruito, Effettivo e Budget Finale, ma non attribuisce
     alla base ricostruita la capacità di misurare variazioni rispetto a importi originari non noti.
138. In un Anno Storico con Previsto Ricostruito, la classificazione Extra Budget può essere modificata
     anche dopo la Chiusura senza Rettifica e senza Nota obbligatoria.
139. La Riclassificazione non modifica Effettivo o Budget Finale, ma ricalcola Previsto Ricostruito,
     Extra Budget e Differenza.
140. La Cronologia registra automaticamente classificazione precedente, classificazione nuova, Data
     e Utente.
141. L'eccezione riguarda soltanto il Previsto Ricostruito e non si applica alle nuove Spese Extra
     Budget del Flusso ordinario o ai Budget realmente approvati.
142. Non è previsto un Flusso per sostituire successivamente il Previsto Ricostruito con un Budget
     originario recuperato o per mantenere entrambe le basi.
143. Una Spesa Ordinaria appartiene a un solo Anno Economico e non può contenere Effettivi attribuiti
     ad anni differenti.
144. La parte attribuita a un anno successivo richiede una nuova Spesa collegata alla precedente,
     con una Stima suggerita e modificabile.
145. La nuova Spesa entra nel Budget Proposto se l'anno è in preparazione, è Extra Budget se il
     Budget è Approvato ed è una Rettifica se l'Anno Economico è Chiuso.
146. La Copertura Plafond non viene trasferita tra anni; deve essere ricreata usando Plafond
     compatibili con il nuovo Anno Economico.
147. Le Spese collegate mostrano Totale Effettivo complessivo e scomposizione annuale senza una
     Famiglia di Spese autonoma.
148. Un Effettivo con Anno differente non viene salvato sulla Spesa originaria; il Software propone
     la creazione assistita di una nuova Spesa collegata.
149. Il Flusso assistito conserva Importo, Data e Descrizione, mostra la collocazione economica della
     nuova Spesa e richiede una nuova Copertura Plafond quando necessaria.
150. La nuova Spesa viene creata soltanto con la conferma finale; l'annullamento non lascia Spese o
     Righe parziali.
151. Il Centro di Costo può essere modificato anche dopo la Chiusura senza Rettifica e senza Nota,
     perché costituisce una Riclassificazione analitica a totale invariato.
152. I Report correnti applicano il nuovo Centro in modo coerente a Previsto ed Effettivi, mentre la
     Cronologia conserva Centro precedente, Centro nuovo, Data e Utente.
153. Una Copertura Plafond incompatibile blocca il salvataggio finché l'Utente non seleziona Plafond
     del nuovo Centro o rimuove la Copertura.
154. La nuova configurazione aggiorna Consumato e Residuo e resta tracciata, senza produrre una
     Rettifica del Totale Effettivo annuale.
155. Centro di Costo, Fornitore, Descrizione e Note Generali sono modificabili dopo la Chiusura senza
     Rettifica e senza Nota obbligatoria, con registrazione automatica nella Cronologia.
156. I Report correnti applicano Centro di Costo e Fornitore aggiornati in modo coerente a Previsto
     ed Effettivi, senza modificare la fotografia monetaria.
157. Anno Economico, appartenenza a Progetto o Contratto, Natura, Importi, Righe, Copertura Plafond ed
     Extra Budget non sono Riclassificazioni libere e seguono i rispettivi Flussi di Dominio.
158. Il Cestino è unico per Tenant, Multi-Entità e Multi-Anno; non viene duplicato per Modulo o Anno
     Economico.
159. Il Cestino raccoglie tutte le Entità di Dominio soggette a Eliminazione recuperabile e conserva
     per ciascuna l'intero aggregato necessario al Ripristino, senza contribuire ai calcoli economici.
160. Ripristinare dal Cestino riattiva la stessa Identità e l'intero aggregato; non crea una nuova
     Entità e non permette il Ripristino parziale dei suoi elementi dipendenti.
161. Il Ripristino dal Cestino ricalcola il Budget Corrente se l'Anno è aperto; se l'Anno è Chiuso,
     produce una Rettifica opposta all'Eliminazione e richiede una Nota.
162. L'Eliminazione Definitiva rimuove il contenuto recuperabile e impedisce qualsiasi recupero
     tramite Cestino o Cronologia delle Versioni, senza cambiare Totali o Rettifiche già consolidati.
163. Cestino, Cronologia delle Versioni e Rettifiche restano meccanismi separati rispettivamente per
     recupero dell'entità, consultazione degli stati e coerenza economica storica.
164. La Cronologia delle Versioni presenta gli Snapshot in un Pannello laterale e riusa la normale
     Vista della Spesa in sola lettura, evidenziando nella loro posizione i Campi e le Righe che
     differiscono dalla Versione Corrente.
165. La selezione confronta un solo Snapshot con la Versione Corrente; non viene introdotto un
     confronto arbitrario tra due Snapshot.
166. Ripristinare una Versione applica atomicamente Spesa e Righe, rivalida le regole correnti e crea
     un nuovo Snapshot senza riscrivere la Cronologia precedente.
167. Gli Allegati non vengono duplicati dentro ogni Snapshot e un Ripristino di Versione non ricrea
     file precedentemente eliminati; gli Allegati correnti restano invece recuperabili finché la
     Spesa permane nel Cestino.
168. Lo Svuotamento Automatico del Cestino e la Retention della Cronologia delle Versioni sono due
     Manutenzioni distinte, entrambe idempotenti e gestite dallo Scheduler.
169. Ogni elemento viene Eliminato Definitivamente dopo 12 mesi dalla propria Data di Eliminazione,
     indipendentemente dal suo Anno Economico e senza uno Svuotamento annuale comune.
170. La Vista unica del Cestino permette Ricerca e Filtri per Tipo di Entità, Anno Economico, Data di
     Eliminazione e Utente; le Entità prive di Anno mostrano `—`.
171. Il dettaglio di un elemento nel Cestino riusa in sola lettura la Vista specifica della relativa
     Entità, evitando una rappresentazione generica e complessa.
172. Se una Dipendenza obbligatoria è ancora nel Cestino, il Ripristino dell'elemento dipendente viene
     bloccato e l'Utente riceve l'elenco delle Dipendenze da Ripristinare prima.
173. Il Software non Ripristina automaticamente le Dipendenze e non esegue Ripristini ricorsivi;
     ciascuna Entità richiede una decisione esplicita e produce la propria Cronologia.
174. Se una Dipendenza è stata Eliminata Definitivamente, il Ripristino resta bloccato finché non può
     essere selezionata una nuova Dipendenza valida secondo le regole dell'Entità.
175. L'Eliminazione non si propaga mai automaticamente alle Entità dipendenti e non lascia elementi
     operativi collegati a un'Entità nel Cestino.
176. La presenza di Riferimenti Attivi blocca l'Eliminazione; il Software elenca gli elementi da
     riassegnare, chiudere o gestire esplicitamente prima di riprovare.
177. Se restano soltanto Riferimenti Storici, l'Eliminazione è consentita e le Viste storiche
     conservano esclusivamente la Denominazione minima necessaria alla comprensione del dato.
178. La Denominazione storica non è selezionabile, non permette modifiche retroattive e rimane
     leggibile anche dopo l'Eliminazione Definitiva dell'Entità originaria.
179. Il numero di Snapshot disponibili nella Cronologia delle Versioni è configurabile nelle
     Impostazioni e ha come valore predefinito `10 Versioni` per ogni Entità.
180. Il limite conta gli Snapshot logici dell'intero aggregato e non le singole Righe tecniche
     generate dalla medesima modifica.
181. Gli Snapshot eccedenti vengono rimossi dalla disponibilità operativa tramite la Manutenzione
     schedulata; la Retention delle Versioni resta distinta dai 12 mesi del Cestino.
182. Il limite delle Versioni è un'Impostazione unica del Tenant, comune a tutti gli Utenti e Tipi di
     Entità e modificabile soltanto dagli Utenti autorizzati.
183. Il Confronto Annuale usa inizialmente quattro Grafici: Confronto per Dimensione, Differenza per
     Dimensione, Progressione Mensile Cumulativa ed Extra Budget e Rettifiche.
184. Confronto e Differenza condividono un solo Selettore di Dimensione con Centro di Costo,
     Progetto, Contratto, Fornitore e Spesa; non vengono duplicati Grafici per ogni Dimensione.
185. I Grafici per Dimensione mostrano inizialmente i dieci elementi di maggiore impatto, mentre la
     Tabella permette di consultare l'insieme completo.
186. La Progressione Mensile usa soltanto dati realmente attribuibili a un Mese e non distribuisce
     artificialmente Totali annuali privi di granularità.
187. Extra Budget e Rettifiche sono rappresentati separatamente in un Grafico condizionale mostrato
     soltanto quando sono presenti.
188. Il Donut del Proposto, il Donut degli Stati della Spesa e il RadialBar di Utilizzo non fanno
     parte della configurazione iniziale del Confronto Annuale.
189. Una Riga di KPI mostra i Totali delle due Serie, Differenza Assoluta e Percentuale ed eventuali
     Effettivo sovrapposto, Extra Budget e Rettifiche senza introdurre nuove misure economiche.
190. Non viene aggiunto un Grafico dei Totali separato, perché replicherebbe i valori già presentati
     dai KPI senza offrire un nuovo livello di lettura.
191. Ogni Tenant usa una sola Base Economica autorevole, `Netto` o `Lordo`, per Budget, Previsto,
     Effettivo, Scostamenti, Plafond, Rettifiche, Extra Budget, KPI, Grafici, Snapshot, Export e
     Confronti Annuali; il valore predefinito è `Netto`.
192. Ogni Riga conserva Netto, IVA e Lordo; le Impostazioni controllano soltanto la visibilità delle
     relative colonne informative e mostrano il Netto per impostazione predefinita.
193. Cambiare le colonne visibili non modifica dati o calcoli, non genera Rettifiche o Snapshot e può
     avvenire anche dopo Approvazione o Chiusura.
194. Netto, IVA e Lordo devono essere sempre etichettati; anche quando la colonna corrispondente alla
     Base ufficiale è nascosta, le Viste sintetiche dichiarano se i Totali sono espressi al Netto o
     al Lordo.
195. La visibilità di Netto, IVA e Lordo è una Preferenza personale per Utente, con Netto visibile per
     impostazione predefinita e senza effetti sulla Vista degli altri Utenti.
196. La Preferenza personale si applica coerentemente alle superfici economiche dello stesso Utente,
     fermo restando che ogni schermata mostra soltanto le colonne pertinenti al proprio dettaglio.
197. Preparazione, Approvazione e Chiusura dipendono dalle Autorizzazioni e possono essere eseguite
     dallo stesso Utente; non è richiesta una separazione obbligatoria dei compiti.
198. L'Approvazione può essere annullata soltanto in assenza di Effettivi, Rettifiche, Spese Extra
     Budget, Chiusura o altri eventi che abbiano usato il Previsto approvato.
199. L'annullamento consentito riporta il Budget in Proposta, disattiva i Previsti approvati e
     conserva fotografia, Data, Approvatore e operazione nella Cronologia.
200. Una successiva Approvazione crea una nuova fotografia; quella annullata non viene modificata,
     eliminata o trasformata in Rettifica.
201. Il Budget Finale può essere riaperto soltanto finché non esistono Rettifiche successive alla
     Chiusura; la presenza anche di una sola Rettifica blocca l'operazione.
202. La Riapertura non cancella o ricalcola i dati economici, disattiva soltanto la fotografia finale
     corrente e riporta l'Anno alla situazione precedente alla Chiusura.
203. Fotografia, Data, Utente e Riepilogo della Chiusura riaperta restano nella Cronologia; una nuova
     Chiusura crea una nuova fotografia senza sovrascrivere la precedente.
204. Un Progetto può conservare una **Fase del Progetto** scelta tra `Idea`, `Proposto`, `Approvato`,
     `Rinviato` e `Rifiutato` come informazione organizzativa modificabile manualmente.
205. La **Fase del Progetto** è economicamente inerte: non modifica Anno Economico, appartenenza al
     Budget, Budget Proposto, Previsto, Effettivo, Data di Chiusura o importi da riproporre.
206. `Rinviato` non attiva promozioni temporali automatiche. Spostamento senza Effettivi e
     Continuazione con Effettivi restano azioni esplicite con la rispettiva Vista di Impatto.
207. Lo Scadenziario Contratti mantiene Date di Inizio, Rinnovo, eventuale Preavviso e Cessazione e
     collega ogni scadenza alla Riga del Contratto e alla Spesa generata.
208. Lo Scadenziario è una Vista operativa temporale distinta dal Budget: non crea una seconda fonte
     economica, non rappresenta i Pagamenti e non determina da solo la ripartizione dell'Importo tra
     Anni Economici.
209. La Base Economica può essere configurata per Tenant tra Netto e Lordo, usa Netto come default e
     viene bloccata definitivamente dalla prima Approvazione, anche se questa viene annullata.
210. Il Motore Economico è unico e riceve la Base del Tenant come parametro; Budget, Plafond,
     Dashboard, Report, Snapshot ed Export non implementano formule alternative.
211. Un Contratto Mensile genera una sola Spesa Contrattuale per Anno con una Riga distinta per ogni
     Scadenza Mensile appartenente all'Anno; ciascuna Riga conserva Data, origine e collegamento allo
     Scadenziario.
212. Le Righe mensili concorrono al totale dell'Anno della propria Data. La loro aggregazione in una
     sola Spesa annuale riduce il rumore nel Registro senza perdere granularità economica o temporale.

## Questioni Aperte da Raffinare

- La necessità di una Nota per Annullamento dell'Approvazione e Riapertura della Chiusura può essere
  valutata in una passata successiva insieme alle Autorizzazioni puntuali.
