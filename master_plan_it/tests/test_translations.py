# -*- coding: utf-8 -*-
"""Tests for required Italian translations in locale/it.po."""

from __future__ import annotations

import ast
from pathlib import Path


def _po_unquote(value: str) -> str:
    return ast.literal_eval(value)


def _load_po_translations(po_path: Path) -> dict[str, str]:
    translations: dict[str, str] = {}
    current_msgid: list[str] = []
    current_msgstr: list[str] = []
    mode: str | None = None

    def flush() -> None:
        msgid = "".join(current_msgid)
        msgstr = "".join(current_msgstr)
        if msgid:
            translations[msgid] = msgstr

    for raw_line in po_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()

        if line.startswith("#"):
            continue

        if line.startswith("msgid "):
            if current_msgid or current_msgstr:
                flush()
            current_msgid = [_po_unquote(line[5:].strip())]
            current_msgstr = []
            mode = "msgid"
            continue

        if line.startswith("msgstr "):
            current_msgstr = [_po_unquote(line[6:].strip())]
            mode = "msgstr"
            continue

        if line.startswith('"'):
            if mode == "msgid":
                current_msgid.append(_po_unquote(line))
            elif mode == "msgstr":
                current_msgstr.append(_po_unquote(line))
            continue

        if not line:
            if current_msgid or current_msgstr:
                flush()
            current_msgid = []
            current_msgstr = []
            mode = None

    if current_msgid or current_msgstr:
        flush()

    return translations


def _it_po_path() -> Path:
    return Path(__file__).resolve().parents[1] / "locale" / "it.po"


def _main_pot_path() -> Path:
    return Path(__file__).resolve().parents[1] / "locale" / "main.pot"


def test_workspace_translation_targets_present():
    expected = {
        "Home": "Home",
        "Management": "Gestione",
        "Operations": "Operazioni",
        "Analysis": "Analisi",
        "Overview": "Panoramica",
        "Monthly Plan": "Piano mensile",
        "Expenses Report": "Report spese",
        "Project Forecast vs Actual": "Forecast vs effettivo per progetto",
        "Renewals Window": "Finestra rinnovi",
        "System": "Sistema",
        "Settings": "Impostazioni",
        "Expenses": "Spese",
        "Plafonds": "Plafond",
        "Contracts": "Contratti",
        "Projects": "Progetti",
        "Vendors": "Fornitori",
        "Cost Centers": "Centri di costo",
        "Years": "Anni",
        "Quick Actions": "Azioni rapide",
        "Navigation": "Navigazione",
        "Forecast vs Actual by Cost Center": "Forecast vs effettivo per centro di costo",
        "Monthly Forecast vs Actual": "Forecast mensile vs effettivo",
        "Plafond Usage by Cost Center": "Utilizzo plafond per centro di costo",
        "Recent Expenses": "Spese recenti",
        "Recent Contracts": "Contratti recenti",
        "Recent Projects": "Progetti recenti",
        "New Expense": "Nuova spesa",
        "New Plafond": "Nuovo plafond",
        "New Contract": "Nuovo contratto",
        "New Project": "Nuovo progetto",
        "Forecast Total": "Totale forecast",
        "Actual Total": "Totale effettivo",
        "Active Plafonds": "Plafond attivi",
        "Remaining Plafond": "Plafond residuo",
    }

    translations = _load_po_translations(_it_po_path())

    missing = [key for key in expected if key not in translations]
    assert not missing, f"Missing msgid entries in it.po: {missing}"

    mismatched = {
        key: (translations[key], value)
        for key, value in expected.items()
        if translations.get(key) != value
    }
    assert not mismatched, f"Mismatched translations in it.po: {mismatched}"


