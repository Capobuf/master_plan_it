# Field Help Text Inventory

- App package root: `master_plan_it/master_plan_it`; DocTypes live in `master_plan_it/master_plan_it/doctype/**/<doctype>.json`.
- Label convention: labels are predominantly English; keep DocField `description` strings in English and translate them to Italian in `translations/it.csv`.
- Translation file present: `master_plan_it/master_plan_it/translations/it.csv` (3 columns: `source_string,translated_string,context`).
- Policy: do not invent business rules; base help text on fieldtype, label/fieldname, options, or confirmed controller logic. If unclear, pause and ask for wording options.
- Current state: all meaningful DocFields now have bilingual descriptions (English source + Italian translation).

## Conventions and rules

- DocField `description` renders as helper text below the field in Frappe.
- Source strings stay English in DocType JSON; add Italian translations to `master_plan_it/master_plan_it/translations/it.csv` (leave context blank for DocField descriptions because Frappe does not pass context for field metadata).
- Keep copy short (1 sentence, ≤120 chars), imperative/conditional; note VAT/recurrence/units only when present in schema or controllers.
- STOP when meaning is unclear; gather wording options instead of guessing.
- Checklist: edit DocType JSON → add matching `it.csv` line → log changes/evidence in `docs/ux/field-help-text-report.md`.
