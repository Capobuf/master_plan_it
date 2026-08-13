# Regole di dominio implementate

Stato: `VERIFIED CURRENT` dopo l'implementazione della Slice 024.

Questo documento descrive soltanto regole che devono restare dopo la rimozione degli Spec Kit
storici. Le funzionalità non implementate sono descritte esclusivamente negli Spec Kit attivi.

## Tenant e accesso

- `Administrator` è globale e protetto; entra ed esce esplicitamente dal contesto Tenant senza
  impersonare un tenant user.
- I tenant user appartengono a un solo Tenant e possono ricevere più ruoli tenant-scoped.
- `Editor` e `Viewer` sono template iniziali; il comportamento applicativo dipende da abilities,
  non dal nome del ruolo.
- Un Tenant è `Active` o `Inactive`; non è prevista cancellazione permanente del Tenant.
- Un Tenant inattivo blocca i tenant user. Administrator può mantenerne l'accesso autorizzato e
  riattivarlo.
- La disattivazione di un user conserva authorship e storico.
- Le impostazioni generali operano sempre sul Tenant corrente e comprendono nome, fuso orario,
  IVA predefinita, Base Budget e obbligo della motivazione di cancellazione; la valuta è visibile
  ma non modificabile da questa superficie.
- Il registro globale crea il Tenant con nome, codice, valuta, lingua, fuso orario e IVA come
  valori bootstrap. Dopo la creazione può modificare soltanto codice, valuta e lingua: nome
  operativo, fuso orario, IVA predefinita, Base Budget e obbligo della motivazione appartengono
  esclusivamente alle impostazioni generali del Tenant corrente.
- Lettura e modifica delle impostazioni generali usano abilities tenant-scoped distinte. Il Tenant
  non è selezionabile dal payload e l'update è transazionale, versionato e auditato con i soli nomi
  dei campi modificati.
- La Base Economica ufficiale è `Net` o `Gross`. Può cambiare prima della prima approvazione senza
  riscrivere i valori Net, IVA e Gross già persistiti; la prima approvazione la blocca in modo
  atomico e ne conserva il timestamp.

## Anni di pianificazione

- L'identità è l'anno solare del Tenant.
- Inizio e fine sono derivati: 1 gennaio e 31 dicembre.
- Le date non sono modificabili.
- Sono ammessi create, deactivate e reactivate secondo permission.
- Lo stesso record è il Budget annuale e attraversa `preparation`, `approved` e `closed`.
- La chiusura richiede `planning-year.update`; non esiste riapertura formale implicita.
- Un anno disattivato resta leggibile nello storico ed è escluso dalle nuove selezioni.
- Non è prevista cancellazione permanente.
- Non è prevista revision restore per gli anni di pianificazione.

## Fornitori

- Sono tenant-owned.
- Un fornitore inattivo resta visibile nei dati storici, non è selezionabile per nuovi dati e può
  essere riattivato.
- La cancellazione è ammessa solo in assenza di riferimenti correnti o storici.
- Revision history e restore sono operazioni autorizzate e tenant-scoped.

## Centri di costo

- Sono tenant-owned e gerarchici.
- Profondità massima: tre livelli, contando la radice come livello 1.
- La gerarchia non può contenere cicli.
- I fratelli sono ordinati case-insensitive per nome e poi per ID.
- Un parent con discendenti attivi non può essere disattivato implicitamente.
- Un centro inattivo resta leggibile nello storico e non è selezionabile per nuovi dati.
- La cancellazione richiede assenza di riferimenti correnti/storici e assenza di discendenti.
- Revision history e restore sono tenant-scoped.

## Spese

- Expense kind: `Ordinary` o `Plafond`.
- Estimate, Quote e Actual sono tipi indipendenti: non esiste progressione obbligatoria.
- Se esiste almeno una Estimate o Quote, esattamente una deve essere selezionata come
  Pianificazione Corrente; le alternative restano visibili ma non contribuiscono ai totali
  correnti. Una Spesa con soli Actual è valida senza selezione.
