# Field Help Text Inventory

- App package root: `master_plan_it/master_plan_it`; DocTypes live in `master_plan_it/master_plan_it/doctype/**/<doctype>.json`.
- Label convention: labels are predominantly English; keep DocField `description` strings in English and translate them to Italian in `locale/it.po`.
- Translation files: `master_plan_it/master_plan_it/locale/main.pot` + `master_plan_it/master_plan_it/locale/it.po`.
- Policy: do not invent business rules; base help text on fieldtype, label/fieldname, options, or confirmed controller logic. If unclear, pause and ask for wording options.
- Current state: all meaningful DocFields now have bilingual descriptions (English source + Italian translation).

## Conventions and rules

- DocField `description` renders as helper text below the field in Frappe.
- Source strings stay English in DocType JSON; add Italian translations to `master_plan_it/master_plan_it/locale/it.po`.
- Keep copy short (1 sentence, ≤120 chars), imperative/conditional; note VAT/recurrence/units only when present in schema or controllers.
- STOP when meaning is unclear; gather wording options instead of guessing.
- Checklist: edit DocType JSON → regenerate `main.pot` / update `it.po` → compile `.mo` → `bench --site <site> clear-cache` + hard refresh.
