# Research: Audit Codice e Allineamento al Dominio Raffinato

**Data**: 2026-08-12  
**Stato**: Audit completato; tutte le decisioni bloccanti D01–D05 sono risolte  
**Baseline letta**: codice e test presenti nel worktree corrente, incluse le modifiche locali già esistenti

## Executive Summary

L'applicazione corrente copre già una parte importante dell'infrastruttura necessaria, ma il dominio raffinato non può essere ottenuto con un semplice restyling. Esistono cinque conflitti economici bloccanti:

1. la Base Economica Netto/Lordo resta configurabile, ma deve essere bloccata dalla prima Approvazione e consumata da un unico Motore Economico;
2. la riconciliazione economica è duplicata in più Query e nel motore economico;
3. la Copertura Plafond è un singolo riferimento senza Quote e viene trattata come Consumato anche per Stime e Preventivi;
4. il Budget Approvato è aggiornato mutando `approved_amount` tramite Variazioni, anziché conservare una fotografia immutabile e Rettifiche esplicite;
5. l'Anno Economico è scelto sulla Spesa e la Data viene forzata nello stesso anno, mentre il dominio richiede derivazione dalla Data o dall'Anno del Progetto.

Questi punti devono essere corretti prima di considerare affidabili Dashboard e Report, perché tutte le superfici di lettura dipendono dai medesimi totali.

## Baseline Positiva da Conservare

| Area | Implementazione utile già presente | Indicazione |
|---|---|---|
| Denaro | `Money`, `MoneyCalculator`, `VatCalculator`, BCMath e `DECIMAL(19,2)` | Conservare e ampliare i casi limite; nessun float autorevole. |
| Transazioni | Le mutazioni principali sono Actions esplicite e transazionali | Riutilizzare lo stesso modello per Rettifiche, Continuazioni, Coperture e Ripristini. |
| Concorrenza | `lock_version`, `lockForUpdate` e rollback testati | Estendere alle nuove entità economiche e alle azioni multi-aggregato. |
| Tenant | Middleware, Policy e Query Tenant-bound | Mantenere fail-closed in ogni nuova API, Cestino e Report. |
| Righe di Spesa | Stima, Preventivo, Effettivo, più Righe e Riga Previsionale Corrente | La struttura di base è coerente; vanno corretti terminologia, date, Note Extra e Coperture. |
| Allegati | Allegati su Spesa e Riga di Spesa | Coerente con il target; va cambiata la conservazione durante il Cestino. |
| Revisioni | Snapshot aggregati, confronto, restore atomico, audit e retention schedulata | Base valida da estendere, senza riscrivere il plugin. |
| Scheduler | Laravel Scheduler già configura promozione Progetti e retention Revisioni | Riutilizzare l'infrastruttura; rimuovere le automazioni in conflitto e aggiungere Cestino/heartbeat UI. |
| Registro Spese | Ricerca, filtri, selezione pagina, azioni massive, colonne e righe espandibili | Usare come baseline UX, correggendo Stati, colonne e modifica inline. |
| Frontend Test | 103 test Vitest correnti passano | Buona rete di regressione UI, da riallineare ai nuovi termini e flussi. |

## Gap Matrix

### G01 — Unico Motore Economico

**Severità**: CRITICAL  
**Tipo**: Contraddizione costituzionale e rischio di conti divergenti

**Evidenza corrente**:

- `app/Domain/Economics/Services/EconomicEngine.php` calcola riepiloghi, Plafond e distribuzione;
- `app/Domain/Budget/Queries/AnnualBudgetQuery.php` possiede una seconda `reconcilePlafond()`;
- `app/Domain/Budget/Queries/HistoricalAnnualBudgetQuery.php` ricostruisce nuovamente totali e Plafond;
- `app/Domain/Reporting/Queries/AnnualEconomicReportQuery.php` riconcilia ancora i gruppi.

**Differenza dal dominio**: Budget, Report e Dashboard devono essere proiezioni dello stesso dataset autorevole, non implementazioni indipendenti delle formule.

**Lavoro necessario**:

- definire una sola proiezione economica annuale nella Base ufficiale del Tenant decisa in D04;
- far consumare a Budget, Report, Dashboard e Report Plafond i risultati della stessa proiezione;
- mantenere nelle Query soltanto selezione, autorizzazione, raggruppamento e paginazione;
- introdurre test di riconciliazione al centesimo tra riepilogo, gruppi e dettaglio.

### G02 — Base Economica Ufficiale del Tenant

**Severità**: CRITICAL  
**Tipo**: Decisione di Prodotto e vincoli economici incompleti

**Evidenza corrente**:

