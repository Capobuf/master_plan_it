---
description: Apply changes to Frappe site in dev Docker environment (migrate + clear-cache)
---

# Apply Changes (development)

Run after editing metadata JSON or Python controllers in the app repo.

## Steps

// turbo-all
1. Run migrate to apply schema and code changes:
   ```bash
   docker exec -u "${HOST_UID:-1000}:${HOST_GID:-1000}" "${BACKEND_CONTAINER}" \
     bench --site "${SITE_NAME}" migrate
   ```

2. Clear cache:
   ```bash
   docker exec -u "${HOST_UID:-1000}:${HOST_GID:-1000}" "${BACKEND_CONTAINER}" \
     bench --site "${SITE_NAME}" clear-cache
   ```

## Notes

- Load `HOST_UID`, `HOST_GID`, `SITE_NAME`, and `BACKEND_CONTAINER` from the deploy repo's `.env` or `prod.env`.
- Default container name: `mpit-backend` (dev); check `docker ps` if unsure.
- Hard refresh browser after cache clear: Ctrl+F5.
- This workflow is for the **development** environment. Production upgrades require pulling a new image first — see `master-plan-it-deploy/README.md`.
