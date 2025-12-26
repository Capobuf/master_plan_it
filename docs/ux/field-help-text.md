# Field Help Text Inventory

- App package root: `apps/master_plan_it/master_plan_it`; DocTypes live in `apps/master_plan_it/master_plan_it/master_plan_it/doctype/**/<doctype>.json`.
- Label convention: labels are predominantly English; keep DocField `description` strings in English and translate them to Italian in `translations/it.csv`.
- Translation file present: `apps/master_plan_it/master_plan_it/translations/it.csv` (3 columns: `source_string,translated_string,context`).
- Policy: do not invent business rules; base help text on fieldtype, label/fieldname, options, or confirmed controller logic. If unclear, pause and ask for wording options.
- Current state: all meaningful DocFields now have bilingual descriptions (English source + Italian translation).

## Conventions and rules

- DocField `description` renders as helper text below the field in Frappe.
- Source strings stay English in DocType JSON; add Italian translations to `apps/master_plan_it/master_plan_it/translations/it.csv` (leave context blank for DocField descriptions because Frappe does not pass context for field metadata).
- Keep copy short (1 sentence, ≤120 chars), imperative/conditional; note VAT/recurrence/units only when present in schema or controllers.
- STOP when meaning is unclear; gather wording options instead of guessing.
- Checklist: edit DocType JSON → add matching `it.csv` line → log changes/evidence in `docs/ux/field-help-text-report.md`.

## Inventory snapshots

- Before (Phase 1): 16 DocTypes, 171 meaningful fields missing description (see report for prior counts).
- After (current): 0 meaningful fields missing description across all DocTypes. Table below shows the current state.

Counts per DocType (current totals | with description | meaningful fields missing description):

- MPIT Actual Entry: 16 | 16 | 0
- MPIT Amendment Line: 12 | 12 | 0
- MPIT Baseline Expense: 24 | 24 | 0
- MPIT Budget: 14 | 9 | 0
- MPIT Budget Amendment: 7 | 6 | 0
- MPIT Budget Line: 22 | 22 | 0
- MPIT Category: 9 | 9 | 0
- MPIT Contract: 21 | 21 | 0
- MPIT Project: 12 | 9 | 0
- MPIT Project Allocation: 7 | 7 | 0
- MPIT Project Milestone: 6 | 6 | 0
- MPIT Project Quote: 10 | 10 | 0
- MPIT Settings: 3 | 3 | 0
- MPIT User Preferences: 12 | 8 | 0
- MPIT Vendor: 6 | 6 | 0
- MPIT Year: 4 | 4 | 0