- `Tenant.budget_basis` accetta `net` o `gross`;
- `EconomicEngine` e le Query annuali scelgono la Base dal Tenant;
- le Impostazioni permettono di cambiare la “Base Budget ufficiale” fino alla prima approvazione;
- le colonne predefinite del Registro Spese mostrano Netto, IVA e Lordo insieme.

**Differenza dal dominio**: il dominio aveva stabilito il Netto come Base ufficiale; la decisione è stata riaperta per valutare il mantenimento della configurazione Netto/Lordo. La preferenza di visualizzazione Netto/IVA/Lordo resta distinta dalla Base ufficiale.

**Lavoro necessario**:

- applicare la decisione D04 senza introdurre due Motori Economici: l'eventuale Base configurabile è un parametro della stessa proiezione autorevole;
- mantenere sempre Netto, IVA e Lordo sulle Righe;
- introdurre una preferenza personale trasversale per la visibilità degli Importi;
- dichiarare esplicitamente la Base ufficiale nelle viste sintetiche;
- se la Base resta configurabile, usare Netto come default e bloccare il cambio dalla prima Approvazione del Tenant;
- non prevedere conversioni storiche: il prodotto è Greenfield e il database può essere ricostruito.

### G03 — Copertura Plafond e Quote Multiple

**Severità**: CRITICAL  
**Tipo**: Modello dati e formule incompatibili

**Evidenza corrente**:

- ogni Riga possiede un solo `funded_plafond_expense_id`;
- non esiste l'importo della Quota di Copertura;
- `is_extra` e Plafond sono mutuamente esclusivi nel database e nel validator;
- il validator non impone stesso Centro di Costo né Copertura Integrale;
- il motore incrementa `plafondConsumed` per ogni Riga collegata, comprese le Valutazioni presenti nel dataset;
- lo Sforamento è rilevato a posteriori senza Vista di Impatto o Nota obbligatoria.

**Differenza dal dominio**: una Riga può avere più Quote; Stima/Preventivo producono Copertura Prevista, solo gli Effettivi producono Consumato; Extra Budget e Copertura sono indipendenti; Copertura incompleta blocca, Capienza insufficiente avvisa e richiede motivazione.

**Lavoro necessario**:

- sostituire il singolo riferimento con Quote di Copertura per Riga e Plafond, con Importo Netto esatto e ordine;
- vincolare Tenant, Anno Economico e Centro di Costo compatibili;
- validare somma Quote = Importo Netto della Riga quando la Copertura è presente;
- consentire Extra Budget coperto;
- calcolare separatamente Impegnato, Copertura Prevista, Consumato, Residuo e Sforamento;
- implementare proposta di adeguamento Quote dalla Valutazione all'Effettivo;
- richiedere motivazione di Sforamento e aggiungerla alle Note Generali nel formato deciso;
- aggiungere un Report Plafond dedicato;
- coprire integralmente formule e doppio conteggio con tabelle decisionali.

### G04 — Previsto, Approvazione Immutabile e Rettifiche

**Severità**: CRITICAL  
**Tipo**: Lifecycle economico incompatibile

**Evidenza corrente**:

- `ApplyBudgetApproval` accetta un sottoinsieme di Spese;
- la prima chiamata è `initial`, le successive sono `variation`;
- ogni chiamata sovrascrive `expenses.approved_amount`;
- `approved_current` rappresenta il valore mutabile risultante;
- non esiste una Rettifica canonica distinta dall'Extra Budget;
- non esistono Annullamento dell'Approvazione o Riapertura del Budget Finale.

**Differenza dal dominio**: l'Approvazione è una fotografia dell'intero Budget Proposto; il Previsto originario resta stabile; omissioni e correzioni sono Rettifiche; il valore mostrato può incorporarle senza sovrascrivere la fotografia.

**Lavoro necessario**:

- rendere atomica l'Approvazione dell'intero Budget Proposto;
- conservare Snapshot e Voci approvate immutabili;
- impedire che nuove Stime/Preventivi modifichino il Previsto;
- introdurre un unico registro di Rettifiche con Nota, autore, data, elemento ed effetto Netto;
- distinguere Rettifica per Omissione da Extra Budget;
- implementare Annullamento con Vista di Impatto e blocchi per eventi dipendenti;
- mantenere compatibilità di lettura della fotografia annullata nella Cronologia;
- eliminare dalla UI i concetti `Variazione` e `Approvato corrente` non coerenti.

### G05 — Budget Finale e Chiusura Assistita

**Severità**: CRITICAL  
**Tipo**: Funzionalità centrale parziale

**Evidenza corrente**:

