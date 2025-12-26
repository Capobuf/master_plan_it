# Docker Compose notes

- Main file: `compose.yml` (uses `Dockerfile.frappe`).
- Set `INSTALL_APPS=master_plan_it` to install the app during bootstrap.
- Mount `./apps/master_plan_it` for development; avoid mounting empty `./data/apps`.
- After bringing up services, apply app changes with `bench --site <site> migrate` and `bench --site <site> clear-cache`.
- Install hooks create MPIT Settings and MPIT Year (current + next); verify in Desk after migrate. No additional bootstrap scripts are required for baseline data.
