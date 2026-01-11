---
description: How to read data from the MPIT container using dynamic configuration from .env
---

This workflow explains how to execute Frappe commands (like `frappe.get_all` or `frappe.get_meta`) directly on the running container, using the configuration defined in the local `.env` file.

## Prerequisites

- Ensure you are in the deploy directory or know its path: `/usr/docker/masterplan-project/master-plan-it-deploy`
- Ensure the container `mpit-backend` is running.

## Steps

1.  **Load Environment Variables**: Read the `.env` file to get the `HOST_UID`, `HOST_GID`, and `SITE_NAME`.
    ```bash
    # Example command to extract variables (for reference)
    export $(grep -v '^#' /usr/docker/masterplan-project/master-plan-it-deploy/.env | xargs)
    ```

2.  **Construct the Command**: Use the variables to build the `docker exec` command.
    - **User**: `$HOST_UID:$HOST_GID` (e.g., `1000:1000`)
    - **Site**: `$SITE_NAME` (e.g., `budget.zeroloop.it`)
    - **Container**: `mpit-backend` (Standard container name)

3.  **Execute the Command**: Run the command using `bench execute`.

    **Example: Get Metadata (Schema)**
    ```bash
    docker exec -u ${HOST_UID}:${HOST_GID} mpit-backend bench --site ${SITE_NAME} execute frappe.get_meta --args "('MPIT Project',)"
    ```

    **Example: Get Data (List Records)**
    ```bash
    docker exec -u ${HOST_UID}:${HOST_GID} mpit-backend bench --site ${SITE_NAME} execute frappe.get_all --args "('MPIT Project',)" --kwargs "{'fields': ['name', 'title']}"
    ```

## Python Script Example (for complex queries)

If you need to run complex logic, you can pipe a python script:

```bash
# internal_script.py
import frappe
print(frappe.get_all('MPIT Project', fields=['name', 'title']))
```

```bash
cat internal_script.py | docker exec -i -u ${HOST_UID}:${HOST_GID} mpit-backend bench --site ${SITE_NAME} console
```

## References

- [Bench Commands](file:///usr/docker/masterplan-project/master-plan-it/docs/_vendor/frappev15/bench/frappe-commands.md) (`execute`, `console`)
- [Database API](file:///usr/docker/masterplan-project/master-plan-it/docs/_vendor/frappev15/api/database.md) (`frappe.get_all`)
