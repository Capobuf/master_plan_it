# Specification Quality Checklist: Programma 022

**Purpose**: verificare coerenza e completezza prima di creare le Slice Verticali
**Created**: 2026-08-12
**Feature**: [spec.md](../spec.md)

## Autorità e stato

- [x] CHK-001 Il target è marcato non implementato e distinto dalla baseline corrente.
- [x] CHK-002 Il documento di dominio è indicato come fonte di prodotto per formule e lifecycle.
- [x] CHK-003 Gli Spec Kit implementati restano baseline e le regole sostituite sono marcate
  `CONFLICT` o `DEPRECATED`, non riscritte come corrente.
- [x] CHK-004 La modalità Greenfield senza dati da preservare è confermata esplicitamente; il purge
  automatico delle Spese resta fuori perimetro.

## Decisioni economiche

- [x] CHK-005 Il prodotto è esplicitamente gestionale, non contabile, fiscale, di pagamento o di
  Project Management.
- [x] CHK-006 Anno Economico, Data della Spesa e Data di Registrazione sono separati.
- [x] CHK-007 Esiste un solo Budget annuale con Preparazione, Approvato e Chiuso.
- [x] CHK-008 Approvato e Finale includono Rettifiche senza creare contenitori alternativi.
- [x] CHK-009 Riapertura e Annullamento richiedono Nota, Revisione e conservazione dello storico.
- [x] CHK-009A L'Annullamento usa soltanto i quattro gruppi Effettivi, Extra Budget, Rettifiche e
  Chiusure; include Cestino e Chiusure riaperte, rivalida con lock nella transazione e non sblocca
  la Base Economica.
- [x] CHK-010 La matrice Rettifica/Extra/Modifica ordinaria è completa e non fiscale.
- [x] CHK-011 Il Forecast è escluso dal dominio e il Previsto Ricostruito è limitato agli anni
  storici privi di Budget originario.

## Progetti e Contratti

- [x] CHK-012 Le Spese di Progetto ereditano l'Anno del Progetto conservando la Data reale.
- [x] CHK-013 La Continuazione usa un riferimento al precedente e non chiude automaticamente
  l'origine.
- [x] CHK-014 Le viste `Solo Questo Progetto` e `Intero Percorso` evitano doppio conteggio dei
  Previsti riproposti.
- [x] CHK-015 Futuro=Preventivo, corrente/passato=Effettivo e il Preventivo resta conservato.
- [x] CHK-016 La cessazione conserva Effettivi generati, interrompe il futuro, gestisce mensili e
  Righe manuali senza pro-rata giornaliero.

## Plafond

- [x] CHK-017 Esiste al massimo un Plafond per Tenant/Anno/Centro di Costo.
- [x] CHK-018 L'allocazione usa `ExpenseRow` additive dedicate con importi positivi o negativi e
  non introduce una sorgente monetaria parallela.
- [x] CHK-019 La Riga coperta usa un riferimento singolo ed è coperta integralmente.
- [x] CHK-020 Capienza insufficiente blocca atomicamente Effettivi coperti e riduzioni invalidanti,
  restituendo Allocazione, Disponibile, Richiesto e Mancante e mantenendo gli input; non blocca
  Stime/Preventivi coperti che alimentano la sola Copertura Prevista.
- [x] CHK-021 Una riduzione invalidante è bloccata da una Vista di Impatto.
- [x] CHK-022 Allocazione, Copertura Prevista, Consumato e Disponibile non producono doppio
  conteggio né espongono misure duplicate.
- [x] CHK-023 Quote multiple, copertura parziale e Sforamento consentito non compaiono come target.
- [ ] CHK-023A Compatibilità Extra Budget/Plafond e compatibilità tra Centri di Costo confermate dal
  proprietario prima di chiudere la Slice 024.

## Cancellazione, retention e UX

- [x] CHK-024 Cancellazione e Ripristino escludono/includono il dataset corrente e producono
  Rettifica su Budget Chiuso.
- [x] CHK-025 La Source Key di una Spesa contrattuale cancellata resta soppressa.
- [x] CHK-026 La retention distingue limite operativo, `Version` tecniche e snapshot annuali.
- [x] CHK-027 Il quickstart copre il superamento del limite con storia annuale a cutoff intatta.
- [x] CHK-028 Inline editing e navigazione non aggirano validazioni, Note o autorizzazioni.
- [x] CHK-029 I criteri di successo sono misurabili e tracciabili alle acceptance.

## Prontezza

- [x] CHK-030 Spec, research, plan, data model, API e quickstart usano la stessa semantica per le
  decisioni risolte e classificano coerentemente come aperte le due compatibilità della Slice 024.
- [x] CHK-031 Il piano contiene Slice verticali end-to-end e nessun modello many-to-many Plafond.
- [x] CHK-032 Non esiste `tasks.md` per 022 e nessun artefatto avvia l'implementazione.
- [ ] CHK-033 La verifica read-only equivalente a `/speckit.analyze` non rileva conflitti
  bloccanti: da rivalidare dopo le due risposte ancora richieste per compatibilità Plafond.

## Note

I checkbox vengono marcati soltanto dopo la verifica finale della sessione. L'assenza di `tasks.md`
è intenzionale: 022 è una specifica di programma e ogni Slice avrà il proprio Spec Kit.
