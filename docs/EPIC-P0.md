# MPIT Financial Policy & Delivery Plan (EPIC / PDL) — V3.1 (Deterministic Dev Reset + Self-Verification Runbook)

Date: 2025-12-26  
Scope: **Dev-only**. Data is disposable. The agent must delete containers + bind-mounted data directories and re-create a clean site.

Audience: implementation agent with:
- container terminal access (frappe container)
- host access for `docker compose` (required for the reset step)

---

## 1) Non-negotiable: Dev Reset (NO backups, NO rename, NO archive)

### 1.1 Run on the HOST (required)
Run from the project root (where `compose.yml` lives). **Do not use `bench drop-site`** (it archives/backs up). We want a hard wipe.

```bash
docker compose down --remove-orphans -v
rm -rf ./data/db ./data/sites ./data/logs ./data/redis
docker compose up -d --build
```

Rationale:
- This project persists data via **bind mounts** under `./data/*`. Deleting containers without deleting `./data/*` will keep the DB/site data.

### 1.2 Enter the frappe container and confirm the site exists
```bash
docker compose exec -T frappe bash
cd /home/frappe/frappe-bench
ls -la sites
```

Determine the site name:
```bash
echo "$SITE_NAME"
# if empty:
ls -1 sites | grep -vE 'assets|common_site_config\.json'
```

---

## 2) Mandatory “apply cycle” after any code/JSON change

Inside the frappe container:

```bash
cd /home/frappe/frappe-bench
bench --site "$SITE_NAME" migrate
bench --site "$SITE_NAME" clear-cache


If you changed any `.js` files under `doctype/*/*.js`, run an asset build (this stack does not run `bench watch`):
```bash
bench build --app master_plan_it
```
```

`clear-cache` is a standard bench site command. citeturn1search1turn1search11

If Desk/UI still shows stale behavior after Python edits (dev server not reloading), restart services:

```bash
exit
docker compose restart frappe
```

---

## 3) VAT defaults & override policy (implementation target)

### 3.1 Source of defaults (repo reality)
Defaults are per-user in **MPIT User Preferences**:
- `default_vat_rate`
- `default_amount_includes_vat`

Used via helper in:
- `apps/master_plan_it/master_plan_it/mpit_user_prefs.py`

### 3.2 Correct semantics (must enforce)
- If a doc/row has explicit values set (`vat_rate`, `amount_includes_vat`), **do not override** them.
- Apply defaults only at **creation time**:
  - new parent doc
  - new child row in table fields
- Do not force defaults in server-side `validate()` (it breaks explicit user overrides, especially for Check fields).

---

## 4) P0 EPIC — Bugfix-first (end-to-end tasks + verification)

> Implement these before adding new features. They affect correctness of plan vs reality.

### P0-0 Remove Workflows (NOT optional)

We are in dev reset; **do NOT create migration patches** for workflow deletion.

#### Code tasks
1) Delete workflow folders from repo:
- `apps/master_plan_it/master_plan_it/master_plan_it/workflow/mpit_budget_workflow/`
- `apps/master_plan_it/master_plan_it/master_plan_it/workflow/mpit_budget_amendment_workflow/`

2) Convert workflow fields to editable “Status label”
Do NOT rename fieldnames (avoid churn). Keep `workflow_state` but change behavior:
- In `MPIT Budget` doctype JSON:
  - `label` -> "Status"
  - `read_only` -> 0
  - `default` -> "Draft"
  - ensure `in_list_view` = 1
- In `MPIT Budget Amendment` doctype JSON: same

3) Server-side invariant in controllers:
- Draft (`docstatus=0`) must not be “Approved”
- On submit (`docstatus=1`) force status “Approved”

4) Update repo checks:
- `apps/master_plan_it/master_plan_it/devtools/verify.py`: remove REQUIRED_WORKFLOWS enforcement
- `apps/master_plan_it/master_plan_it/tests/test_smoke.py`: remove workflow existence assertion

#### Apply cycle
Run migrate + clear cache (Section 2).

#### Verification (via console)
Open console: citeturn1search0turn1search1
```bash
cd /home/frappe/frappe-bench
bench --site "$SITE_NAME" console --autoreload

In console, set a deterministic user context (controllers read `frappe.session.user`):
```py
import frappe
frappe.set_user("Administrator")
```
```

In console:
```py
import frappe
frappe.db.exists("Workflow", "MPIT Budget Workflow")
frappe.db.exists("Workflow", "MPIT Budget Amendment Workflow")
# both must be False (or None)
```

---

### P0-1 Fix annualization year bounds (MPIT Year start/end)

#### Why
`MPIT Actual Entry` derives `year` based on the `MPIT Year` date range. Annualization must use the same bounds.

#### Code tasks
1) Edit `apps/master_plan_it/master_plan_it/annualization.py`
- `get_year_bounds(year)` must:
  - attempt to load `MPIT Year` doc (name is the integer year as string)
  - if `start_date`/`end_date` exist, use those bounds
  - fallback to calendar year if doc missing

2) Fix wrong attribute in `mpit_budget.py`
- Replace `self.fiscal_year` -> `self.year` in error message.

#### Verification (data + computation)
In Desk (or console), create:
- MPIT Year:
  - year=2025
  - start_date=2025-07-01
  - end_date=2026-06-30

Then create a Baseline Expense with:
- year=2025
- amount=100
- recurrence_rule="Monthly"
- period_start_date=2025-07-01
- period_end_date=2025-12-31

Expected:
- annual_net should represent only overlapped months within the year bounds (not the calendar year default).

---

### P0-2 Fix VAT defaults end-to-end (UI defaults, no server override)

#### Code tasks
1) Remove “force includes_vat=1” in controllers:
- `mpit_baseline_expense.py`
- `mpit_actual_entry.py`
- `mpit_contract.py` (field `current_amount_includes_vat`)
- `mpit_budget_amendment.py` (child lines)
- `mpit_project.py` (allocations)
(Only remove the override behavior; keep VAT split calculations.)

2) Add a whitelisted API to fetch defaults:
In `mpit_user_prefs.py` add:
```py
import frappe

@frappe.whitelist()
def get_vat_defaults(user=None):
    prefs = get_or_create(user or frappe.session.user)
    return {
        "default_vat_rate": prefs.default_vat_rate,
        "default_includes_vat": bool(prefs.default_amount_includes_vat),
    }
```

3) Client scripts: apply defaults on **new docs** and on **child-row add**
Implement in:
- `doctype/mpit_baseline_expense/mpit_baseline_expense.js`
- `doctype/mpit_actual_entry/mpit_actual_entry.js`
- `doctype/mpit_contract/mpit_contract.js`
- `doctype/mpit_budget/mpit_budget.js` (lines_add)
- `doctype/mpit_budget_amendment/mpit_budget_amendment.js` (lines_add)
- `doctype/mpit_project/mpit_project.js` (allocations_add)

#### Verification (must be done)
1) Set MPIT User Preferences:
- default_amount_includes_vat = 1
- default_vat_rate = 22

2) Create each doc type above:
- New doc defaults to includes VAT = 1 and vat_rate = 22
- Manually toggle includes VAT = 0 and Save
- Reopen: includes VAT must stay 0 (no server override)

---

### P0-3 Fix Approved Budget vs Actual (correctness)

#### Repo bug
Actuals are aggregated by `(year, category)` and then “spread” across vendor rows.

#### Code tasks
Edit:
- `.../report/mpit_approved_budget_vs_actual/mpit_approved_budget_vs_actual.py`

Rules:
- Aggregate both plan and actual by `(year, category, vendor)`
- **Behavior note:** Actual Entries with `vendor` empty will appear under a blank vendor bucket and will NOT be attributed to vendor-specific budget lines. This is intentional to avoid silent duplication.
- Join vendor with NULL-safe equality:
  - `a.vendor <=> b.vendor` citeturn2search1turn2search7
- Use annualized plan:
  - `SUM(COALESCE(bl.annual_net, bl.amount_net, bl.amount))`

#### Verification dataset (create via console)
Create doctypes with known required fields:

**MPIT Category**
- field required: `category_name`

**MPIT Vendor**
- field required: `vendor_name`

**MPIT Year**
- field required: `year` (name is usually set to year)

Use console:
```bash
bench --site "$SITE_NAME" console --autoreload

In console, set a deterministic user context (controllers read `frappe.session.user`):
```py
import frappe
frappe.set_user("Administrator")
```
```

Paste:
```py
import frappe

def ensure(doctype, name, fields):
    if frappe.db.exists(doctype, name):
        return frappe.get_doc(doctype, name)
    doc = frappe.get_doc({"doctype": doctype, "name": name, **fields})
    doc.insert(ignore_permissions=True)
    return doc

ensure("MPIT Year", "2025", {"year": 2025})
ensure("MPIT Category", "Connectivity", {"category_name": "Connectivity"})
ensure("MPIT Vendor", "TIM", {"vendor_name": "TIM"})
ensure("MPIT Vendor", "Dimensione", {"vendor_name": "Dimensione"})
frappe.db.commit()
```

Create a Budget and submit:
```py
# Ensure Administrator has a default VAT rate to satisfy strict VAT validation
prefs = frappe.get_doc({"doctype": "MPIT User Preferences", "user": "Administrator"}).insert(ignore_permissions=True) \
    if not frappe.db.exists("MPIT User Preferences", {"user": "Administrator"}) else frappe.get_doc("MPIT User Preferences", {"user": "Administrator"})
prefs.default_vat_rate = 22
prefs.default_amount_includes_vat = 0  # keep net as the source for deterministic expectations
prefs.save(ignore_permissions=True)
frappe.db.commit()

b = frappe.get_doc({
  "doctype": "MPIT Budget",
  "year": "2025",
  "title": "Approved Budget 2025",
  "lines": [
    {
      "doctype": "MPIT Budget Line",
      "category": "Connectivity",
      "vendor": "TIM",
      "recurrence_rule": "Monthly",
      "amount": 104,
      "amount_includes_vat": 0,
      "vat_rate": 22
    },
    {
      "doctype": "MPIT Budget Line",
      "category": "Connectivity",
      "vendor": "Dimensione",
      "recurrence_rule": "Monthly",
      "amount": 49,
      "amount_includes_vat": 0,
      "vat_rate": 22
    },
  ]
}).insert(ignore_permissions=True)

b.submit()
frappe.db.commit()
```

Create actuals only for TIM:
```py
for m in range(1, 13):
    frappe.get_doc({
      "doctype": "MPIT Actual Entry",
      "posting_date": f"2025-{m:02d}-01",
      # year is derived from posting_date; leaving it set is OK but not required
      "year": "2025",
      "category": "Connectivity",
      "vendor": "TIM",
      "amount": 100,               # treat as NET for deterministic math
      "amount_includes_vat": 0,
      "vat_rate": 22
    }).insert(ignore_permissions=True)

frappe.db.commit()
```

Run report function directly (python import):
```py
from master_plan_it.report.mpit_approved_budget_vs_actual import mpit_approved_budget_vs_actual as r
res = r.execute({"year": "2025"})
print(type(res), len(res))
cols = res[0]; data = res[1]
# Verify: TIM row actual == 1200, Dimensione row actual == 0, total actual == 1200 (no duplication).
```

---

### P0-4 Fix Current Budget vs Actual (correctness + sorting safety)

#### Code tasks
Edit:
- `.../report/mpit_current_budget_vs_actual/mpit_current_budget_vs_actual.py`

Rules:
- consistent key `(year, category, vendor)` across baseline/budget/amendment/actual
- use annual fields for plan
- vendor join uses `<=>`
- sorting normalizes nulls (avoid `TypeError`)

Verification:
- create one budget line with vendor empty (None) and one with vendor set
- run report execute and confirm it does not crash

---

### P0-5 Fix Budget print format (field alignment)

Edit:
- `.../print_format/mpit_budget_professional/mpit_budget_professional.html`

Replace missing fields:
- `doc.fiscal_year` -> `doc.year`
- remove `doc.approved_date` (not present)
- show “Status” as `doc.workflow_state` (now editable label) or derive from `doc.docstatus`

Verify by printing from Desk.

---

## 5) Running automated tests (agent must run them)

### 5.1 Run unit tests
Frappe testing docs: citeturn0search0turn2search2

Inside container:
```bash
cd /home/frappe/frappe-bench
bench --site "$SITE_NAME" run-tests
```

If you want to target a specific test / doctype (optional):
- examples exist for `--test` / `--doctype` usage. citeturn2search9

### 5.2 If tests are slow or fail early
- First ensure migrations are clean (Section 2).
- Then rerun tests.

---

## 6) Definition of Done (DoD) for the P0 EPIC

All must be true:

1) Dev reset performed (Section 1) and fresh site exists.
2) Workflows removed from repo + no workflow checks remain + console confirms Workflow docs absent.
3) Annualization year bounds use MPIT Year start/end.
4) VAT defaults:
   - applied on new docs/rows via JS
   - never overridden server-side if user explicitly sets 0
5) Approved Budget vs Actual report:
   - no vendor duplication
   - uses annual planned values
6) Current Budget vs Actual report:
   - no vendor duplication
   - sorting safe with NULL vendor
7) Budget print format renders without template errors.
8) `bench --site "$SITE_NAME" run-tests` completes without failures.

---
End.
