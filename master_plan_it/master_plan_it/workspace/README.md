# Workspace Master Plan IT

## Principi di information architecture

### `shortcuts` — sole azioni rapide di creazione

I `shortcuts` contengono **esclusivamente** le azioni di creazione rapida (DocType, `doc_view: New`).
Non devono contenere link a report o destinazioni analitiche.

Shortcuts attivi:
- **Nuova Spesa** → MPIT Actual Entry (New)
- **Nuovo Progetto** → MPIT Project (New)
- **Nuovo Contratto** → MPIT Contract (New)

### `links` / `cards` — navigazione strutturata

La navigazione è organizzata in 4 card tematiche visibili nella sezione "Navigazione":

| Card | Voci |
|---|---|
| Budget & Pianificazione | Budget, Addendum, Voci Pianificate, Progetti, Spese |
| Analisi & Report | Panoramica Budget, Piano Mensile, Confronto Budget, Progetti vs Eccezioni, What-If Budget |
| Contratti | Contratti, Finestra Rinnovi |
| Dati Anagrafici | Centri di Costo, Anni Fiscali, Fornitori, Impostazioni |

### Label

- Tutte le label sono in **italiano**.
- Non usare label inglesi se esiste già un equivalente italiano chiaro.
- Non duplicare lo stesso collegamento sia in `shortcuts` che in `links`.

### Nessun custom JS/CSS

Il layout è interamente definito tramite configurazione nativa Frappe:
- campo `col` nel JSON `content` per la larghezza dei blocchi
- `type: "Card Break"` nei `links` per separare le card
- `shortcuts`, `links`, `quick_lists`, `charts` nativi Workspace

## Apply

```bash
bench --site <site> migrate
bench --site <site> clear-cache
# hard refresh browser (Ctrl+F5)
```

## Verifica DB

```bash
# In bench console:
import frappe
ws = frappe.get_doc("Workspace", "Master Plan IT")
print(ws.modified)
print([s.label for s in ws.shortcuts])  # atteso: 3 voci create
```
