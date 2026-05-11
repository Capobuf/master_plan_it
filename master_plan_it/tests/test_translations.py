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
        "View Mode": "Vista",
        "Build-up": "Composizione",
        "Lines": "Righe",
        "Section Scope": "Ambito sezione",
        "All": "Tutto",
        "Expense Phase": "Fase spesa",
        "Show Zero Rows": "Mostra righe a zero",
        "Forecast Contracts": "Forecast contratti",
        "Forecast Estimate": "Forecast stima",
        "Forecast Quote": "Forecast preventivo",
        "Over": "Sforamento",
        "Source Type": "Tipo origine",
        "Source Document": "Documento origine",
        "Source Row": "Riga origine",
        "Period Start": "Inizio periodo",
        "Period End": "Fine periodo",
        "Annual Contribution": "Contributo annuale",
        "Total Lines": "Totale righe",
        "Annual Total": "Totale annuale",
        "Create Actual for Current Year": "Crea effettivo anno corrente",
        "Actual current year: not created": "Effettivo anno corrente: non creato",
        "Actual current year: partial": "Effettivo anno corrente: parziale",
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
        "Select a non-cancelled plafond for the same year. The plafond can belong to a different cost center than the expense.": (
            "Seleziona un plafond non annullato dello stesso anno. Il plafond può appartenere a un centro di costo diverso dalla spesa."
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
        "Enable only when this expense consumes a selected plafond.": (
            "Abilita solo quando questa spesa consuma un plafond selezionato."
        ),
        "Enable only for unplanned or out-of-scope expenses. Leave disabled for ordinary planned expenses.": (
            "Abilita solo per spese non previste o fuori perimetro. Lascia disabilitato per le spese ordinarie previste."
        ),
        "Leave both On Plafond and Extra disabled for ordinary planned expenses.": (
            "Lascia sia Su plafond sia Extra disabilitati per le spese ordinarie previste."
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