- Actual è effettivo immediatamente, richiede una Data Effettiva indipendente dall'Anno Economico
  della Spesa e può essere positivo, negativo o zero. Non esiste un passaggio di conferma.
- Estimate e Quote non possono avere importo netto negativo; Actual può essere negativo.
- Ogni riga usa alternativamente un importo diretto oppure quantità e prezzo unitario; il backend
  valida decimali esatti, calcola Net/IVA/Gross e arrotonda alla scala monetaria. La pianificazione
  può omettere la data. Le nuove scritture non supportano periodi o distribuzioni.
- Extra e finanziamento tramite Plafond sono mutuamente esclusivi.
- Un Plafond referenziato deve appartenere allo stesso Tenant e anno; il cost center può differire.
- Solo Expense row correnti e non eliminate entrano nella proiezione corrente: una Pianificazione
  Corrente per Spesa e la somma di tutti gli Actual.
- I valori economici autorevoli sono calcolati dal backend e mantengono Net, IVA, Gross e valore
  ufficiale secondo la Base Economica del Tenant.
- Gli update concorrenti usano optimistic locking e ogni mutazione economica serializza lo stesso
  Tenant/Anno prima di modificare aggregate e righe.
- Una Expense può riferirsi a Project, Contract o entrambi come relazioni tenant-scoped
  indipendenti; il Project proprio del Contract non forza quello della Spesa.
- L'Importo approvato è nullable: `null` significa non approvato, `0.00` approvato a zero. Solo
  l'Action di approvazione può modificarlo.
- La Spesa non possiede un lifecycle `open|closed`: non esistono stato, esito di chiusura,
  Close/Reopen o Move. Il lifecycle annuale appartiene esclusivamente al Budget.
- Una nota di credito in un anno successivo è una nuova Spesa collegata all'origine e contiene
  soltanto Actual negativi.

## Progetti

- I Project sono tenant-owned e classificano le Expense senza introdurre importi, budget o totali
  persistiti propri.
- Gli stage ammessi sono `Idea`, `Proposed`, `Approved`, `Deferred` e `Rejected`.
- `Deferred` richiede un Planning Year attivo dello stesso Tenant; al raggiungimento dell'anno nel
  timezone Tenant la promozione a `Proposed` è idempotente, versionata e auditata.
- Create, update, delete e restore revision usano optimistic locking.
- History, compare e restore sono disponibili soltanto sul record corrente e secondo ability.
- La cancellazione è terminale, richiede assenza di Expense correnti collegate, non effettua detach
  o cascade e non può essere annullata da revision restore.

## Plafond

- Il Plafond è una Spesa gestionale dedicata, creata soltanto dalle route `/api/v1/plafonds`; le
  route generiche delle Spese accettano esclusivamente Spese `Ordinary`.
- Esiste al massimo un Plafond corrente per Tenant, Anno Economico e Centro di Costo. Il vincolo
  usa uno slot live generato, quindi una root soft-deleted non occupa lo slot.
- L'Allocazione è la somma firmata di Righe `allocation_adjustment` non nulle. Ogni variazione
  conserva data, autore server-side e componenti Net/IVA/Gross; il Plafond non è mai un Effettivo.
- Ogni Riga ordinaria può essere coperta integralmente da zero o un Plafond dello stesso Tenant e
  Anno. Il Centro di Costo della Riga può differire; copertura Plafond ed Extra Budget sono
  mutuamente esclusivi. Non esistono percentuali, quote multiple o coperture parziali.
- Il Motore Economico espone separatamente `Allocazione`, `Copertura Prevista`, `Consumato` e
  `Disponibile`. `Disponibile = Allocazione - Consumato`; soltanto gli Actual coperti, incluso il
  loro segno, alimentano Consumato. Estimate e Quote coperte possono superare il Disponibile e
  restano informative.
- Un Actual coperto o una riduzione dell'Allocazione che porterebbe Consumato oltre Allocazione è
  rifiutato atomicamente con `PLAFOND_INSUFFICIENT`. Preview e conferma non riservano capienza; la
  conferma rivalida sotto la guardia Tenant/Anno e non lascia righe, revisioni o audit parziali.
