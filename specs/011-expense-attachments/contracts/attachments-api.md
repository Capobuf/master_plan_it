# API allegati privati — Feature 011

Tutti gli endpoint usano Sanctum SPA, active-user, active-Tenant e tenant context. Ogni route
richiede sia l'ability attachment sia l'ability del parent indicata. `{attachment}` è risolto solo
dentro Tenant, morph e parent esatti; mismatch e record estranei restituiscono `404` senza metadata.

## Endpoint

### Expense root

| Operazione | Endpoint | Abilities |
|---|---|---|
| lista | `GET /api/v1/expenses/{expense}/attachments` | `expense.view` + `attachment.view` |
| upload | `POST /api/v1/expenses/{expense}/attachments` | `expense.update` + `attachment.upload` |
| download | `GET /api/v1/expenses/{expense}/attachments/{attachment}/download` | `expense.view` + `attachment.view` |
| delete | `DELETE /api/v1/expenses/{expense}/attachments/{attachment}` | `expense.delete` + `attachment.delete` |

### ExpenseRow

Stessi verbi sotto
`/api/v1/expenses/{expense}/rows/{row}/attachments[/{attachment}[/download]]`. La riga deve
appartenere alla Expense e al Tenant della route. Le abilities sono quelle Expense corrispondenti.

### Contract e Project

Stessi quattro endpoint sotto:

- `/api/v1/contracts/{contract}/attachments` con `contract.view|update|delete` più ability attachment;
- `/api/v1/projects/{project}/attachments` con `project.view|update|delete` più ability attachment.

Il server non espone alcun endpoint generico con `model_class`, `model_type` o morph selezionabile.

## List response

`200 OK`

```json
{
  "data": [
    {
      "id": 42,
      "name": "Preventivo rete.pdf",
      "mime_type": "application/pdf",
      "extension": "pdf",
      "size": 83422,
      "uploaded_at": "2026-08-11T10:15:00Z",
      "uploaded_by": {"name": "Mario Rossi"}
    }
  ],
  "meta": {
    "used_bytes": "83422",
    "quota_bytes": "2147483648"
  },
  "abilities": {"upload": true, "download": true, "delete": true}
}
```

Le abilities sono calcolate dal server una volta per il parent e valgono per le azioni della lista.
La response non contiene payload, storage path, disk, morph type, parent ID, uploader ID o URL
pubblico.

## Upload

`multipart/form-data`, campo singolo obbligatorio `file`; nessun URL o array batch.

Success: `201 Created` con `AttachmentResource` singola e meta quota aggiornata.

Validation `422 VALIDATION_FAILED`, `fields.file`:

- file assente/errore upload/zero byte/>10,485,760 byte;
- estensione fuori `pdf,jpg,jpeg,png,csv,xlsx,docx`;
- MIME rilevato incoerente o contenuto OOXML non corrispondente;
- filename con path traversal/separator/control/segmento vuoto o pericoloso;
- quota zero/insufficiente: `422 ATTACHMENT_QUOTA_EXCEEDED`, senza creare Media o file.

Failure storage: `500 ATTACHMENT_STORAGE_FAILURE` con correlation ID; nessun fallback disk.

## Download

`200 OK` streamed payload con:

- `Content-Type` server-side persistito;
- `Content-Length` esatto;
- `Content-Disposition: attachment` con filename validato;
- nessun redirect o URL permanente.

Metadata presente/file assente: `500 ATTACHMENT_FILE_MISSING`, correlation ID e nessun body file.

## Delete

Il frontend richiede conferma; l'endpoint non accetta body.

Success: `204 No Content`. Media e payload sono definitivamente rimossi, la quota è libera e
l'evento `attachment.deleted` contiene soltanto filename, size, MIME e parent minimizzato.

## Failure contract comune

- `401 AUTHENTICATION_REQUIRED` actor assente;
- `403 PERMISSION_DENIED|ACCOUNT_INACTIVE|TENANT_INACTIVE` senza mutation;
- `404 RESOURCE_NOT_FOUND` parent/row/attachment estraneo o mismatch, senza leakage;
- `422 VALIDATION_FAILED|ATTACHMENT_QUOTA_EXCEEDED` input/quota;
- `500 ATTACHMENT_FILE_MISSING|ATTACHMENT_STORAGE_FAILURE` errore operativo diagnosticabile;
- ogni response errore conserva `X-Correlation-ID` e `error.correlation_id`.
