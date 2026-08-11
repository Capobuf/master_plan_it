# Research — Impostazioni generali del Tenant

## Baseline recuperato

**Decision**: recuperare il contratto della pagina Generali dalla precedente implementazione rimasta sul commit parallelo `8566923`, ma reimplementare il delta sopra il `HEAD` corrente che contiene revisioni, allegati e workspace Spese più recenti.

**Rationale**: il commit parallelo contiene le decisioni prodotto già consolidate ma non può essere integrato integralmente perché condivide un parent più vecchio e rimuoverebbe funzionalità correnti.

**Alternatives considered**: cherry-pick completo (rischio regressioni e conflitti estesi), pagina globale nel registro Tenant (non è la superficie del Tenant corrente), nuova semantica inventata (vietata).

## Storage impostazioni

**Decision**: mantenere tutti i valori nella riga `Tenant` esistente e usare il suo `lock_version`.

**Rationale**: i campi sono già persistiti e consumati dal dominio. Una tabella settings o un key/value store duplicherebbero la fonte autorevole.

**Alternatives considered**: tabella dedicata, package settings, aggiornamento tramite il Resource globale del Tenant.

## Autorizzazione

**Decision**: aggiungere `tenant-settings.view` e `tenant-settings.update` al catalogo tenant-scoped, senza assegnazione automatica a Editor/Viewer; Administrator globale riceve entrambe tramite upgrade forward-only.

**Rationale**: separa consultazione e modifica, permette delega esplicita e conserva protette quota e gestione globale Tenant.

**Alternatives considered**: riuso di `platform.settings.manage` (non delegabile), riuso di `platform.tenants.update` (target globale scelto dal client), branch sul nome ruolo.

## IVA predefinita

**Decision**: conservare i resolver backend esistenti per i nuovi figli, che usano `Tenant.default_vat_rate` quando `vat_rate` è omesso. Correggere il nuovo termine Contract che oggi invia `0.00` e preservare l'aliquota persistita quando un update omette il campo su un figlio esistente; la UI non diventa la fonte autorevole del default.

**Rationale**: Spese e Contratti calcolano già con stringhe decimali e BCMath. L'omissione consente di risolvere il valore corrente al momento del salvataggio, anche se il form era già aperto.

**Alternatives considered**: precompilare e inviare sempre il default dal client (può diventare stale), riapplicare il default corrente a figli esistenti su omissione (viola il forward-only), observer o ricalcolo massivo (distruttivo), secondo calcolatore frontend.

## Refresh del contesto client

**Decision**: dopo il salvataggio riuscito ricaricare il contesto applicativo già disponibile.

**Rationale**: nome e default aggiornati devono propagarsi alle altre superfici presentazionali senza reload manuale; i calcoli backend continuano comunque a rileggere il Tenant autorevole.

**Alternatives considered**: patch locale di singoli campi nel context (duplica la response), attendere reload/cambio Tenant (stato visibilmente stale).

## Base Budget bloccata

**Decision**: derivare il blocco dalla presenza di `ApprovalOperation` e restituire stato e codice motivo nella proiezione.

**Rationale**: coincide con l'invariante di `UpdateTenant` e non introduce stato duplicato.

**Alternatives considered**: nuova colonna boolean, controllo solo frontend.

## Navigazione

**Decision**: offrire un unico workspace `/impostazioni` con navigazione interna ordinata Generali, Utenti, Ruoli e permessi, Anni di pianificazione e Centri di costo. Le sezioni riusano pagine e abilities correnti, sono filtrate con semantica OR, l'indice sceglie la prima accessibile e un guard comune nega i deep link non autorizzati prima del mount. Gli URL flat italiani e legacy inglesi restano redirect compatibili verso le route canoniche nidificate.

**Rationale**: il feedback del Product Owner chiarisce che “Generali” non deve essere una voce autonoma ma una sezione dentro la pagina Impostazioni insieme alle altre superfici amministrative. Il resolver permission-aware evita di assumere che Generali sia sempre accessibile e mantiene backend e pagine esistenti come autorità.

**Alternatives considered**: cinque voci laterali flat (non soddisfa il risultato), redirect fisso a Generali (fallisce per utenti autorizzati soltanto ad altre sezioni), introduzione di nuove abilities tenant Users/Roles (fuori scope), rimozione dei vecchi URL (rompe bookmark e link esistenti).

## Ownership tra registro globale e Generali

**Decision**: distinguere creazione e aggiornamento. La creazione globale conserva nome, codice, valuta, lingua, fuso orario e IVA come valori bootstrap. Dopo la creazione, il registro globale può modificare esclusivamente codice, valuta e lingua; Generali è l'unica autorità di modifica per nome operativo, fuso orario, IVA predefinita, Base Budget e obbligo della motivazione di cancellazione. Il contratto backend globale viene ristretto insieme al form, non soltanto nascosto nella UI.

**Rationale**: oggi i due endpoint condividono `lock_version` ma hanno target, abilities, audit e refresh client differenti. Un modal globale aperto prima di Generali può reinviare uno snapshot obsoleto e sovrascrivere le impostazioni appena salvate. Un payload chiuso elimina la seconda autorità e rende il confine verificabile.

**Alternatives considered**: rimuovere soltanto i controlli UI (bypass API ancora possibile), mantenere nome modificabile in entrambe le superfici (ownership ambigua), eliminare i valori bootstrap dalla creazione (impossibile configurare un Tenant non ancora esistente), mantenere compatibilità silenziosa sul PUT globale (conserva il bug).

## Upgrade abilities e cache

**Decision**: l'upgrade forward-only deve creare le abilities, assegnarle all'Administrator globale esistente quando presente e invalidare esplicitamente la cache autorizzativa. La creazione successiva del ruolo tramite seeder continua a sincronizzare l'intero catalogo.

**Rationale**: una cache calda può continuare a nascondere Generali anche dopo l'inserimento raw delle permissions. Il comportamento deve essere osservabile subito dopo migrate e restare idempotente.

**Alternatives considered**: bypass frontend basato sul flag Administrator (backend resterebbe deny), assegnazione manuale post-deploy (non ripetibile), affidarsi al riavvio del processo (fragile).

## Remaining unknowns

Nessun `NEEDS CLARIFICATION` residuo.