- Le mutazioni di Plafond e copertura sono ammesse soltanto con Budget in Preparazione. Un Plafond
  referenziato non è eliminabile; il restore di una revisione su root live rivalida unicità, anno,
  XOR e capienza. Il restore di una root Plafond eliminata appartiene al futuro Cestino.
- Documento, Registro, Budget e Report Plafond consumano la proiezione annuale completa prima dei
  filtri e riconciliano al centesimo anche con righe coperte di Centri differenti. Allocazione entra
  una volta nella pianificazione annuale; la pianificazione coperta non viene sommata di nuovo.

## Revisioni operative

- Una revisione rappresenta uno stato business realmente raggiunto. Un salvataggio invariato o una
  modifica di solo bookkeeping non crea batch; una modifica alle Note è business e la crea.
- Expense e tutte le ExpenseRow formano un aggregate revisionale. Contract e tutti i ContractTerm
  ne formano un altro. La batch conserva lo stato completo dell'aggregate e marca separatamente gli
  elementi realmente cambiati. Ogni mutazione aggregate riuscita conta una sola revisione logica.
- Project, Vendor e Cost Center mantengono le rispettive capability usando la stessa identità
  `RevisionBatch` e lo stesso limite operativo.
- Lo Storico espone al massimo le dieci revisioni logiche più recenti della root. Una revisione
  espulsa non è elencabile, confrontabile o ripristinabile neppure tramite URL diretto.
- Compare è soltanto revisione selezionata contro stato corrente e restituisce label business. Il
  restore è transazionale, rivalida l'aggregate, non applica il lock storico e produce una nuova
  revisione logica.
- Il restore Contract non riattiva term terminalmente eliminati e non modifica Expense generate,
  source key, suppression o generation history. Il restore di una root terminalmente eliminata è
  vietato.
- Le mutazioni automatiche che cambiano business state sono revisionate con actor visuale
  `Sistema`; i job idempotenti senza cambiamento non producono history.

## Allegati

- Soltanto Expense, ExpenseRow, Contract e Project supportano allegati; ogni allegato appartiene a
  un solo Tenant, parent e autore di upload.
- Sono ammessi PDF, JPEG/JPG, PNG, CSV, XLSX e DOCX non vuoti fino a 10 MiB. Estensione, MIME
  rilevato e contenuto devono essere coerenti e il filename non può contenere segmenti pericolosi.
- La quota del Tenant conta ogni copia fisicamente corrente per la propria dimensione; zero è un
  limite valido e ridurre la quota sotto l'uso non elimina dati esistenti.
- Gli allegati sono stato corrente indipendente dalle revisioni operative: upload e delete non
  consumano revisioni e il restore di Expense, Contract o Project non modifica il set dei file.
- Il delete di un allegato è definitivo. Il delete di una ExpenseRow ne elimina gli allegati; il
  delete terminale di Expense, Contract o Project elimina anche tutti i relativi payload senza
  conservarne copie per restore.
- Lista, upload, download e delete richiedono Tenant, parent, relazione e ability correnti; nessun
  payload compare in JSON, audit, revisioni, log applicativi o URL pubblici permanenti.

## Contratti e generazione

- I Contract sono tenant-owned e non costituiscono una sorgente economica aggiuntiva.
- I Contract term non possono sovrapporsi.
- I cicli supportati sono Monthly e Annual.
- Un Contract può appartenere a un Project dello stesso Tenant.
- Per ogni anno interessato, i term mensili o annuali sono aggregati in una sola Spesa annuale con
  Quote inizialmente non selezionata e source key stabile; la generazione non crea Actual.
- La sincronizzazione può modificare solo una Quote ancora system-managed e mai selezionata o
  modificata manualmente. In caso contrario conserva il valore corrente ed espone la differenza
  attesa.
- Un cambio Project del Contract influenza soltanto le future Spese generate.
- La cancellazione di una Expense generata richiede una scelta esplicita:
  consentire la futura rigenerazione oppure sopprimere quella occurrence.
