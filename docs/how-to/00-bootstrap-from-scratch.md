# How-to: Bootstrap from scratch

1) Create a new site in your bench and install the app (`bench --site <site> install-app master_plan_it`).
2) Ensure canonical metadata exists under `apps/master_plan_it/master_plan_it/master_plan_it/`.
3) Apply files to the site with standard commands:
   - `bench --site <site> migrate`
   - `bench --site <site> clear-cache`
4) Export any Desk customizations back into the canonical folder immediately after making them.
5) Install hooks will create MPIT Settings and MPIT Year (current + next); verify from Desk after migrate.
