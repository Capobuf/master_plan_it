# Spec Kit attivi

Gli Spec Kit storici del replatform `001`–`007` sono ritirati.

Questa directory contiene gli Spec Kit attivi e le Feature 017 e 020 completate. La Feature 020
resta disponibile fino all'accettazione del Product Owner, come richiesto dalla slice.

| ID | Feature | Stato corrente |
|---|---|---|
| 008 | Platform operations | Specificata, da pianificare |
| 009 | Operational revisions | Implementata e verificata; in attesa di review Product Owner |
| 010 | Projects | Specificata, da pianificare |
| 011 | Private attachments | Implementata e verificata; in attesa di review Product Owner |
| 012 | Budget versions | Specificata, da pianificare |
| 013 | Scenarios | Specificata; richiede clarify |
| 014 | Exports and print | Specificata, da pianificare |
| 015 | Migration and portability | Specificata, da pianificare |
| 016 | Backup and operations | Specificata; dipende da evidenze hosting |
| 017 | Budget annuale, approvazioni e ciclo Spese | Implementata e verificata |
| 020 | Expense workspace UX | Implementata e verificata; in attesa di review Product Owner |

## Regola

Una directory resta qui solo finché rappresenta lavoro ancora aperto. A feature completata:

- il codice e i test diventano autorità;
- il contract API feature-local viene aggiornato e, quando presente, anche l'OpenAPI globale;
- eventuali regole durevoli entrano nei documenti minimi;
- `docs/STATUS.md` viene aggiornato;
- gli artefatti della feature vengono rimossi.

Le directory 009 e 011 costituiscono eccezione esplicita: restano presenti fino alla review del
Product Owner anche dopo implementazione e verifica.
