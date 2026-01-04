Files created during the backup process can be encrypted using an **Auto-generated key** by checking the **Encrypt Backup** option and the data can be saved under the default or provided location.

## System Requirements

For MacOS, ensure that [gnupg](https://formulae.brew.sh/formula/gnupg) is installed in the system. Use the following command to install gnupg:

```
brew install gnupg
```

Most Linux distributions already have GnuPG installed, and the current version will likely use GnuPG 2.0 by default.

## Encrypt Backup option

1. Under Settings tab go to `System settings`.
2. Inside the `Backups` section check the `Encrypt Backup` checkbox.

The system uses an auto-generated key supplied by the **Site config**. If no such key is found, **a new key is generated**. Any Administrator can later look it from the `https://{site}/app/backups` page.

It encrypts the public and private files as well as the partial backup files.

## Backup Encryption Status

1. Encrypted backups are stored at the same location as the general backups `./sites/{site}/private/backups`.
2. Encrypted backups can be downloaded from the `https://{site}/app/backups`
3. Encrypted backups are differentiated using the `key icon`.

## Backup Encryption Key

1. To get the backup encryption key go to the `./sites/{site}/private/backups`.
2. Click on the `Get Encrpytion key` and verify your password.

Copy the key to restore the encrypted backup files.

## Restoring the Encrypted backup files

1. The `bench restore SQL_FILE_PATH` can be used to restore the files without `--backup-encryption-key` as it is automatically picked from the Site Config.
2. In case of an unsuccessful restoration due to a wrong key `--backup-encryption-key` can be used to provide the key to restore the files.
3. Usage:
