# Revisione del dominio Budget

Stato: `APPROVED PRODUCT CONTRACT — /speckit.plan REQUIRED`  
Ambito: Budget annuale, voci economiche, progetti, contratti, Plafond, versioni nominate e confronti  
Autorità: Constitution 3.0.1; Q-034–Q-040; PD-BUD-001; PD-GEN-001

## 1. Obiettivo

Rappresentare il metodo operativo vCIO senza creare domini paralleli per Budget iniziale, autorizzato, forecast e consuntivo.

Il Budget corrente è una vista economica rolling per tenant e anno. Non è una seconda copia delle spese e non possiede un totale corrente indipendente da sincronizzare.

## 2. Sorgente corrente

Il Budget corrente deriva esclusivamente da Expense e Expense row correnti e non eliminate, secondo Constitution C-03.

Non entrano implicitamente nel corrente:

- operational revisions;
- audit events;
- record eliminati o tombstone;
- generation exceptions;
- scenari;
- righe di `BudgetVersion`;
- importi autonomi di progetti e contratti.

Contratti e progetti forniscono contesto e generazione, non un secondo importo da sommare.

## 3. Tipi economici indipendenti — Q-037

`Estimate`, `Quote` e `Actual` sono tipi indipendenti. Non esiste un workflow obbligatorio Estimate → Quote → Actual.

Più righe dello stesso tipo possono coesistere quando descrivono costi distinti. La sostituzione o correzione di uno stesso costo avviene sulla stessa identità logica attraverso revisioni operative, non mostrando copie `Replaced` o `Cancelled` nel registro corrente.

Ogni riga conserva importi Netto, IVA e Lordo con calcolo decimal-safe.

## 4. Actual e generazione contrattuale — Q-035

Una ricorrenza contrattuale genera un Actual `Da confermare` con source key stabile e tenant-scoped.

La voce ha due modalità operative:

- **system-managed:** non è stata modificata manualmente né confermata; la sincronizzazione può aggiornare esclusivamente i campi derivati dal contratto;
- **user-authoritative:** è stata modificata manualmente o confermata; la sincronizzazione non la sovrascrive.

La conferma interrompe la sincronizzazione automatica ma non rende l'Actual immutabile. Un attore autorizzato può ancora correggere, versionare, ripristinare o eliminare il record mediante le normali Actions e invarianti della Feature 003.

La pagina e i dataset mantengono distinta la componente Actual Confermata da quella Da confermare. Entrambe restano attribuite all'anno; i report possono mostrarle separatamente senza duplicarle.

Delete, regeneration choice, suppression, resume e manual generation per un anno sono governati da PD-GEN-001 e Constitution C-13.

## 5. Un Budget rolling per tenant e anno — Q-036

Per ogni tenant e anno esiste un solo contesto Budget corrente calcolato. Non si creano documenti Budget concorrenti per rappresentare le modifiche durante l'anno.

Gli stati deliberati vengono conservati mediante `BudgetVersion` nominate e immutabili:

- `Manual`;
- `Approved`;
- `Snapshot`.

Una versione appartiene a un tenant e anno, conserva dataset, righe, totali, dimensioni disponibili, filtri dichiarati, base monetaria, autore, data e checksum. Una versione pubblicata non viene modificata; una correzione produce una nuova versione.

Il tenant può selezionare una versione di riferimento per i confronti. La selezione non sostituisce né modifica il corrente.

## 6. Anni storici — Q-034

Gli anni precedenti sono ricostruiti dai dati economici disponibili.

Quando è noto soltanto un importo storico o un dettaglio parziale, può essere creata una `BudgetVersion` Manuale:

- total-only;
- partial;
- full.

Le dimensioni mancanti sono dichiarate non disponibili. Non vengono valorizzate a zero né inventate.

In assenza di evidenza, una ricostruzione non viene qualificata come Budget approvato.

## 7. Base economica ufficiale — Q-038

Ogni tenant sceglie la base ufficiale del Budget:

