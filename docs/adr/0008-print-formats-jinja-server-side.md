# ADR 0008 — Print formats (Jinja, server-side)

## Decision
Print formats are maintained as server-side Jinja templates checked into the canonical module folder (`apps/master_plan_it/master_plan_it/master_plan_it/print_format/`). They may be imported with `frappe.modules.import_file.import_file_by_path()` when needed, but no custom sync pipeline is used.

## Rationale
- Keep print formats versioned alongside the rest of the app metadata.
- Avoid runtime templating risks by rendering on the server.
- Rely on standard Frappe file sync (`migrate`, `clear-cache`) to apply changes; Desk edits must be exported immediately back into the canonical folder.

## Implications
- No `sync_all` / spec-import flow; file-first only.
- When adjusting formats in Desk, export them to `print_format/` and commit.
- Existing fixtures should remain minimal and filtered to MPIT records only.
