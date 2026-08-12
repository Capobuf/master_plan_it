# Spec Kit attivi

Gli Spec Kit storici del replatform `001`–`007` sono ritirati.

Questa directory contiene gli Spec Kit attivi, le Feature implementate mantenute temporaneamente
come baseline e il programma target 022. Le regole sostituite da 022 sono deprecate soltanto come
target: il codice corrente resta autorevole fino alla consegna delle relative Slice Verticali.

| ID | Feature | Stato corrente |
|---|---|---|
| 008 | Platform operations | Specificata, da pianificare |
| 009 | Operational revisions | Implementata e verificata; in attesa di review Product Owner |
| 010 | Projects | Implementata nella baseline corrente; regole economiche sostituite da 022 deprecate come target |
| 011 | Private attachments | Implementata e verificata; in attesa di review Product Owner |
| 012 | Budget versions | Specificata, da pianificare |
| 013 | Scenarios | Specificata; richiede clarify |
| 014 | Exports and print | Specificata, da pianificare |
| 015 | Migration and portability | Specificata, da pianificare |
| 016 | Backup and operations | Specificata; dipende da evidenze hosting |
| 017 | Budget annuale, approvazioni e ciclo Spese | Implementata e verificata |
| 018 | Dashboard UX | Implementata nella baseline corrente; copy e regole sostituite da 022 deprecate come target |
| 019 | Reporting analytics | Implementata nella baseline corrente; copy e regole sostituite da 022 deprecate come target |
| 020 | Expense workspace UX | Implementata e verificata; in attesa di review Product Owner |
| 021 | Workspace Impostazioni e IVA predefinita del Tenant | Implementata e verificata; in attesa di review Product Owner |
| 022 | Application workspace UX e programma di riallineamento economico | `PROPOSED TARGET`; design di programma, non implementato; nessun `tasks.md` |
| 023 | Workspace annuale e Spesa autorevole | Implementata e verificata; fondazione del programma 022 |

## Regola

Una directory resta qui solo finché rappresenta lavoro ancora aperto. A feature completata:

- il codice e i test diventano autorità;
- il contract API feature-local viene aggiornato e, quando presente, anche l'OpenAPI globale;
- eventuali regole durevoli entrano nei documenti minimi;
- `docs/STATUS.md` viene aggiornato;
- gli artefatti della feature vengono rimossi.

Le directory 009, 010, 011, 017, 018, 019, 020, 021 e 023 costituiscono baseline corrente o eccezioni
temporanee. Il programma 022 non le riscrive retroattivamente: una futura Slice trasferisce nel
codice soltanto il proprio delta approvato e aggiorna lo stato dopo verifica.
