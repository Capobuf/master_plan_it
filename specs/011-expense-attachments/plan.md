# Implementation Plan: Allegati privati

**Branch**: `laravel-replatform` | **Date**: 2026-08-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/011-expense-attachments/spec.md`

## Summary

Integrare allegati privati correnti per Expense, ExpenseRow, Contract e Project usando
`spatie/laravel-medialibrary` base 11.23.3. Laravel rimane unico owner di validazione, quota,
tenancy, autorizzazione, audit e lifecycle; React aggiunge la tab `Allegati` riusando ObjectTabs,
Modal, Button, Table e `react-dropzone`. I Media non entrano in revisioni o storia annuale.

## Technical Context

**Language/Version**: PHP 8.3 (Composer platform 8.3.32), Laravel 13.22; TypeScript 5.7,
React 19

**Primary Dependencies**: `spatie/laravel-medialibrary` 11.23.3 exact; Sanctum 4.3.3;
Spatie Permission 8.3.0; Axios 1.19; react-dropzone 14.3.5; TailAdmin React Free patterns

**Storage**: MySQL strict mode per metadata/quota; Flysystem local private disk rooted at
`storage/app/private/attachments`; no public symlink or permanent URL

**Testing**: Pest/PHPUnit 12, Laravel HTTP/Storage fakes, MySQL integration tests; Vitest,
Testing Library, ESLint, TypeScript/Vite build; browser verification desktop/mobile light/dark

**Target Platform**: Laravel API on ordinary shared hosting; browser SPA; no daemon, queue worker,
Redis, WebSocket or S3 requirement

**Project Type**: Laravel API-only backend plus React/TypeScript SPA in `frontend/`

**Performance Goals**: list queries use parent/tenant indexes; quota is one indexed tenant SUM
inside a serialized upload transaction; download streams instead of loading the file in memory

**Constraints**: 10,485,760 bytes/file; exact unsigned-bigint quota; four whitelisted parents;
fail-closed tenant isolation; zero orphan metadata/payload on handled failures; no file versioning,
deduplication, conversions, preview or remote upload

**Scale/Scope**: one current attachment collection per supported parent, three root detail pages,
one grouped Expense row experience, list/upload/download/delete only

## Constitution Check

### Pre-design gate

- **PASS — Laravel business ownership**: Actions/Queries and policies own every rule; React sends
  files and renders server outcomes only.
- **PASS — Tenant isolation**: explicit `tenant_id` on Media, parent-scoped endpoints and
  tenant-constrained lookup prevent arbitrary morph access.
- **PASS — Authorization**: attachment and parent abilities are both enforced server-side;
  inactive actor/Tenant paths reuse existing policy/context behavior.
- **PASS — Exact arithmetic**: quota remains a decimal string/unsigned bigint and uses BCMath,
  never float.
- **PASS — Explicit mutations**: upload, delete and terminal purge use focused Actions; no hidden
  business lifecycle in model observers.
- **PASS — Diagnostic failures**: private disk throws and storage/missing-file errors retain the
  application correlation reference.
- **PASS — Minimal design**: MediaLibrary supplies persistence/file lifecycle; no repository,
  DMS, event bus, second backend or parallel design system.
- **PASS — Testability**: same-Tenant allow, permission/cross-Tenant deny, concurrency and rollback
  scenarios have focused backend/frontend tasks.

### Post-design gate

All gates remain PASS. The custom Media model adds only indexed ownership/uploader columns; the
three attachment Actions and one Query are concrete behavior, not an abstraction layer. Explicit
parent controllers/routes prevent a browser-selected model class. No Complexity Tracking waiver
is required.

## Project Structure

### Documentation (this feature)

```text
specs/011-expense-attachments/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── attachments-api.md
├── checklists/
│   ├── requirements.md
│   └── upload-security-storage.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Domain/Attachments/
│   ├── Actions/{UploadAttachment,DeleteAttachment,PurgeAttachments}.php
│   ├── Queries/AttachmentQuery.php
│   └── Services/{AttachmentAuthorization,AttachmentFileValidator}.php
├── Http/Controllers/Api/V1/
│   ├── ExpenseAttachmentController.php
│   ├── ContractAttachmentController.php
│   └── ProjectAttachmentController.php
├── Http/Resources/Api/V1/AttachmentResource.php
├── Models/{Media,Expense,ExpenseRow,Contract,Project,Tenant}.php
└── Policies/{Expense,Contract,Project}Policy.php

config/
├── filesystems.php
└── media-library.php

database/migrations/2026_08_11_000005_create_media_table.php

routes/api/v1/{expenses,contracts,projects}.php

frontend/src/
├── api/attachments.ts
├── components/attachments/{AttachmentDropZone,AttachmentList,AttachmentPanel,ExpenseAttachmentsPanel}.tsx
└── pages/{Expenses/ExpenseDetail,Contracts/ContractDetail,Projects/ProjectDetail}.tsx

tests/
├── Feature/Attachments/
├── Feature/Api/Attachments/
└── Architecture/

frontend/src/components/attachments/*.test.tsx
frontend/src/pages/{Expenses,Contracts,Projects}/*Detail.test.tsx
```

**Structure Decision**: mantenere il monolite Laravel API e la SPA React esistenti. I controller
sono parent-specific per rendere la whitelist visibile nei route contract; Actions/Query comuni
evitano duplicazione delle regole senza introdurre un repository generico.

## Complexity Tracking

Nessuna violazione costituzionale da giustificare.

## Delivery Phases

1. Dipendenza/config/migration e custom Media model.
2. Validazione, autorizzazione, quota concorrente, upload/download/delete e audit.
3. Purge esplicito nel lifecycle Expense/row, Contract e Project; test indipendenza revisioni.
4. API parent-scoped e contract test.
5. Componenti TailAdmin/reac-dropzone e tab sui tre detail.
6. Demo data, documentazione permanente minima e gate completi.
