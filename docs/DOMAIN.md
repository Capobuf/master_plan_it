# Regole di dominio implementate

Stato: `VERIFIED CURRENT` / decisioni permanenti già implementate alla baseline
`8f0f5660b409b562d354589d9e00012f31df8ef2`.

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

## Anni di pianificazione

- L'identità è l'anno solare del Tenant.
- Inizio e fine sono derivati: 1 gennaio e 31 dicembre.
- Le date non sono modificabili.
- Sono ammessi create, deactivate e reactivate secondo permission.
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
- Possono coesistere più row quando rappresentano costi distinti.
- Actual può essere `ToConfirm` oppure `Confirmed`.
- La conferma non rende l'Actual immutabile: le normali correzioni autorizzate restano possibili.
- Estimate e Quote non possono avere importo netto negativo; Actual può essere negativo.
- Una row usa una singola data di spesa oppure un periodo completo con distribuzione, mai entrambi.
- Le distribuzioni supportate sono `all`, `start`, `end`.
- Extra e finanziamento tramite Plafond sono mutuamente esclusivi.
- Un Plafond referenziato deve appartenere allo stesso Tenant e anno; il cost center può differire.
- Solo Expense row correnti e non eliminate entrano nei totali correnti.
- I valori economici autorevoli sono calcolati dal backend e mantengono Net, VAT e Gross.
- Gli update concorrenti usano optimistic locking.

## Contratti e generazione

- I Contract sono tenant-owned e non costituiscono una sorgente economica aggiuntiva.
- I Contract term non possono sovrapporsi.
- I cicli supportati sono Monthly e Annual.
- Ogni occurrence generata ha una source key stabile, tenant-scoped e unica.
- Una nuova occurrence valida genera un Actual `ToConfirm` inizialmente system-managed.
- La sincronizzazione può modificare solo una occurrence ancora system-managed.
- Modifica manuale o conferma rende l'Actual user-authoritative e impedisce successivi overwrite
  automatici.
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
- Il Plafond non deve produrre doppio conteggio della parte già coperta dall'allocazione.
- Dashboard, Budget e Report usano lo stesso economic kernel/server dataset.
- Un output economico non può combinare più Tenant.

## Lingua dell'interfaccia

Il launch corrente usa copy e route utente in italiano, con le sole eccezioni indicate in
`docs/ARCHITECTURE.md`.
