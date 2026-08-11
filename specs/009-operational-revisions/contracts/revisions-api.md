# API revisioni operative — Feature 009

Tutti gli endpoint usano Sanctum SPA, active-user, active-Tenant, tenant context e l'ability del
parent. ID estranei, source fuori dalle dieci revisioni visibili e parent/child mismatch falliscono
closed senza rivelare l'esistenza del dato.

Il parametro `{revision}` identifica una logical `RevisionBatch`; non identifica una riga
`versions` e non viene mostrato nella UI come normale copy.

## Endpoint uniformi

| Parent | List | Compare | Restore |
|---|---|---|---|
| Expense | `GET /api/v1/expenses/{expense}/history?year={planningYear}` | `GET /api/v1/expenses/{expense}/history/{revision}?year={planningYear}` | `POST /api/v1/expenses/{expense}/history/{revision}/restore` |
| Contract | `GET /api/v1/contracts/{contract}/history` | `GET /api/v1/contracts/{contract}/history/{revision}` | `POST /api/v1/contracts/{contract}/history/{revision}/restore` |
| Project | `GET /api/v1/projects/{project}/history` | `GET /api/v1/projects/{project}/history/{revision}` | `POST /api/v1/projects/{project}/history/{revision}/restore` |

Vendor e Cost Center mantengono i path parent-scoped esistenti; `{version}` viene sostituito da
`{revision}` logico e la response non espone più `source_revision_id` Version.

## List history

Abilities: `<parent>.view-revisions` e accesso view al parent.

Query opzionale: `page` positivo e `per_page` 1–10, default 10. La response non può contenere più di
dieci revisioni totali anche richiedendo pagine o limiti maggiori.

```json
{
  "data": [
    {
      "id": 410,
      "operation": "update",
      "actor": {"kind": "system", "label": "Sistema"},
      "timestamp": "2026-08-11T09:30:00Z",
      "summary": "Promozione automatica del progetto",
      "changed_count": 2,
      "changed_fields": ["Stage", "Anno di destinazione"],
      "can_compare": true,
      "can_restore": true
    }
  ],
  "meta": {"current_page": 1, "last_page": 1, "per_page": 10, "total": 1}
}
```

`actor.label` è il nome corrente/minimizzato dell'utente oppure `Sistema`; non è mai un ID.
`can_restore` incorpora ability e incompatibilità già determinabili senza mutare.

## Compare selected vs current

Ability: `<parent>.view-revisions`.

```json
{
  "data": {
    "revision": {
      "id": 410,
      "operation": "update",
      "actor": {"kind": "human", "label": "Mario Rossi"},
      "timestamp": "2026-08-11T09:30:00Z",
      "summary": "Aggiornamento condizioni",
      "can_restore": true
    },
    "changes": [
      {
        "scope": "contract",
        "subject": "Contratto",
        "field": "vendor",
        "label": "Fornitore",
        "revision_value": "Microsoft Italia",
        "current_value": "Unidos S.r.l."
      },
      {
        "scope": "contract_term",
        "subject": "Termine 01/01/2026–31/12/2026",
        "field": "gross_amount",
        "label": "Importo lordo",
        "revision_value": "1220.00",
        "current_value": "1464.00"
      }
    ]
  }
}
```

La lista contiene solo differenze. `field` è una chiave client stabile, non il nome SQL; relazioni
ed enum sono label/valori semantici, mentre i decimali restano stringhe canoniche scale-two e sono
localizzati dalla presentazione condivisa. Expense usa scope `expense`/`expense_row`; Project `project`.
`lock_version`, tenant ID, Version ID, FK numeriche e provenance tecnica non compaiono.
Quando una relazione legacy non ha più una label ricostruibile, il valore è
`Riferimento non più disponibile`: l'API non restituisce la FK come fallback e non inventa una
label.

## Restore

Ability: `<parent>.restore-revision` oltre all'accesso parent. Conferma UX obbligatoria prima della
request.

```json
{"lock_version": 7}
```

Success: `200 OK` con il Resource corrente completo e nuovo `lock_version`. Il set allegati non fa
parte della request/response revisionale e non viene modificato.

Failure stabile:

- `404 RESOURCE_NOT_FOUND`: parent/source estraneo, terminale, fuori top-10 o mismatch;
- `403 PERMISSION_DENIED`: ability mancante;
- `409 STALE_VERSION`: current root lock non coincide, rollback totale;
- `422 REVISION_RESTORE_INVALID`: snapshot legacy/incompleto, riferimento corrente invalido,
  ContractTerm terminale o altra invariante corrente;
- nessun successo parziale e nessuna nuova batch/version/audit su failure.

## Backward compatibility

- Project conserva i path correnti e cambia soltanto payload semantico/diff-only.
- Contract conserva il list path e aggiunge compare/restore.
- Expense aggiunge i tre path; l'activity embedded nel detail può restare temporaneamente ma usa la
  stessa lista limitata e non è una seconda contract source.
- Vendor/Cost Center cambiano il source pubblico da Version ID a batch ID; i vecchi Version ID non
  vengono accettati come fallback silenzioso.
