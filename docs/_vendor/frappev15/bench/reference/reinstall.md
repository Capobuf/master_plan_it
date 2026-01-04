## Usage

```
bench reinstall [OPTIONS]
```

## Description

Reinstall a site with the current apps. This will wipe all site data and start
afresh. This is considered a destructive operation, hence, contains an
interactive confirmation prompt by default.

> Note: This feature only exists for **MariaDB** sites currently. In the future,
> they may be extended for **PostgreSQL** support as well.

## Options

* `--admin-password` Administrator Password for reinstalled site
* `--mariadb-root-username` Root username for MariaDB
* `--mariadb-root-password` Root password for MariaDB

## Flags

* `--yes` Skip confirmation for reinstall

## Examples

1. Reinstall a site skipping the prompts for:
2. Reinstall a site using an alternative user with *DBMS SUPER* privileges.

   ```
   bench reinstall
   --mariadb-root-username {db-user}
   --mariadb-root-password {db-pass}
   ```