def test_overview_and_contract_actualization_translation_targets_present():
    expected = {
        "Visualization": "Visualizzazione",
        "Layout": "Layout",
        "Build-up": "Composizione",
        "Lines": "Righe",
        "Section Scope": "Ambito sezione",
        "All": "Tutto",
        "Expense Phase": "Fase spesa",
        "Show Zero Rows": "Mostra righe a zero",
        "Forecast Estimate": "Forecast stima",
        "Forecast Quote": "Forecast preventivo",
        "Approved Budget": "Budget approvato",
        "Proposals": "Proposte",
        "Ideas": "Idee",
        "Over": "Sforamento",
        "Source Type": "Tipo origine",
        "Source Document": "Documento origine",
        "Source Row": "Riga origine",
        "Period Start": "Inizio periodo",
        "Period End": "Fine periodo",
        "Annual Contribution": "Contributo annuale",
        "Total Lines": "Totale righe",
        "Annual Total": "Totale annuale",
        "Contract expenses are synchronized automatically after saving.": (
            "Le spese contratto vengono sincronizzate automaticamente dopo il salvataggio."
        ),
        "Actual current year: not created": "Effettivo anno corrente: non creato",
        "Actual current year: complete": "Effettivo anno corrente: completo",
    }

    translations = _load_po_translations(_it_po_path())

    missing = [key for key in expected if key not in translations]
    assert not missing, f"Missing msgid entries in it.po: {missing}"

    mismatched = {
        key: (translations[key], value)
        for key, value in expected.items()
        if translations.get(key) != value
    }
    assert not mismatched, f"Mismatched translations in it.po: {mismatched}"


def test_contract_fallback_translation_targets_absent():
    translations = _load_po_translations(_it_po_path())

    forbidden = {
        "Header " + "Fallback (No Terms)",
        "Intestazione " + "fallback (senza " + "termini)",
        "Contract " + "Header",
    }
    present = [key for key in forbidden if key in translations or key in translations.values()]
    assert not present, f"Obsolete contract fallback translations remain in it.po: {present}"


def test_plafond_cross_cost_center_translation_targets_present():
    expected = {
        "Select the plafond consumed by this expense. Only non-cancelled plafonds from the same year are valid.": (
            "Seleziona il plafond consumato da questa spesa. Sono validi solo plafond non annullati dello stesso anno."
        ),
        "Plafond Consumed": "Plafond consumato",
        "Plafond Consumed: {0}": "Plafond consumato: {0}",
        "Consumed": "Consumato",
        "Ordinary expense cannot be both On Plafond and Extra.": (
            "La spesa ordinaria non può essere contemporaneamente su plafond ed extra."
        ),
        "Plafond reference must be empty unless On Plafond is enabled.": (
            "Il plafond di riferimento deve essere vuoto se Su plafond non è abilitato."
        ),
        "Enable only when this ordinary expense consumes a selected plafond. The plafond may belong to another cost center but must be in the same year.": (
            "Abilita solo quando questa spesa ordinaria consuma un plafond selezionato. Il plafond può appartenere a un altro centro di costo, ma deve essere dello stesso anno."
        ),
        "Enable only for unplanned or out-of-scope actual expenses. Extra expenses do not consume plafond.": (
            "Abilita solo per spese effettive non pianificate o fuori perimetro. Le spese extra non consumano plafond."
        ),
        "Actual Standard": "Effettivo ordinario",
        "Actual Standard: {0}": "Effettivo ordinario: {0}",
        "Actual / Standard": "Effettivo / Ordinario",
        "Standard": "Ordinaria",
    }

    translations = _load_po_translations(_it_po_path())

    missing = [key for key in expected if key not in translations]
    assert not missing, f"Missing msgid entries in it.po: {missing}"

    mismatched = {
        key: (translations[key], value)
        for key, value in expected.items()
        if translations.get(key) != value
    }
    assert not mismatched, f"Mismatched translations in it.po: {mismatched}"


