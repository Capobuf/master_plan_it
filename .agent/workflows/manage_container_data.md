---
description: How to Create, Read, Update, and Delete data in the MPIT container using bench console.
---

This workflow explains how to manage data (CRUD operations) directly on the running container using `bench console`. This method is useful for debugging, data fixing, or scripted updates.

## Prerequisites

- Ensure you are in the deploy directory: `/usr/docker/masterplan-project/master-plan-it-deploy`
- Ensure the container `mpit-backend` is running.
- **IMPORTANT**: For any Write/Update/Delete operation, you must explicitly call `frappe.db.commit()`, otherwise changes will be rolled back when the console exits.

## Setup: Environment Variables

First, load the environment variables to avoid hardcoding:

```bash
# Load variables
export $(grep -v '^#' /usr/docker/masterplan-project/master-plan-it-deploy/.env | xargs)
```

## 1. Read Data (Select)

To read data, you can use `frappe.get_all` or `frappe.get_doc`.

```bash
echo "import frappe; print(frappe.get_all('MPIT Project', fields=['name', 'title']))" | docker exec -i -u ${HOST_UID}:${HOST_GID} mpit-backend bench --site ${SITE_NAME} console
```

## 2. Create Data (Insert)

To create a new document:
1.  Initialize with `new_doc`.
2.  Set mandatory fields.
3.  Call `insert()`.
4.  **Call `frappe.db.commit()`**.

```bash
# create_script.py
import frappe
doc = frappe.new_doc('MPIT Project')
doc.title = 'New Project'
doc.cost_center = 'Spese Interne' # Ensure this foreign key valid
doc.status = 'Draft'
doc.insert()
frappe.db.commit() # CRITICAL
print(f"Created: {doc.name}")
```

**Execute:**
```bash
cat create_script.py | docker exec -i -u ${HOST_UID}:${HOST_GID} mpit-backend bench --site ${SITE_NAME} console
```

## 3. Update Data (Save)

To update an existing document:
1.  Fetch with `get_doc`.
2.  Modify fields.
3.  Call `save()`.
4.  **Call `frappe.db.commit()`**.

```bash
# update_script.py
import frappe
try:
    doc = frappe.get_doc('MPIT Project', 'PRJ-1')
    doc.description = 'Updated Description'
    doc.save()
    frappe.db.commit() # CRITICAL
    print(f"Updated: {doc.name}")
except frappe.DoesNotExistError:
    print("Doc not found")
```

**Execute:**
```bash
cat update_script.py | docker exec -i -u ${HOST_UID}:${HOST_GID} mpit-backend bench --site ${SITE_NAME} console
```

## 4. Delete Data

To delete a document:
1.  Call `frappe.delete_doc`.
2.  **Call `frappe.db.commit()`**.

```bash
# delete_script.py
import frappe
frappe.delete_doc('MPIT Project', 'PRJ-X')
frappe.db.commit() # CRITICAL
print("Deleted")
```

**Execute:**
```bash
cat delete_script.py | docker exec -i -u ${HOST_UID}:${HOST_GID} mpit-backend bench --site ${SITE_NAME} console
```

## References

- [Bench Commands](file:///usr/docker/masterplan-project/master-plan-it/docs/_vendor/frappev15/bench/frappe-commands.md) (`console`)
- [Document API](file:///usr/docker/masterplan-project/master-plan-it/docs/_vendor/frappev15/api/document.md) (`new_doc`, `save`, `delete`)
- [Database API](file:///usr/docker/masterplan-project/master-plan-it/docs/_vendor/frappev15/api/database.md) (`frappe.db.commit`, `frappe.get_all`)
