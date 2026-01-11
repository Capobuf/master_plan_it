# Domande per Refactoring Workflow Nativo

> **Obiettivo**: Definire flussi nativi Frappe per Budget, Project, Contract
> **Approccio**: Tabula rasa dei flussi custom, utilizzo di Frappe Workflow DocType

---

## 🔷 A) MPIT BUDGET - Architettura Fondamentale

### A1. Budget Live vs Snapshot - Stesso DocType o separati?

Attualmente sono differenziati dal campo `budget_type`. Con Frappe Workflow nativo:

| Opzione | Descrizione | Pro | Contro |
|---------|-------------|-----|--------|
| **A** | Mantieni un solo DocType con due workflow distinti | Meno DocType | ❌ Non supportato: Frappe consente 1 solo workflow per DocType |
| **B** | Creare DocType separati (`MPIT Budget Live`, `MPIT Budget Snapshot`) | Workflow dedicati, chiara separazione | Migrazione dati, relazioni da aggiornare |
| **C** | Un solo DocType; Live non ha workflow (sempre Draft), solo Snapshot ha workflow | Meno impatto, più semplice | Live diventa "diverso" dagli altri |

**Domanda**: Quale preferisci? 

Se scegli **C**, confermi che il Live resta tecnicamente `docstatus=0` e mai submittable?

---

### A2. Snapshot - "Annullata torna editabile"

Frappe ha un pattern nativo per questo: **Amend** (Cancel + Create Copy con suffisso `-1`).

| Opzione | Descrizione |
|---------|-------------|
| **Amend** | Cancel crea una copia editabile con link `amended_from` all'originale |
| **Unlock** | Lo stesso documento viene riaperto per modifica (NON nativo, richiede patch `docstatus=0`) |

**Domanda**: Vuoi usare il pattern **Amend** (standard) o preferisci un **Unlock** custom?

> ⚠️ Nota: Il pattern Amend è lo standard Frappe per documenti che devono poter essere corretti dopo approvazione. Consente audit trail completo.

---

### A3. Snapshot - Stati intermedi

Hai descritto: Draft → Proposta al cliente → Accettata/Immutabile

**Domanda**: Servono stati aggiuntivi?

| Flusso Minimo | Flusso Esteso |
|---------------|---------------|
| Draft → Proposed → Approved | Draft → Internal Review → Proposed to Client → Approved |

E se viene rifiutata?
- Proposed → Draft (torna modificabile)?
- Proposed → Rejected (stato terminale con possibilità Amend)?

---

## 🔷 B) MPIT PROJECT

### B1. Project Submittable?

**Stato attuale**: Il progetto NON è `is_submittable`. Gli stati sono gestiti tramite campo `status`.

**Domanda**: Vuoi che il progetto diventi submittable (`is_submittable=1`)?

| Opzione | Pro | Contro |
|---------|-----|--------|
| **Sì, submittable** | Immutabilità nativa, workflow standard, audit trail | Progetti pluriennali potrebbero dover essere amended più volte |
| **No, resta non-submittable** | Flessibilità per modifiche continue | Meno controllo formale, workflow più complesso |

---

### B2. Approved con ripensamento

Se un progetto Approved deve poter tornare modificabile:

| Modello | Descrizione |
|---------|-------------|
| **Amend** | Cancel → Amended Copy (traccia storico completo) |
| **Simple Reject** | Toggle: Approved può tornare a Proposed/Draft senza cancellare |

**Domanda**: Quale modello preferisci?

---

### B3. Stati operativi vs workflow

Attualmente esistono 7 stati:
- **Fasi approvazione**: Draft, Proposed, Approved
- **Fasi operative**: In Progress, On Hold, Completed
- **Terminali**: Cancelled

**Domanda**: Come vuoi gestirli?

| Opzione | Workflow States | Campo Separato |
|---------|-----------------|----------------|
| **A** | Tutti e 7 gli stati nel workflow | - |
| **B** | Solo Draft/Proposed/Approved/Cancelled nel workflow | `operational_status` = In Progress/On Hold/Completed |

L'opzione B separa "approvazione" da "esecuzione", rendendo il workflow più semplice.

---

## 🔷 C) MPIT CONTRACT

### C1. Contratto con Workflow formale?

