# API contract — Impostazioni generali del Tenant

Base path: `/api/v1`. Le route richiedono sessione SPA, actor attivo, Tenant context, permission-team context e Tenant attivo secondo i middleware correnti. Le response usano l'envelope `data`; gli errori usano il contratto applicativo corrente con correlation id.

## `GET /tenant-settings`

Ability: `tenant-settings.view` oppure `tenant-settings.update` per consentire al modificatore di caricare il form.

Il Tenant deriva esclusivamente dal contesto.

Response `200`:

```json
{
  "data": {
    "tenant_id": 1,
    "name": "Azienda",
    "currency_code": "EUR",
    "timezone": "Europe/Rome",
    "default_vat_rate": "22.00",
    "budget_basis": "net",
    "budget_basis_locked": false,
    "budget_basis_lock_reason": null,
    "deletion_reason_required": false,
    "lock_version": 1
  }
}
```

Errori: `401` non autenticato; `403` permesso/context/inattivo.

## `PUT /tenant-settings`

Ability: `tenant-settings.update`.

Request chiusa:

```json
{
  "name": "Azienda",
  "timezone": "Europe/Rome",
  "default_vat_rate": "20.00",
  "budget_basis": "net",
  "deletion_reason_required": true,
  "lock_version": 1
}
```

`tenant_id`, `currency_code`, quota allegati e ogni altro campo vengono rifiutati con `422`. Response `200`: stessa proiezione aggiornata.

Errori: `401`; `403`; `409 STALE_VERSION`; `409 TENANT_BUDGET_BASIS_LOCKED`; `422` validazione.

## Confine con il registro globale Tenant

`POST /tenants` conserva il contratto bootstrap esistente: `name`, `code`, `currency_code`, `language_code`, `timezone` e `default_vat_rate` sono necessari per creare un Tenant che ancora non può essere selezionato.

`PUT /tenants/{tenant}` accetta esclusivamente:

```json
{
  "code": "ACME",
  "currency_code": "EUR",
  "language_code": "it",
  "lock_version": 3
}
```

I campi sono parziali: almeno uno tra codice, valuta e lingua deve essere presente. `name`, `timezone`, `default_vat_rate`, `budget_basis`, `deletion_reason_required` e ogni altro campo vengono rifiutati con `422` senza mutazioni o audit. La response globale può restare la proiezione Tenant completa; il write owner dei campi operativi è esclusivamente `PUT /tenant-settings`.

## Contratto IVA sugli endpoint economici esistenti

Negli input esistenti di creazione/aggiornamento Spese e Contratti, `rows.*.vat_rate` e `terms.*.vat_rate` restano nullable/omettibili. Omissione o null su un nuovo elemento significa “usa il default Tenant corrente”; omissione o null su un elemento esistente significa “conserva l'aliquota persistita”. La stringa `"0.00"` è un override esplicito valido. Le response continuano a restituire aliquota e importi effettivi persistiti.
