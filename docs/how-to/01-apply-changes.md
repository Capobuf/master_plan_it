# How-to: Apply changes (native file-first)

**Source of truth:** exported metadata (JSON) and controllers (Python/JS) inside the app’s **python package folder** (the folder that contains `hooks.py`) under your bench `apps/master_plan_it/...`.  
**Rules:** stay native (no spec/import pipeline, no `sync_all`). Apply changes using standard Bench/Frappe commands.

---

## Change type → what to do

| Change type | Apply | Then |
| --- | --- | --- |
| **DocType / Workflow / Workspace / Report / Print Format JSON** (metadata) | **Recommended:** `bench --site <site> migrate`  \| **Targeted (layout-only DocType):** `bench --site <site> reload-doctype "<DocType Name>"` | `bench --site <site> clear-cache` + hard refresh (Ctrl+F5) |
| **Python code** (controllers/hooks/patch code) | If your web process does **not** autoreload, **restart** the web process. If schema/metadata also changed, run `bench --site <site> migrate`. | Hard refresh after reload/restart |
| **Frontend JS** (client scripts/custom JS assets) | `bench build` (only when JS assets change) | Restart web/socketio if needed, `bench --site <site> clear-cache`, hard refresh |
| **Translations** (`translations/it.csv`) | `bench --site <site> clear-cache` (and hard refresh) | If still stale: restart web process; if you changed JS assets, run `bench build` |
| **Fixtures** (`fixtures/*.json`, `hooks.py` fixtures list) | `bench --site <site> migrate` | `bench --site <site> clear-cache` |
| **Patches** (`patches.txt` + patch modules) | `bench --site <site> migrate` (runs patches) | `bench --site <site> clear-cache` |

DocField help text (`description`) updates are DocType JSON changes: apply with migrate or targeted `reload-doctype`, then `clear-cache` + hard refresh.

Note: Default child-table columns/ordering come from `in_list_view` flags on the child DocType. Column widths set via “Configure Columns” are typically per-user; the app can set default visibility/order only.

---

## Operator apply sequence (pick one per deployment)

- **A) Full sync (safest):**  
  `bench --site <site> migrate` → `bench --site <site> clear-cache` → hard refresh browser  
  *(Optionally add `bench --site <site> clear-website-cache` if you rely on website caching.)*

- **B) Targeted DocType reload (layout-only):**  
  `bench --site <site> reload-doctype "<DocType Name>"` → `bench --site <site> clear-cache` → hard refresh browser

---

## Workspace changes (Desk) — Apply + Verify + Debug

### Apply (standard)
- `bench --site <site> migrate`
- `bench --site <site> clear-cache`  
- Optional: `bench --site <site> clear-website-cache`
- Hard refresh Desk (Ctrl+F5)

### Verify (DB-level, reliable)
Use console for deterministic verification:

- `bench --site <site> console`
- In console:
  - `import frappe`
  - `print(frappe.db.get_value("Workspace", "Master Plan IT", ["modified", "content"]))`

Confirm:
- `modified` changed after the update
- `content` contains the expected new shortcut/block (e.g. “User Preferences”)

### Debug (when it doesn’t move)
1) **Prove you edited the canonical file path** used by the running bench/container  
   - In Docker setups, confirm the bind-mount for `/home/frappe/frappe-bench/apps/master_plan_it` points to the same host folder you edited.
2) **Check Workspace 2.0 file layout**  
   - Workspace JSON should follow the folder pattern:  
     `<app>/<module>/workspace/<workspace_id>/<workspace_id>.json`  
     where `<workspace_id>` is the folder name used in the app (do not assume it without checking).
3) **Fallback: push the file into DB using native import**  
   - `bench --site <site> import-doc <ABS_PATH_TO_WORKSPACE_JSON>`
   - Then `bench --site <site> clear-cache` (+ optional `clear-website-cache`) and hard refresh
4) **Stop before destructive actions**  
   - If import still fails, do **not** delete/recreate the Workspace automatically. Investigate paths, JSON structure, and permissions first.

---

## Troubleshooting (manual)

- Confirm you edited the **canonical** path used by the running bench/container (avoid stale duplicates).
- Confirm you are operating on the correct **site** name.
- If UI is stale:
  - hard refresh (Ctrl+F5)
  - `bench --site <site> clear-cache`
  - restart web/socketio **only if** your processes are not autoreloading changes
- In bind-mounted dev: host file edits are visible immediately in the container filesystem, but **Frappe applies them only after the apply steps above**.

---

## Decision rules (anti-drift)

- DocType/schema JSON changes → **always** `bench --site <site> migrate` (targeted reload only for layout-only DocType tweaks).
- Workspace updates → migrate → verify DB → `import-doc` fallback → verify DB again.
- Always verify DB fields (`modified` + the changed field(s), e.g. `content`) **before claiming success**.

---

## Verification example: “MPIT Budget totals to the right”

- File edited: `<bench>/apps/master_plan_it/.../doctype/mpit_budget/mpit_budget.json`
- Apply:
  - Option A: `bench --site <site> migrate` → `bench --site <site> clear-cache`
  - Option B (layout-only): `bench --site <site> reload-doctype "MPIT Budget"` → `bench --site <site> clear-cache`
- Hard refresh Desk and confirm the Totals block renders on the right.
