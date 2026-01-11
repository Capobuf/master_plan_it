# Master Plan IT

Frappe Desk app (v15) for budgeting, contracts, and projects. Native file-first workflow; no custom JS/CSS or build pipeline.

## Quick Start

```bash
# Install on existing Frappe bench
bench get-app https://github.com/Capobuf/master_plan_it.git
bench --site <your-site> install-app master_plan_it
bench --site <your-site> migrate

# Enable scheduler (required for background jobs)
bench --site <your-site> enable-scheduler
```

> **Note:** The repository name (`Master-Plan-IT`) differs from the Python package name (`master_plan_it`). 
> If `bench get-app` fails to install the Python package automatically, run:
> ```bash
> pip install -e /path/to/frappe-bench/apps/master_plan_it
> ```

## Site Management

### Create a new site with the app

```bash
# Create site (you'll be prompted for admin password)
bench new-site mysite.example.com --mariadb-root-password <db-root-password>

# Install the app on the site
bench --site mysite.example.com install-app master_plan_it

# Run migrations
bench --site mysite.example.com migrate

# Enable scheduler for background jobs
bench --site mysite.example.com enable-scheduler

# Set as default site (optional)
bench use mysite.example.com
```

### List sites

```bash
bench --site all list-apps    # List all sites with installed apps
ls sites/                      # Quick list of site directories
```

### Delete a site

```bash
# Remove site and its database
bench drop-site mysite.example.com --mariadb-root-password <db-root-password>

# Or force removal without database cleanup
bench drop-site mysite.example.com --force
```

### Other useful commands

```bash
# Backup a site
bench --site mysite.example.com backup

# Restore a site from backup
bench --site mysite.example.com restore <backup-file.sql.gz>

# Clear cache
bench --site mysite.example.com clear-cache

# Show site config
bench --site mysite.example.com show-config

# Disable/enable maintenance mode
bench --site mysite.example.com set-maintenance-mode on
bench --site mysite.example.com set-maintenance-mode off

# Run bench console (Python shell with Frappe context)
bench --site mysite.example.com console
```

## Documentation

- [Installation Guide](docs/how-to/00-bootstrap-from-scratch.md)
- [Architecture](docs/explanation/01-architecture.md)
- [Open Issues](OPEN_ISSUES.md)
- [Changelog](CHANGELOG.md)

## Features

- **Budget management** - Live/Snapshot with workflow (Draft → Proposed → Approved)
- **Contract tracking** - Renewal management with terms and vendors
- **Project planning** - Allocations with workflow support
- **Multi-year budget engine** - Annualization and cross-year planning
- **Dashboard and reports** - 17 number cards, 9 chart sources, 6 custom reports

## Key Concepts
- App root: this repo; inside a bench the app lives at `apps/master_plan_it/`.
- Multi-tenant: 1 site = 1 client.
- Canonical metadata and controllers: `master_plan_it/master_plan_it/`.
- Apply changes: edit canonical files, then run standard Frappe commands (`bench --site <site> migrate`, `clear-cache`).
- Desk policy: treat Desk as read-only for day-to-day metadata; use it only for initial skeletons and Export Customizations for non-owned DocTypes.
- Install hooks: create MPIT Settings, MPIT Year (current + next), and seed the root Cost Center (“All Cost Centers”). Fixtures ship MPIT roles only.
- Docs: `docs/reference/06-dev-workflow.md` is the authoritative workflow; see also `docs/reference/05-fixtures-bootstrap.md`.
