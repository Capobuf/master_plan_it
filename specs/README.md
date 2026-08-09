# Spec Kit attivi

Gli Spec Kit storici del replatform `001`–`007` sono ritirati.

Questa directory contiene gli Spec Kit attivi e la Feature 017 completata in attesa di archiviazione
coordinata con il repository.

| ID | Feature | Stato iniziale |
|---|---|---|
| 008 | Platform operations | Specificata, da pianificare |
| 009 | Operational revisions | Specificata, da pianificare |
| 010 | Projects | Specificata, da pianificare |
| 011 | Expense attachments | Specificata, da pianificare |
| 012 | Budget versions | Specificata, da pianificare |
| 013 | Scenarios | Specificata; richiede clarify |
| 014 | Exports and print | Specificata, da pianificare |
| 015 | Migration and portability | Specificata, da pianificare |
| 016 | Backup and operations | Specificata; dipende da evidenze hosting |
| 017 | Budget annuale, approvazioni e ciclo Spese | Implementata e verificata |

## Regola

Una directory resta qui solo finché rappresenta lavoro ancora aperto. A feature completata:

- il codice e i test diventano autorità;
- il contract API feature-local viene aggiornato e, quando presente, anche l'OpenAPI globale;
- eventuali regole durevoli entrano nei documenti minimi;
- `docs/STATUS.md` viene aggiornato;
- gli artefatti della feature vengono rimossi.
