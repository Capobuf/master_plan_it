# Reference: Printing (Frappe v15)

Guidance for MPIT print formats with the native file-first workflow.

- Store print formats under `apps/master_plan_it/master_plan_it/master_plan_it/print_format/` and version them in Git.
- Prefer server-side Jinja templates; avoid client-side rendering for stability.
- After Desk edits, export the print format back into the canonical folder immediately.
- Apply changes with standard Frappe commands (`bench --site <site> migrate`, `clear-cache`); no custom `sync_all` / spec-import pipeline.
- You can import a specific print format manually with `frappe.modules.import_file.import_file_by_path()` if needed.
