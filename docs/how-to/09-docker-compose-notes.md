# Docker Compose notes

- Compose + Dockerfile live in the infra repo: `../master-plan-it-deploy/compose.yml` and `Dockerfile.frappe`.
- Set `INSTALL_APPS=master_plan_it` to install the app during bootstrap.
- Mount the app repo root (`../master-plan-it`) to `/home/frappe/frappe-bench/apps/master_plan_it`; data/config come from `../master-plan-it-deploy/{data,config}` (keep those bind mounts intact).
- After bringing up services, apply app changes with `bench --site <site> migrate` and `bench --site <site> clear-cache`.
- Install hooks create MPIT Settings, MPIT Year (current + next), and seed the root Cost Center (“All Cost Centers”); verify in Desk after migrate. No additional bootstrap scripts are required for baseline data.
- Post-migrate, the reports/charts available are:
  - MPIT Current Plan vs Exceptions
  - MPIT Baseline vs Exceptions
  - MPIT Monthly Plan vs Exceptions
  - MPIT Projects Planned vs Exceptions
  - MPIT Plan Delta by Cost Center (chart)
