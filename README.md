# Master Plan IT

Baseline documentale verificata sul branch `laravel-replatform` al commit
`8f0f5660b409b562d354589d9e00012f31df8ef2` del 2026-08-09.

## Struttura

Master Plan IT è composto da:

- backend Laravel API-only;
- frontend React/TypeScript basato su TailAdmin React Free;
- MySQL come database applicativo;
- API applicative versionate sotto `/api/v1`;
- autenticazione SPA Laravel Sanctum;
- proxy same-origin dal frontend verso Laravel, che resta origine privata.

## Fonti di verità

Usare questo ordine:

1. codice, migration e test correnti per il comportamento già implementato;
2. `docs/api/openapi-v1.yaml` per il contratto API implementato;
3. `docs/ARCHITECTURE.md` per i vincoli tecnici permanenti;
4. `docs/DOMAIN.md` per le regole di prodotto già implementate e ancora rilevanti;
5. `docs/STATUS.md` per distinguere implementato, parziale e non implementato;
6. `docs/OPERATIONS.md` per i soli comandi operativi verificabili;
7. `specs/*` esclusivamente per funzionalità non ancora implementate.

Uno Spec Kit non è documentazione storica. Dopo l'implementazione accettata di una feature:

1. aggiornare codice, test e OpenAPI;
2. trasferire in `docs/DOMAIN.md` o `docs/ARCHITECTURE.md` solo le nuove regole permanenti;
3. aggiornare `docs/STATUS.md`;
4. eliminare la directory della feature completata.

Non creare registri globali di task, readiness, path ownership, remediation o report `analyze` datati.
Gli esiti delle analisi appartengono alla sessione di lavoro, alla PR o alla issue; le correzioni vanno
applicate direttamente agli artefatti sorgente della feature.

## Lettura minima

Per sviluppo ordinario:

1. `docs/ARCHITECTURE.md`;
2. `docs/DOMAIN.md`;
3. `docs/STATUS.md`;
4. lo Spec Kit della sola feature attiva;
5. i file di codice coinvolti.

Per il runtime locale e i test leggere anche `docs/OPERATIONS.md`.

## Spec Kit attivi

Vedi `specs/README.md`.

Gli Spec Kit `001`–`007` del replatform sono ritirati: hanno svolto il loro scopo e non sono più
fonti eseguibili dello stato corrente.