- `CloseAnnualBudget` modifica soltanto `planning_years.budget_state`;
- non conserva un Riepilogo di Chiusura economico;
- non classifica automaticamente le modifiche tardive;
- l'UI offre un pulsante diretto, non una pagina **Chiusura**;
- non esiste la pagina **Rettifiche**.

**Differenza dal dominio**: la Chiusura crea una fotografia del Budget Finale dopo una Vista assistita non bloccante; ogni modifica economica successiva è una Rettifica con Nota; la Riapertura è possibile soltanto prima della prima Rettifica.

**Lavoro necessario**:

- creare la pagina e l'API di anteprima Chiusura;
- elencare Spese senza Effettivi, Progetti, Contratti e Plafond con azioni contestuali;
- consentire la chiusura anche con situazioni residue;
- conservare fotografia, Data, Utente e riepilogo;
- classificare automaticamente create/update/delete/restore economici successivi come Rettifiche;
- consentire Riapertura solo senza Rettifiche successive;
- mostrare Budget Finale originario e Rettifiche senza creare un `Budget Finale Rettificato`.

### G06 — Anno Economico e Data della Spesa

**Severità**: CRITICAL  
**Tipo**: Validazione in contraddizione

**Evidenza corrente**:

- `ExpenseAggregateValidator::dateShape()` richiede che ogni Data Effettiva appartenga al `planning_year_id` della Spesa;
- l'Anno viene selezionato nell'intestazione della Spesa;
- non esiste una Data di Registrazione di dominio separata dai timestamp tecnici;
- il Progetto non possiede un proprio Anno.

**Differenza dal dominio**: per Spese ordinarie l'Anno deriva dalla Data; per Spese di Progetto deriva dall'Anno del Progetto; la Data reale può essere di un altro anno; un Effettivo ordinario di altro anno propone una nuova Spesa collegata.

**Lavoro necessario**:

- rendere l'Anno Economico derivato e spiegato, non sempre selezionabile;
- supportare Data reale fuori anno soltanto per Spese di Progetto;
- bloccare un Effettivo ordinario fuori anno e offrire la creazione assistita della continuazione;
- applicare Rettifica in base allo stato dell'Anno Economico, non all'anno della Data;
- mostrare insieme Anno di Competenza, Data della Spesa e Data di Registrazione;
- aggiornare Date Picker, API validation e test dei confini temporali.

### G07 — Stati della Spesa da Rimuovere

**Severità**: HIGH  
**Tipo**: Contraddizione di dominio diffusa

**Evidenza corrente**:

- esistono `ExpenseState::Open/Closed`, `ExpenseClosureOutcome`, `CloseExpense` e riapertura automatica;
- Budget, Report, Dashboard, filtri e grafici contano Spese Aperte/Chiuse;
- sono coinvolti almeno 33 file tra applicazione, Frontend e test.

**Differenza dal dominio**: la Spesa non possiede Stati Aperta/Chiusa; può ricevere più Effettivi; la Chiusura appartiene al Budget.

**Lavoro necessario**:

- rimuovere Stato, Esito e azione di Chiusura dalla Spesa;
- sostituire `Non Sostenuta`, `Annullata`, `Spostata` con azioni di dominio esplicite: lasciare senza Effettivi, eliminare, riproporre o collegare una continuazione;
- rimuovere filtri, KPI, Donut e badge basati sullo Stato;
- eliminare i campi e i comportamenti legacy durante la ricostruzione Greenfield dello schema;
- conservare gli eventi storici soltanto quando necessari alla leggibilità della migrazione.

### G08 — Extra Budget e Note di Riga

**Severità**: HIGH  
**Tipo**: Vincoli mancanti

**Evidenza corrente**:

- `is_extra` è sulla Riga, ma la Riga non ha un campo Note;
- la UI spiega soltanto che Extra mantiene la Riga separata dal Plafond;
- nessuna validazione richiede una motivazione;
- Extra viene contato anche prima di distinguere correttamente Valutazioni ed Effettivi.

**Differenza dal dominio**: Extra Budget è indipendente dal tipo Riga e dalla Copertura; la Nota di Riga è obbligatoria nel flusso ordinario; non crea Previsto.

**Lavoro necessario**:

- aggiungere Note alla Riga e renderle obbligatorie quando `Extra Budget` è selezionato;
- mantenere la classificazione durante il passaggio Stima → Preventivo → Effettivo;
- separare nei Report Valutazione Extra ed Effettivo Extra senza sommarli impropriamente;
- applicare l'eccezione senza Nota soltanto alle riclassificazioni di Anni con Previsto Ricostruito.

### G09 — Progetti Pluriennali e Continuazioni

**Severità**: CRITICAL  
**Tipo**: Modello fondamentale assente

**Evidenza corrente**:

- `projects` non possiede `planning_year_id`, Data di Chiusura o riferimento al Progetto precedente;
- possiede invece `stage` e `deferred_target_planning_year_id`;
- lo Scheduler promuove automaticamente Progetti Rinviati in base all'anno corrente;
- non esiste una Vista di Impatto per spostamento o continuazione.

**Differenza dal dominio**: il Progetto appartiene a un Anno; non viene spostato dal tempo; senza Effettivi può cambiare Anno; con Effettivi crea una Continuazione collegata e suggerisce gli importi da riproporre.

**Lavoro necessario**:

- introdurre Anno del Progetto, Data di Chiusura e `previous_project_id`;
- rimuovere la promozione automatica incompatibile;
- ereditare l'Anno Economico sulle Spese;
- implementare anteprima atomica per spostamento senza Effettivi;
- implementare chiusura origine, Continuazione e riproposta per Progetti con Effettivi;
- mostrare **Solo Questo Progetto** e **Intero Percorso** con Effettivi riconciliati;
- mantenere `stage` come **Fase del Progetto** descrittiva e manuale, economicamente inerte;
- usare `closed_at` come dimensione separata per il ciclo Aperto/Chiuso;
- rimuovere la promozione automatica di `Rinviato`: lo spostamento o la Continuazione restano azioni esplicite.

### G10 — Contratti e Rinnovi

**Severità**: CRITICAL  
**Tipo**: Generazione parzialmente incompatibile

**Evidenza corrente**:

- ogni occorrenza generata è sempre `Quote`;
- `ExpectedContractOccurrenceQuery` aggrega date mensili/annuali in un bucket annuale con data `01-01`;
- `auto_renew` è salvato sui Termini ma non crea le Righe mancanti per tutti gli Anni esistenti;
- `active` non rappresenta una Cessazione con Data Effettiva;
- un Contratto retrodatato non genera Rettifica con Nota e Vista di Impatto.

**Differenza dal dominio**: anno corrente o precedente genera Effettivo; anno futuro genera Preventivo; Rinnovo Automatico copia la Riga precedente per gli Anni esistenti; cessazione interrompe solo il futuro.

**Lavoro necessario**:

- riallineare il modello delle Righe di Rinnovo e le Date reali;
- generare Effettivo per anni correnti/passati e Preventivo per futuri;
- all'ingresso nell'Anno creare l'Effettivo mantenendo il Preventivo;
- creare Righe mancanti quando nasce un nuovo Anno o si attiva il Rinnovo Automatico;
- introdurre Disattivazione/Cessazione con Data e impatto sui soli rinnovi futuri;
- consentire modifica manuale dell'Effettivo con Nota facoltativa, obbligatoria su Anno chiuso;
- implementare anteprima e Nota unica per Contratti retrodatati;
- risolvere il Ciclo Mensile tramite D03.

### G11 — Composizione Automatica ed Esclusioni Annuali

**Severità**: HIGH  
**Tipo**: Funzionalità assente

**Evidenza corrente**: non esiste una persistenza delle Esclusioni Annuali; l'Approvazione opera su Voci selezionate ma non mostra inclusioni automatiche, override ed effetti futuri.

**Lavoro necessario**:

- derivare il Budget in Lavorazione da Spese, Progetti e Contratti applicabili;
- memorizzare Esclusioni Annuali idempotenti;
- permettere override di inclusione/rimozione prima dell'Approvazione;
- disattivare Rinnovo Automatico quando si esclude un Contratto, con Vista di Impatto;
- lasciare Aperto il Progetto escluso e spiegare gli Effettivi futuri Extra Budget;
- aggiungere la Vista derivata **Spese Non Realizzate dell'Anno Precedente**.

### G12 — Previsto Ricostruito

**Severità**: HIGH  
**Tipo**: Funzionalità storica assente

**Evidenza corrente**: gli Anni passati possono essere creati, ma non esiste una base `Ricostruita dagli Effettivi`; la Chiusura presuppone la stessa struttura di ogni altro Budget.

**Lavoro necessario**:

- consentire la scelta esplicita della base ricostruita quando manca un Budget originario;
- calcolare Previsto Ricostruito dagli Effettivi non Extra;
- non creare approvatore o Data di Approvazione fittizi;
- consentire riclassificazione Extra post-chiusura senza Rettifica in questo solo caso;
- rendere origine e limiti visibili in Report e confronto.

### G13 — Cestino Unico e Conservazione Allegati

**Severità**: HIGH  
**Tipo**: Recuperabilità incompleta e comportamento in conflitto

**Evidenza corrente**:

- diverse Entità usano Soft Delete, ma non esiste una Vista Cestino unificata;
- `DeleteExpense`, `DeleteContract` e `DeleteProject` eliminano immediatamente gli Allegati;
- non esistono restore aggregati dal Cestino né purge dopo 12 mesi;
- Fornitori e Centri di Costo bloccano ogni riferimento, senza distinguere attivo da storico.

**Differenza dal dominio**: il Cestino conserva aggregato e Allegati per 12 mesi; ripristino stessa identità; dipendenze bloccanti esplicite; eliminazione definitiva separata.

**Lavoro necessario**:

- mantenere file e metadati finché l'aggregato resta nel Cestino;
- implementare Query unificata Multi-Entità/Multi-Anno e viste specifiche in sola lettura;
- implementare restore completo con controllo dipendenze;
- produrre Rettifica per delete/restore economico su Anno chiuso;
- distinguere riferimenti attivi e storici, conservando denominazioni minime;
- aggiungere purge idempotente a 12 mesi e test di non eliminazione anticipata.

### G14 — Revisioni Configurabili e Vista Documento

**Severità**: MEDIUM  
**Tipo**: Base solida, UX e configurazione parziali

**Evidenza corrente**:

- il limite è costante `OperationalRevisionQuery::LIMIT = 10`;
- la UI mostra lista + modale con sole differenze;
- non esiste slider temporale né documento completo evidenziato;
- restore aggregato e nuova Revisione sono già disponibili per varie Entità.

**Lavoro necessario**:

- spostare il limite nelle Impostazioni Tenant, default 10;
- applicare la retention per Tenant e aggregato;
- riusare la Vista documento in sola lettura con campi e righe evidenziati;
- aggiungere selettore temporale e ritorno alla Versione Corrente;
- integrare gli impatti di Rettifica quando il restore modifica un Anno chiuso;
- mantenere la regola: gli Allegati eliminati non sono ricreati da uno Snapshot.

### G15 — Report, Dashboard e Confronto Annuale

**Severità**: HIGH  
**Tipo**: Funzionalità presente ma semanticamente precedente

**Evidenza corrente**:

- il Report confronta Proposto/Approvato/Actual di un solo Anno;
- filtra e rappresenta Spese Aperte/Chiuse;
- include il Donut degli Stati della Spesa, esplicitamente escluso dal target;
- non consente confronto libero tra due Budget/anni;
- il click sui grafici non porta al dettaglio filtrato;
- manca Report Plafond, Progressione Cumulativa ed evidenza Rettifiche/Extra come decise.

**Lavoro necessario**:

- costruire selettori indipendenti delle due Serie e suggerire corrente/precedente;
- implementare Confronto, Differenza, Progressione Mensile ed Extra/Rettifiche condizionale;
- usare un selettore Dimensione condiviso;
- limitare il grafico ai primi dieci impatti mantenendo la tabella completa;
- fare scroll al dettaglio e applicare filtri dal click;
- introdurre Switch di sovrapposizione Preventivato/Stimato senza alterare i totali principali;
- rimuovere Stati Spesa e ogni termine `Actual` visibile;
- ricostruire Dashboard e KPI solo dopo il consolidamento del dataset.

### G16 — Workspace, Tabelle, Guida e Anno Globale

**Severità**: HIGH  
**Tipo**: Delta UX ampio

**Evidenza corrente**:

- `AppLayout` riserva 90/290 px a una Sidebar permanente;
- Anno Globale è in memoria, senza gestione centralizzata delle modifiche non salvate;
- le pagine dettaglio non applicano in modo uniforme il redirect al registro quando cambia Anno;
- soltanto il Registro Spese possiede l'insieme più completo di filtri, bulk e preferenze colonne;
- non esiste una modalità Guida globale;
- il Date Picker supporta min/max, ma le regole di dominio non sono applicate sistematicamente;
- la pagina Spese non possiede ancora Hero/KPI e non offre modifica inline;
- Budget non ha sottopagine Chiusura/Rettifiche.

**Lavoro necessario**:

- sostituire la Sidebar con Barra Superiore responsiva;
- centralizzare cambio Anno/Tenant, dirty guard e redirect contestuale;
- applicare pattern di registro a tutte le liste pertinenti senza un mega-componente;
- aggiungere preferenze personali per vista e Importi;
- introdurre Guida Contestuale persistente;
- collegare Date Picker a vincoli server e messaggi accessibili;
- verticalizzare Hero, launcher, espansione e modifica inline sulle singole aree;
- strutturare Budget e Report secondo la nuova Information Architecture.

### G17 — Tenant e Scheduler Self-Hosted

**Severità**: MEDIUM  
**Tipo**: Parzialmente implementato

**Evidenza corrente**:

- Gestione Tenant di Piattaforma e Impostazioni Tenant sono già separate e autorizzate;
- Scheduler Laravel contiene task reali;
- manca UI del comando Cron e stato/heartbeat;
- manca configurazione Tenant del limite Revisioni;
- l'impostazione della Base Economica non possiede ancora tutti i vincoli proposti in D04.

**Lavoro necessario**:

- mantenere l'attuale separazione Piattaforma/Tenant;
- rimuovere la Base Lordo come scelta economica;
- aggiungere limite Revisioni e altre sole impostazioni effettivamente Tenant-wide;
- mostrare comando Cron copiabile;
- aggiungere heartbeat soltanto se ottenibile senza accesso diretto al sistema operativo;
- mostrare `Stato non verificabile` in assenza di evidenza.

### G18 — Contratti API, Terminologia e Ricostruzione Greenfield

**Severità**: CRITICAL  
**Tipo**: Rischio di coerenza tra Backend e Frontend

**Evidenza corrente**:

- almeno 41 file applicativi espongono o visualizzano `actual`;
- enum, payload e test espongono `open/closed`, `approved_current`, `variation` e `budget_basis`;
- lo schema persistente contiene significati non coerenti con il nuovo dominio, ma non esistono dati reali da preservare.

**Lavoro necessario**:

- mantenere eventualmente identificatori tecnici legacy soltanto dietro mapping esplicito, mai nella UI;
- versionare o migrare i contratti API in modo atomico tra backend e Frontend;
- non fare sostituzioni lessicali cieche: `actual` nei nomi tecnici dei test o delle variabili non è sempre testo di Prodotto;
- aggiornare Backend e Frontend in modo atomico per ogni verticale;
- provare la ricostruzione completa da database vuoto con Migrazioni, Seeder e Factory coerenti;
- non introdurre livelli di compatibilità o code di riconciliazione per dati legacy inesistenti.

## Decisioni di Prodotto

### D01 — Dati Reali da Preservare

**Stato**: RISOLTA — Il prodotto è Greenfield e non esistono dati reali da preservare.

Lo schema corrente contiene significati non convertibili automaticamente senza perdita:

- Budget approvati in Base Lordo;
- `approved_amount` modificati da Variazioni;
- Spese Chiuse con esiti differenti;
- Progetti senza Anno proprio;
- Righe Extra prive di Nota;
- collegamenti a un solo Plafond senza Quota esplicita.

**Decisione**: è consentito eliminare e ricostruire il database, senza backup e senza compatibilità con i significati legacy. Le nuove Migrazioni devono funzionare da database vuoto; Seeder, Factory e test devono produrre soltanto dati validi per il dominio target. La libertà Greenfield non riduce i test: elimina la matrice di migrazione legacy e rende più semplice imporre vincoli corretti direttamente nello schema.

### D02 — Quali Stati Restano sul Progetto?

**Stato**: RISOLTA — Gli Stati correnti restano, purché non abbiano effetti economici o automatici.

**Problema**: il codice usa `Idea`, `Proposto`, `Approvato`, `Rinviato`, `Rifiutato`; il dominio raffinato parla soprattutto di Anno del Progetto, Progetto Aperto, Data di Chiusura, Esclusione Annuale e Continuazione.

**Decisione**: separare le dimensioni:

- ciclo del Progetto: Aperto / Chiuso tramite `closed_at`;
- collocazione nel Budget: derivata da inclusione, Esclusione Annuale, Approvazione e Continuazione.
- **Fase del Progetto**: `Idea`, `Proposto`, `Approvato`, `Rinviato` o `Rifiutato`, modificata manualmente e usata come informazione organizzativa.

La **Fase del Progetto** non modifica Anno, Budget Proposto, Previsto, Effettivo o Chiusura. `Rinviato` non viene promosso automaticamente dal Cron; spostamento e Continuazione sono azioni economiche esplicite. In UI il nome completo e la Guida devono evitare di confondere `Proposto/Approvato` con lo stato del Budget.

### D03 — Il Ciclo Mensile dei Contratti Resta nel Prodotto?

**Stato**: RISOLTA — Resta come frequenza di Scadenze raggruppate in una Spesa annuale.

**Problema**: il codice supporta `monthly` e `annual`, mentre il dominio raffinato descrive Righe per Data di Rinnovo e nessuna ripartizione tra anni.

**Decisione**: per ogni Contratto e Anno viene generata una sola Spesa Contrattuale Annuale con una Riga distinta per ogni Scadenza Mensile. Le Righe conservano la Data reale, compaiono nello Scadenziario e alimentano la Progressione Mensile; la Spesa non usa una Data fittizia per sostituire la granularità. In questo modo una Cessazione o una variazione può intervenire sulle sole mensilità interessate senza creare dodici record nel Registro Spese.