- Una suppression è stato di controllo non economico e non entra nei totali.
- Sono supportati resume, resume-and-generate e generazione di una singola occurrence mancante per
  un anno valido, senza duplicare la source key.
- La cancellazione di Contract o Contract term è terminale per quella identità logica:
  non può essere annullata da restore, import o sincronizzazione.
- La cancellazione di Contract/term non elimina le Expense generate: le conserva, mantiene la
  source key, le rende user-authoritative e conserva la provenienza di cancellazione.
- La generazione futura da una sorgente terminalmente eliminata si arresta.

## Budget e Report correnti

- Il Budget corrente è un calcolo rolling per Tenant e anno, non un totale corrente duplicato e
  sincronizzato in una seconda fonte.
- Il dataset corrente deriva dalle Expense row correnti e non eliminate.
- Contract e Project non vengono sommati separatamente.
- Revisioni, audit, record eliminati, generation exception, Scenario e BudgetVersion non entrano
  implicitamente nei valori correnti.
- Net, VAT e Gross restano esatti; il Tenant può usare Net o Gross come base ufficiale, con Net come
  default approvato.
- L'IVA predefinita del Tenant si applica soltanto a nuove Expense row e nuovi Contract term che
  omettono l'aliquota. Un valore esplicito, incluso zero, prevale sempre; dati e revisioni esistenti
  non vengono riscritti. Se un update omette l'IVA di un figlio esistente, resta valida l'aliquota
  persistita.
- Gli editor mostrano l'IVA Tenant sui nuovi elementi come valore ereditato, senza trasformarla in
  override nel payload. Il backend risolve quindi il valore corrente al salvataggio; elementi
  esistenti e override espliciti continuano a mostrare e conservare la propria aliquota.
- Generazione e sincronizzazione da un Contract usano i valori IVA persistiti nel termine sorgente,
  non un default Tenant modificato successivamente.
- Il Plafond non deve produrre doppio conteggio della parte già coperta dall'allocazione.
- Documento Spesa, Registro, Budget, Report e Dashboard consumano la stessa proiezione annuale
  canonica. Ogni superficie espone separatamente Pianificazione Corrente e Actual, con componenti
  Net/IVA/Gross e valore ufficiale nella Base Economica del Tenant.
- Stati e chiusure di Project o Contract non riclassificano automaticamente pianificazione,
  approvato o Actual.
- Le approvazioni sono batch atomici multi-Spesa con data effettiva e timestamp di registrazione;
  conservano delta e snapshot delle dimensioni. La base ufficiale Tenant non cambia dopo la prima
  approvazione.
- Il Report raggruppa per Centro di costo, Project, Contract, Fornitore o Spesa e i totali restano
  identici al centesimo al riepilogo, inclusa la riconciliazione Plafond.
- Un output economico non può combinare più Tenant.

## Proiezione storica annuale

- La storia annuale viene attivata con un baseline esplicito e un cutoff precedente restituisce
  `HISTORY_BEFORE_ACTIVATION`.
- Il cutoff è interpretato nel timezone Tenant; una data senza ora usa la fine della giornata
  locale e viene normalizzata in UTC.
- Le mutazioni multi-record sono lette tramite revision batch completi; i delete sono tombstone.
- Gli snapshot annuali sono persistiti negli item della batch e restano leggibili anche quando una
  `Version` package ridondante è stata eliminata fisicamente dalla manutenzione.
- Budget e Report storici sono tenant/year-scoped, read-only, preservano label e relazioni al
  cutoff e non costituiscono restore implicito.
- La proiezione usa un numero costante di query rispetto al numero di Spese nel benchmark.

## Lingua dell'interfaccia

Il launch corrente usa copy e route utente in italiano, con le sole eccezioni indicate in
`docs/ARCHITECTURE.md`. Il contesto Tenant/Anno è visibile nella shell superiore; non esiste una
sidebar permanente e il cambio di contesto protegge le modifiche non salvate.
