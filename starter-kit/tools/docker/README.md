# Docker quick commands (frappe_docker style)

These are examples if you're using `frappe_docker` where the backend container runs bench.

```bash
# open a shell
docker compose exec backend bash

# create a site (example)
bench new-site mpit.local --admin-password admin --mariadb-root-password admin

# install app
bench --site mpit.local install-app master_plan_it

# sync specs & migrate
bench --site mpit.local migrate
bench --site mpit.local migrate
```