### D04 — La Base Economica Resta Configurabile?

**Stato**: RISOLTA — Configurabile per Tenant tra Netto e Lordo.

**Problema**: una Base Netto/Lordo configurabile può essere mantenuta senza duplicare il Motore Economico, ma non è una semplice preferenza grafica. Cambia Previsto, Effettivo, Plafond, Scostamenti, Snapshot e Report.

**Decisione**: mantenere la scelta per Tenant con questi vincoli:

- Netto come valore predefinito;
- una sola Base ufficiale per Tenant;
- Base bloccata definitivamente dalla prima Approvazione di un Budget;
- un solo Motore Economico parametrizzato dalla Base, mai formule duplicate;
- Base mostrata chiaramente in Budget, Report, export e Snapshot;
- preferenza personale di visibilità Netto/IVA/Lordo separata dalla Base ufficiale.

Questa soluzione preserva la flessibilità per realtà che ragionano al Lordo senza rendere ambiguo lo storico.

### D05 — Il Rinnovo Annuale Deve Essere Ripartito tra Anni?

**Stato**: RISOLTA — L'intero Importo appartiene all'Anno della Data di Rinnovo.

**Problema**: un Rinnovo annuale del 13 febbraio 2025 può coprire il servizio fino al 12 febbraio 2026. Attribuirlo interamente al 2025 segue la logica dell'impegno assunto alla Data di Rinnovo; ripartirlo tra 2025 e 2026 segue invece una logica di competenza temporale simile a Ratei e Risconti.

**Decisione**: mantenere l'intero Importo nell'Anno della Data di Rinnovo. Il Software sta governando Budget e impegni, non contabilità per competenza o Date di Pagamento. Lo Scadenziario conserva comunque le Date reali e rende visibile che il periodo di servizio attraversa due anni.

La ripartizione richiederebbe regole aggiuntive per giorni o mesi, anno bisestile, arrotondamenti, rinnovi anticipati, cessazioni, rimborsi, variazioni di prezzo, IVA e riconciliazione con l'Effettivo. Inoltre, per un Contratto già a regime con un Rinnovo ogni febbraio, ciascun Anno riceve comunque un Rinnovo completo: la comparabilità annuale non migliora abbastanza da giustificare tale complessità.

## Strategia di Test Economico

### Gate di Ambiente

- La suite deve girare nel container applicativo collegato al servizio MySQL del progetto; il runtime host non è un ambiente supportato per i test d'integrazione.
- Il comando verificato `docker compose exec -T laravel.test php artisan test --testsuite=Accounting` ha completato 34 test e 235 asserzioni con esito positivo.
- L'errore `could not find driver` ottenuto sul runtime host è quindi un errore di invocazione del test, non una regressione e non un Gate aperto.
- `npm test` nel Frontend ha completato 33 file e 103 test con esito positivo, incluso il controllo dei token Dark.
- L'immagine Docker corrente installa `pdo_mysql`, ma non installa Xdebug o PCOV; `XDEBUG_MODE` è configurato in Compose ma non può attivare un'estensione assente.
- Il comando di CI e documentazione deve rendere esplicito il runtime Docker. L'immagine di test deve installare Xdebug e attivare `coverage`; Xdebug è preferito a PCOV perché il Gate richiede anche branch/path coverage.

### Copertura Obbligatoria del Nucleo Economico

La prima verticale deve introdurre un comando ripetibile che fallisce sotto il 100% di line e branch per:

- Money e arrotondamenti;
- calcolo Netto/IVA/Lordo;
- scelta della Riga Previsionale Corrente;
- Quote Plafond, Copertura Integrale e Capienza;
- Impegnato, Copertura Prevista, Consumato, Residuo e Sforamento;
- assenza di Doppio Conteggio;
- Previsto, Effettivo, Extra Budget e Rettifica;
- formula dell'Importo da Riproporre;
- delta economici di delete/restore e modifica post-chiusura.

### Matrice Minima dei Casi Numerici

