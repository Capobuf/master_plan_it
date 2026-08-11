# Research — Feature 008 Platform operations

## Authorization tenant-scoped

**Decision**: riusare `TenantContext`, `SetPermissionTeamContext`, `AuthorizeApplicationAbility` e
Spatie teams; aggiungere un helper focalizzato per replicare il controllo autorevole nelle Actions.

**Rationale**: il middleware copre HTTP, ma le Actions correnti sono invocate direttamente dai test
e devono restare sicure fuori dal controller. Administrator viene verificato nel team platform e
usa il Tenant selezionato; il tenant user viene verificato nel team uguale al proprio Tenant.

**Alternatives considered**: autorizzazione solo route (insufficiente), branch sul nome ruolo
(contrario all'architettura), repository/generic ACL service (troppo ampio).

## Permission upgrade

**Decision**: migration forward-only che crea sei abilities, le assegna al solo Administrator
globale e ritira i tre nomi legacy dopo la creazione; Editor/Viewer restano invariati.

**Rationale**: il seeder corrente crea/sincronizza ma non è un migration contract di produzione.
Le vecchie abilities erano protette e non assegnabili legalmente ai ruoli Tenant, quindi non esiste
un privilegio tenant da trasferire automaticamente.

**Alternatives considered**: lasciare aliases indefiniti (drift e doppio modello), assegnare ai
template Editor (escalation), cancellare senza migration (upgrade non deterministico).

## Tenant settings storage

**Decision**: mantenere i campi nella tabella `tenants` e introdurre Resource/Action focalizzati.

**Rationale**: tutti i valori approvati esistono già sul Tenant e condividono `lock_version`. Una
seconda tabella o un package key/value duplicano la fonte e complicano invarianti.

**Alternatives considered**: SettingsRepository, package settings, aggiornamento tramite
`TenantResource` globale. Tutti scartati per scope o rischio di esposizione.

## VAT default semantics

**Decision**: preservare i resolver esistenti in `ExpenseAggregateValidator` e
`ManagesContracts`; aggiungere test che passano dagli endpoint/Actions reali.

**Rationale**: entrambi risolvono l'omissione dal Tenant e persistono `vat_rate`, Net, VAT e Gross.
La nuova Action modifica solo `tenants.default_vat_rate`, quindi il comportamento è già
naturalmente forward-only.

**Alternatives considered**: observer o update massivo (vietati e distruttivi), default frontend
(non autorevole).

## Budget lock

**Decision**: usare la presenza di `approval_operations` come fonte del lock, coerente con
`UpdateTenant`, ed esporre nella projection lo stato e il codice motivo.

**Rationale**: evita una colonna duplicata e mantiene l'invariante già testata.

**Alternatives considered**: boolean persistito aggiuntivo o check frontend (fonti divergenti).

## Audit retention

**Decision**: singleton `platform_settings`, optimistic locking, conferma testuale rinforzata solo
quando il nuovo periodo è inferiore, cancellazione chunked degli esclusivi `audit_events`
eleggibili nella mutazione esplicita.

**Rationale**: rende l'operazione osservabile e limita il target alla tabella append-only prevista.

**Alternatives considered**: scheduler/worker permanente (fuori scope), cascade generica (rischio
dati business), retry silenzioso (vietato).

## Audit, notification and overview reads

**Decision**: Query/Controller focalizzati, paginazione massima 100. Notification è actor-only;
overview aggrega solo Tenant/user/audit/notification/contract dates già disponibili.

**Rationale**: minimizza payload e impedisce un generico explorer cross-Tenant o telemetria.

**Alternatives considered**: dashboard KPI economici, event stream realtime, dati placeholder.

## Settings navigation

**Decision**: route italiane nidificate concettualmente con resolver della prima sezione accessibile;
redirect dalle route precedenti. I componenti di pagina esistenti sono riusati.

**Rationale**: soddisfa l'accesso con una sola ability senza imporre Generali e conserva link
esistenti.

**Alternatives considered**: pagina monolitica con tutte le API, nuovo design system o duplicazione
delle pagine master data.

## Remaining unknowns

Nessun `NEEDS CLARIFICATION` residuo. Le scelte tecniche sono risolte dal codice e dai vincoli
approvati.