def test_help_text_and_rule_translation_targets_present():
    expected = {
        "Controls whether linked expenses are included in the operating budget, shown only as planning, deferred, or excluded.": (
            "Determina se le spese collegate sono incluse nel budget operativo, mostrate solo come pianificazione, rimandate o escluse."
        ),
        "Required for deferred projects. When this year becomes current, the scheduler moves the project back to Proposed.": (
            "Obbligatorio per i progetti rimandati. Quando questo anno diventa corrente, lo scheduler riporta il progetto a Proposto."
        ),
        "System-calculated status based on contract terms. Active means at least one term is still open or current; Concluded means all terms ended before today.": (
            "Stato calcolato dal sistema in base ai termini contrattuali. Active significa che almeno un termine è ancora aperto o corrente; Concluded significa che tutti i termini sono terminati prima di oggi."
        ),
        "Contract economic periods. At least one row is required. These rows generate closed Actual expenses for existing MPIT Years.": (
            "Periodi economici del contratto. È richiesta almeno una riga. Queste righe generano spese Actual chiuse per gli MPIT Year esistenti."
        ),
        "Technical flag set automatically for auto-renew generated rows. Generated rows are never used as renewal source terms.": (
            "Flag tecnico impostato automaticamente per le righe generate dal rinnovo automatico. Le righe generate non sono mai usate come termini sorgente di rinnovo."
        ),
        "Reference to the source term that generated this row during auto-renew. Used to keep renewal idempotent and traceable.": (
            "Riferimento al termine sorgente che ha generato questa riga durante il rinnovo automatico. Usato per mantenere il rinnovo idempotente e tracciabile."
        ),
        "Official vendor for this expense row. Reports and vendor filters use this field, not the parent expense vendor.": (
            "Fornitore ufficiale per questa riga spesa. Report e filtri fornitore usano questo campo, non il fornitore della spesa padre."
        ),
        "Active rows contribute to totals. Replaced and Cancelled rows remain visible for audit but are excluded from totals.": (
            "Le righe Active contribuiscono ai totali. Le righe Replaced e Cancelled restano visibili per audit ma sono escluse dai totali."
        ),
        "Select an Estimate or Quote row from the same expense to supersede. The selected row will be marked as Replaced automatically.": (
            "Seleziona una riga Estimate o Quote della stessa spesa da sostituire. La riga selezionata sarà marcata automaticamente come Replaced."
        ),
        "Optional contract context. Contract-generated expenses are synchronized automatically and are counted only through expense rows.": (
            "Contesto contratto opzionale. Le spese generate da contratto sono sincronizzate automaticamente e vengono conteggiate solo tramite le righe spesa."
        ),
        "Official budget totals are calculated from active MPIT Expense rows only. Contract totals are not added independently.": (
            "I totali ufficiali di budget sono calcolati solo dalle righe attive di MPIT Expense. I totali contrattuali non vengono aggiunti in modo indipendente."
        ),
        "At least one Contract Term is required.": "È richiesto almeno un termine contrattuale.",
        "Defer To Year is required when Project Stage is Deferred.": (
            "Rimanda all’anno è obbligatorio quando lo stato progetto è Rimandato."
        ),
        "Vendor is required on ordinary expense rows (row #{0}).": (
            "Il fornitore è obbligatorio sulle righe di spesa ordinaria (riga #{0})."
        ),
        "Row #{0} cannot replace an Actual row.": "La riga #{0} non può sostituire una riga Actual.",
        "Replacement cycle detected on row {0}.": "Rilevato ciclo di sostituzione sulla riga {0}.",
    }

    translations = _load_po_translations(_it_po_path())

    missing = [key for key in expected if key not in translations]
    assert not missing, f"Missing msgid entries in it.po: {missing}"

    mismatched = {
        key: (translations[key], value)
        for key, value in expected.items()
        if translations.get(key) != value
    }
    assert not mismatched, f"Mismatched translations in it.po: {mismatched}"


def test_main_pot_contains_help_text_source_strings():
    required_sources = {
        "Controls whether linked expenses are included in the operating budget, shown only as planning, deferred, or excluded.",
        "Required for deferred projects. When this year becomes current, the scheduler moves the project back to Proposed.",
        "System-calculated status based on contract terms. Active means at least one term is still open or current; Concluded means all terms ended before today.",
        "Contract economic periods. At least one row is required. These rows generate closed Actual expenses for existing MPIT Years.",
        "Technical flag set automatically for auto-renew generated rows. Generated rows are never used as renewal source terms.",
        "Reference to the source term that generated this row during auto-renew. Used to keep renewal idempotent and traceable.",
        "Official vendor for this expense row. Reports and vendor filters use this field, not the parent expense vendor.",
        "Active rows contribute to totals. Replaced and Cancelled rows remain visible for audit but are excluded from totals.",
        "Select an Estimate or Quote row from the same expense to supersede. The selected row will be marked as Replaced automatically.",
        "Optional contract context. Contract-generated expenses are synchronized automatically and are counted only through expense rows.",
        "Official budget totals are calculated from active MPIT Expense rows only. Contract totals are not added independently.",
        "At least one Contract Term is required.",
        "Defer To Year is required when Project Stage is Deferred.",
        "Vendor is required on ordinary expense rows (row #{0}).",
        "Row #{0} cannot replace an Actual row.",
        "Replacement cycle detected on row {0}.",
    }

    sources = _load_po_translations(_main_pot_path())
    missing = sorted(msgid for msgid in required_sources if msgid not in sources)
    assert not missing, f"Missing msgid entries in main.pot: {missing}"