I contratti tipicamente hanno uno **stato operativo**, non un workflow di approvazione.

**Domanda**: Serve un workflow di approvazione?

| Opzione | Descrizione |
|---------|-------------|
| **No workflow** | Campo `status` gestito manualmente/automaticamente |
| **Sì workflow** | Draft → Review → Approved → Active |

Se NO, il campo `status` resta come oggi (Active, Pending Renewal, Renewed, Cancelled, Expired).

---

### C2. Transizioni automatiche

Per stati come "Pending Renewal", "Expired":

**Domanda**: Devono essere impostati automaticamente?

| Scenario | Trigger |
|----------|---------|
| `end_date` - 30 giorni | → Pending Renewal |
| `end_date` passata e non rinnovato | → Expired |

Se sì, implementiamo uno scheduler job. Se no, restano manuali.

---

### C3. Auto-renew alla scadenza

Se `auto_renew=True` e `end_date` è passata:

| Opzione | Comportamento |
|---------|---------------|
| **A** | Transizione automatica a "Renewed", estensione contratto |
| **B** | Va in "Pending Renewal", richiede conferma manuale |
| **C** | Rimane "Active", ignora scadenza (perpetuo finché non cancellato) |

**Domanda**: Quale comportamento?

---

## 🔷 D) RUOLI E PERMESSI

### D1. Chi può fare cosa?

Compila questa matrice per le transizioni chiave:

| Transizione | vCIO Manager | Client Editor | Client Viewer |
|-------------|:------------:|:-------------:|:-------------:|
| Draft → Proposed | ? | ? | ❌ |
| Proposed → Approved | ? | ? | ❌ |
| Approved → Cancelled | ? | ❌ | ❌ |
| Riapri (Amend) | ? | ❌ | ❌ |

---

### D2. Self-Approval

**Domanda**: Un utente che propone può anche approvare lo stesso documento?

| Opzione | Descrizione |
|---------|-------------|
| **Sì** | Self-approval consentito |
| **No** | Serve sempre persona diversa (4-eyes principle) |

Frappe Workflow supporta `Allow Self Approval` per ogni transizione.

---

## 🔷 E) NOTIFICHE

### E1. Email su transizioni?

Frappe Workflow può inviare email automatiche. Vuoi:

| Evento | Notifica a | Sì/No |
|--------|-----------|-------|
| Snapshot proposta | Cliente (Client Editor/Viewer) | ? |
| Snapshot approvata | vCIO Manager | ? |
| Snapshot rifiutata | vCIO Manager | ? |
| Progetto approvato | Team | ? |
| Contratto in scadenza | vCIO Manager | ? |

---

## 🔷 F) EDGE CASES

### F1. Budget Live modificato dopo Snapshot Approved

Se il Live cambia (nuovi contratti, progetti) mentre esiste una Snapshot Approved:

| Opzione | Comportamento |
|---------|---------------|
| **A** | Nessun effetto: Snapshot è fotografia storica, Live evolve |
| **B** | Warning: mostra alert "Live diverge da Snapshot approvato" |
| **C** | Auto-proposta: crea automaticamente nuova Snapshot in Draft |

**Domanda**: Quale comportamento?

---

### F2. Progetto Approved senza Planned Items

**Domanda**: Un progetto può essere Approved se non ha Planned Items submitted?

| Opzione | Comportamento |
|---------|---------------|
| **Sì** | Warning ma non blocca |
| **No** | Blocco: richiede almeno 1 Planned Item submitted |

---

### F3. Coverage post-approvazione

Se un Planned Item viene "coperto" (is_covered=1) dopo che il progetto è Approved:

**Domanda**: Il progetto resta Approved o deve cambiare stato?

| Opzione | Comportamento |
|---------|---------------|
| **Resta Approved** | Normale operatività, non influisce sullo stato |
| **Trigger stato** | Se tutti i PI sono coperti → "Completed" automatico |

---

## Prossimi Passi

Una volta ricevute le risposte, preparerò:

1. **Schema Workflow** per ogni DocType (stati, transizioni, ruoli)
2. **Piano di Migrazione** dai flussi custom a quelli nativi
3. **Impact Analysis** sulle logiche esistenti da rimuovere/modificare
4. **Test Checklist** per validare i nuovi flussi
