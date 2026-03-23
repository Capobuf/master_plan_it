---
description: Read data from the MPIT container using bench console or bench execute
---

# Read Container Data

Execute Frappe queries on the running container.

## Prerequisites

Load environment variables from the deploy repo's `.env` or `prod.env`:

```bash
export $(grep -v '^#' /path/to/master-plan-it-deploy/.env | xargs)
# Expected: HOST_UID, HOST_GID, SITE_NAME, BACKEND_CONTAINER (e.g. mpit-backend)
```

## Execute a query

```bash
# Get metadata (schema)
docker exec -u "${HOST_UID}:${HOST_GID}" "${BACKEND_CONTAINER}" \
  bench --site "${SITE_NAME}" execute frappe.get_meta --args "('MPIT Project',)"

# Get data (list records)
docker exec -u "${HOST_UID}:${HOST_GID}" "${BACKEND_CONTAINER}" \
  bench --site "${SITE_NAME}" execute frappe.get_all \
  --args "('MPIT Project',)" --kwargs "{'fields': ['name', 'title']}"
```

## Python script for complex queries

```bash
# Pipe a script into bench console
echo "import frappe; print(frappe.get_all('MPIT Project', fields=['name', 'title']))" \
  | docker exec -i -u "${HOST_UID}:${HOST_GID}" "${BACKEND_CONTAINER}" \
    bench --site "${SITE_NAME}" console
```

## References

- `bench --site <site> execute` — run a single Python expression
- `bench --site <site> console` — interactive Python shell with Frappe context
- `frappe.get_all()`, `frappe.db.get_value()` — read API