- `Net`, predefinita;
- `Gross`.

La scelta determina il valore principale presentato e confrontato, ma ogni record corrente e ogni `BudgetVersion` conserva sempre Netto, IVA e Lordo. Il cambio di impostazione non modifica i valori storici delle versioni pubblicate.

## 8. Progetti e bucket — Q-040

Il progetto è contesto decisionale e non possiede un totale economico autonomo.

Il dataset distingue:

- **primary:** Estimate/Quote senza progetto o collegati a progetto `Approved`, più tutti gli Actual attribuiti all'anno;
- **proposed:** Estimate/Quote di progetti `Proposed`;
- **idea:** Estimate/Quote di progetti `Idea`;
- **excluded:** Estimate/Quote di progetti `Deferred` o `Rejected`, visibili ma non sommati nel primary;
- **potential:** primary + proposed + idea, indicatore non ufficiale.

Gli Actual attribuiti all'anno restano nel `primary` anche se il progetto viene successivamente rinviato o rifiutato. La modifica dello stato progetto non cancella un costo già sostenuto.

## 9. Plafond

Per ogni Plafond:

- `allocated` = capacità assegnata;
- `consumed` = Actual correnti finanziati dal Plafond;
- `residual` = max(allocated − consumed, 0);
- `overrun` = max(consumed − allocated, 0);
- contributo al `primary` = allocated + overrun.

La quota di consumo coperta dall'allocazione non viene sommata una seconda volta.

## 10. Confronti

Sono richiesti:

- current versus `BudgetVersion`;
- `BudgetVersion` versus `BudgetVersion`;
- confronto cross-year quando le dimensioni sono compatibili.

Il risultato è read-only e identifica:

- lato A e lato B;
- differenza assoluta;
- differenza percentuale solo quando il denominatore è definito;
- righe aggiunte, rimosse e modificate;
- bucket e componenti separati;
- dimensioni non disponibili.

Il confronto non modifica né persiste dati economici correnti.

## 11. Lingua — Q-039 superseded

La precedente proposta di prodotto esclusivamente italiano è `SUPERSEDED` dalla decisione Q-013: il tenant possiede una lingua configurata e gli output tenant-facing la rispettano. L'application shell mantiene il branding Master Plan IT.

La pianificazione tecnica deve usare le capacità native Laravel/Filament e non introdurre un framework i18n aggiuntivo senza necessità.

## 12. Controlli di generazione — Q-041

Q-041 è chiusa dal contratto PD-GEN-001 e Constitution C-13:

- history e link delle ricorrenze dal contratto;
- delete con scelta sulla rigenerazione;
- generation exception non economica;
- resume;
- resume and generate now;
- generate one valid missing year;
- source-key uniqueness e no silent overwrite.

## 13. Kernel economico condiviso

Budget corrente, dashboard tenant, report, print ed export devono consumare un solo dataset economico server-side.

La direzione tecnica minima è documentata in `economic-engine-architecture.md`:

- una query tenant-scoped per I/O e proiezione;
- un motore puro per le formule;
- DTO immutabili;
- nessun calculator per ogni KPI;
- nessun repository generico, event bus o CQRS.

## 14. Soluzioni escluse

- totale Budget corrente persistito e sincronizzato;
- un documento Budget per ogni modifica;
- Addendum economico autonomo;
- forecast come secondo archivio economico;
- obbligo Estimate → Quote → Actual;
- contratti/progetti sommati direttamente alle spese;
- operational revisions usate come sorgente corrente;
- `BudgetVersion` pubblicate modificabili;
- event sourcing, CQRS, repository generici o microservizio economico.

## 15. Stato Spec Kit

- Q-034–Q-038 e Q-040: approvate e riconciliate;
- Q-039: superseded da Q-013;
- Q-041: chiusa da PD-GEN-001;
- `/speckit.plan`: da rigenerare su Constitution 3.0.1;
- `/speckit.tasks`: bloccato fino all'approvazione del piano;
- `/speckit.implement`: bloccato.
