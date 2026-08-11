# Data model — Impostazioni generali del Tenant

## Tenant

Entità esistente; nessuna nuova tabella.

| Campo | Tipo logico | Uso nella feature | Regole |
|---|---|---|---|
| `id` | intero | identità derivata dal contesto | mai accettato dal payload settings |
| `name` | testo | nome operativo modificabile | non vuoto, massimo corrente |
| `currency_code` | codice valuta | visualizzazione read-only | non modificabile qui |
| `timezone` | timezone | modificabile | timezone valida |
| `default_vat_rate` | decimale esatto | default per nuovi elementi | non negativo, massimo 2 decimali |
| `budget_basis` | enum | `net` o `gross` | immutabile dopo la prima approvazione |
| `deletion_reason_required` | booleano | regola per cancellazioni future | non retroattiva |
| `lock_version` | intero positivo | concorrenza ottimistica | incrementato a ogni salvataggio riuscito |

Campi esplicitamente non esposti: `code`, `language_code`, `attachment_quota_bytes`, `company_name`, `address`, `contact_name`, `contact_email`, `contact_phone`, `report_logo_path`, lifecycle e ownership.

### Matrice di ownership dei campi Tenant

| Campo | Creazione globale | Aggiornamento globale | Generali Tenant |
|---|---|---|---|
| `name` | richiesto per bootstrap | non accettato | modificabile |
| `code` | richiesto | modificabile | non esposto |
| `currency_code` | richiesto | modificabile | sola lettura |
| `language_code` | richiesto | modificabile | non esposto |
| `timezone` | richiesto per bootstrap | non accettato | modificabile |
| `default_vat_rate` | richiesto per bootstrap | non accettato | modificabile |
| `budget_basis` | default iniziale `net` | non accettato | modificabile finché non bloccato |
| `deletion_reason_required` | default iniziale `false` | non accettato | modificabile |
| lifecycle | stato iniziale attivo | azioni dedicate | non esposto |

La response globale può continuare a proiettare i valori necessari al contesto e all'identificazione, ma non determina il relativo write contract.

## Permission

Due nomi aggiunti al catalogo chiuso:

- `tenant-settings.view`: lettura della proiezione del Tenant corrente;
- `tenant-settings.update`: mutazione della stessa proiezione e lettura necessaria alla pagina.

Sono tenant-scoped e assegnabili esplicitamente a ruoli Tenant. Non entrano automaticamente nei template Editor o Viewer. Il ruolo Administrator globale le riceve per mantenere l'accesso nel Tenant selezionato. L'upgrade invalida la cache autorizzativa dopo avere creato e assegnato le abilities; prima della selezione di un Tenant le abilities tenant-scoped restano intenzionalmente assenti dal contesto.

## Expense row

Entità esistente. Quando un nuovo input omette `vat_rate`, il validatore risolve `Tenant.default_vat_rate` e persiste:

- `vat_rate` effettivo;
- `net_amount`;
- `vat_amount`;
- `gross_amount`.

I record esistenti non vengono toccati dalla modifica del Tenant. Se un update di una riga esistente omette `vat_rate`, viene preservata l'aliquota persistita della riga; l'omissione non la sostituisce con il nuovo default.

## Contract term

Entità esistente con la stessa risoluzione forward-only dell'Expense row. Il client deve rappresentare “nessuna aliquota esplicita” di un termine nuovo come omissione/null, non come zero. Su un termine esistente, l'omissione conserva l'aliquota persistita.

## Approval operation

La presenza di almeno una riga dello stesso Tenant rende `budget_basis` bloccata. Non viene aggiunta una relazione o colonna duplicata.

## Audit event

Ogni salvataggio settings riuscito registra tipo evento, actor, Tenant, correlation id, timestamp e soli nomi dei campi cambiati. Nessun valore economico o dato sensibile è necessario nel payload audit.

## State transitions

```text
Tenant settings vN --save valido e autorizzato--> Tenant settings vN+1
Tenant settings vN --lock stale/errore/deny--------> invariato
budget_basis libero --prima approvazione----------> bloccato permanentemente
default IVA X --modifica a Y----------------------> nuovi elementi usano Y;
                                                   elementi esistenti restano X
```