| Area | Casi obbligatori |
|---|---|
| Decimali | 0, centesimo, massimi ammessi, IVA inclusa/esclusa, arrotondamento a 2 decimali, negativi ammessi solo dove previsto |
| Valutazioni | sola Stima, solo Preventivo, entrambi con selezione corrente, cambio della Riga corrente, nessuna Valutazione |
| Effettivi | uno, più Effettivi, inferiore/uguale/superiore al Previsto, Nota di Credito, Riga eliminata e ripristinata |
| Plafond | uno, due o più, Quote esatte, Copertura incompleta, Sforamento motivato, Stima→Preventivo→Effettivo, Extra coperto |
| Budget | preparazione, Approvazione completa, annullamento consentito/bloccato, Rettifica omissione, Extra, Chiusura, Rettifica tardiva, riapertura consentita/bloccata |
| Anno | Data nello stesso anno, ordinaria fuori anno bloccata, Progetto fuori anno consentito, Anno storico aperto, Anno chiuso |
| Progetti | spostamento senza Effettivi, Continuazione con Effettivi, importo suggerito zero/positivo, Copertura incompatibile |
| Contratti | anno passato/corrente/futuro, rinnovo automatico, cessazione, override importo, contratto retrodatato |
| Riconciliazione | totale Budget = somma Drill-Down; ogni raggruppamento riconcilia; coperto non è contato due volte; eliminati sempre esclusi |

### Livelli di Test

1. **Unit**: formule pure e tabelle decisionali, senza database.
2. **Accounting Integration**: dataset MySQL reale, vincoli e riconciliazione di tutti i consumatori.
3. **Feature/Action**: transazioni, rollback, lock, Nota obbligatoria e side effect atomici.
4. **API Contract**: payload, codici errore, Tenant isolation e assenza di campi legacy visibili.
5. **Frontend Component**: mantenimento input su errore, avvisi, viste di impatto, grafico→dettaglio, Guida e dirty guard.
6. **Fresh Schema**: ricostruzione da database vuoto, vincoli target, Seeder e Factory validi; non sono richieste fixture o conversioni legacy.

## Sequenza di Lavoro Proposta

Questa è la lista ordinata di adattamento. Non è ancora `tasks.md`: ogni punto deve diventare un proprio Spec Kit verticale.

1. Standardizzare l'esecuzione Docker della suite Accounting già verde e introdurre il Gate di coverage.
2. Definire e testare il dataset economico unico nella Base ufficiale decisa in D04.
3. Verticale **Spesa + Plafond**: Quote multiple, Note, date, Extra Budget e rimozione Stati Spesa.
4. Verticale **Budget Annuale**: fotografia completa, Previsto immutabile, Rettifiche, Chiusura e Riapertura.
5. Verticale **Progetti**: Anno, competenza, spostamento e Continuazioni.
6. Verticale **Contratti**: Righe di Rinnovo, generazione Effettivo/Preventivo, Rinnovo Automatico e cessazione.
7. Verticale **Composizione Annuale**: Esclusioni, override e Spese Non Realizzate.
8. Verticale **Storico**: Previsto Ricostruito e riclassificazione Extra.
9. Verticale **Cestino**: conservazione aggregati/Allegati, restore e purge a 12 mesi.
10. Verticale **Revisioni**: limite Tenant e Vista documento completa.
11. Verticale **Report**: confronto libero, grafici decisi, drill-down e Report Plafond.
12. Verticale **Dashboard e Shell**: Barra Superiore, Hero, launcher, Guida, Anno Globale e registri coerenti.
13. Verticale **Self-Hosting**: comando Cron, heartbeat opzionale e stato non verificabile.
14. Eseguire Converge dopo ogni verticale implementata, non prima.

## Alternative Scartate

### Adeguare Prima Tutta la UI

Scartata perché renderebbe più leggibili totali ancora semanticamente errati e produrrebbe doppio lavoro quando cambieranno payload e stati.

### Correggere Separatamente Ogni Report

Scartata perché perpetuerebbe più implementazioni delle formule. Report e Dashboard devono consumare una proiezione autorevole comune.

### Mantenere `Variation` come Sinonimo Tecnico di Rettifica

Scartata nella forma corrente perché `Variation` sovrascrive direttamente `approved_amount` e può essere applicata a un sottoinsieme senza distinguere omissione, effetto economico e fotografia originaria.

### Aggiungere Più Campi al Singolo Collegamento Plafond

Scartata perché non può rappresentare due Plafond sulla stessa Riga. Una relazione con Quote esplicite è il minimo modello coerente.

### Usare Soltanto il 100% di Coverage come Garanzia

Scartata perché la copertura misura esecuzione, non correttezza delle formule. Sono necessari anche esempi canonici, invarianti e riconciliazione end-to-end.

## Conclusion

L'applicazione è tecnicamente predisposta a una migrazione incrementale, ma non è economicamente allineata al dominio raffinato. Il primo intervento non deve essere la Dashboard o la Barra Superiore: deve essere la verticale **Spesa + Plafond**, perché rende autorevoli i numeri sui quali dipendono Budget, Report e Dashboard.

Le decisioni bloccanti emerse dall'audit sono risolte. Il Plan può procedere con Modello Dati, Contratti API, Quickstart e scomposizione in Slice Verticali.
