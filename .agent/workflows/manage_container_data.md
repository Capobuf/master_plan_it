---
description: Create, read, update, and delete data in the MPIT container using bench console
---

# Manage Container Data (CRUD)

Use `bench console` for debugging, data fixes, or scripted updates.

## Prerequisites

Load environment variables from the deploy repo's `.env` or `prod.env`:

```bash
export $(grep -v '^#' /path/to/master-plan-it-deploy/.env | xargs)
# Expected: HOST_UID, HOST_GID, SITE_NAME, BACKEND_CONTAINER (e.g. mpit-backend)
```

**IMPORTANT:** Any write/update/delete operation requires an explicit `frappe.db.commit()` at the end, or the transaction is rolled back when the console exits.

## Pattern: pipe a script into bench console

```bash
cat script.py | docker exec -i -u "${HOST_UID}:${HOST_GID}" "${BACKEND_CONTAINER}" \
  bench --site "${SITE_NAME}" console
```

## 1. Read

```python
import frappe
print(frappe.get_all('MPIT Project', fields=['name', 'title']))
```

## 2. Create

```python
import frappe
doc = frappe.new_doc('MPIT Project')
doc.title = 'New Project'
doc.cost_center = 'All Cost Centers'
doc.status = 'Draft'
doc.insert()
frappe.db.commit()  # required
print(f"Created: {doc.name}")
```

## 3. Update

```python
import frappe
try:
    doc = frappe.get_doc('MPIT Project', 'PRJ-1')
    doc.description = 'Updated description'
    doc.save()
    frappe.db.commit()  # required
    print(f"Updated: {doc.name}")
except frappe.DoesNotExistError:
    print("Doc not found")
```

## 4. Delete

```python
import frappe
frappe.delete_doc('MPIT Project', 'PRJ-X')
frappe.db.commit()  # required
print("Deleted")
```

## References

- `bench --site <site> console` — interactive Python shell
- `frappe.new_doc()`, `doc.save()`, `frappe.delete_doc()` — document API
- `frappe.db.commit()` — always required after writes
