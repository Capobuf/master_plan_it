# Docker Compose notes

- Main file: `compose.yml` (uses `Dockerfile.frappe`).
- Set `INSTALL_APPS=master_plan_it` to install the app during bootstrap.
- Mount `./apps/master_plan_it` for development; avoid mounting empty `./data/apps`.
- After bringing up services, apply app changes with `bench --site <site> migrate` and `bench --site <site> clear-cache`.
- Use `master_plan_it.devtools.bootstrap.run` for tenant/bootstrap steps as needed.
