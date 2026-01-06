---
description: Apply changes to Frappe site (migrate + clear-cache)
---

# Apply Changes Workflow

Deploy code changes to the Frappe development environment.

## Steps

// turbo-all
1. Navigate to deploy directory:
   ```bash
   cd /usr/docker/masterplan-project/master-plan-it-deploy
   ```

2. Run migrate to apply schema and code changes:
   ```bash
   docker exec -u 1000:1000 mpit-backend bench --site budget.zeroloop.it migrate
   ```

3. Clear cache to ensure fresh assets are loaded:
   ```bash
   docker exec -u 1000:1000 mpit-backend bench --site budget.zeroloop.it clear-cache
   ```

## Notes

- **Container**: `mpit-backend`
- **Site**: `budget.zeroloop.it` (from `.env` file)
- **User**: `1000:1000` (matches HOST_UID/HOST_GID in `.env`)
- The `// turbo-all` annotation allows agents to auto-run all steps
