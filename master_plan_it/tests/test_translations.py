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
        "Plafonds": "Plafond",
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
        "Renewal - {0}": "Rinnovo - {0}",
        "Automatic renewal": "Rinnovo automatico",
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
        "Select the plafond consumed by this expense. Plafonds from the same year are valid, even if they belong to another cost center.": (
            "Seleziona il plafond consumato da questa spesa. Sono validi i plafond dello stesso anno, anche se appartengono a un altro centro di costo."
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
            "Abilita solo per spese effettive non pianificate o fuori ambito. Le spese extra non consumano plafond."
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
        "Select the year when this deferred project must return to proposal stage.": (
            "Seleziona l’anno in cui questo progetto rimandato deve tornare allo stato proposto."
        ),
        "System-calculated status based on contract terms.": (
            "Stato calcolato dal sistema in base alle righe del contratto."
        ),
        "Contract terms are mandatory because contract status and generated expenses are calculated from them.": (
            "Le righe del contratto sono obbligatorie perché lo stato del contratto e le spese generate vengono calcolati da esse."
        ),
        "Automatically renewed contract term.": (
            "Riga contratto rinnovata automaticamente."
        ),
        "Source term used to generate this automatic renewal.": (
            "Riga di origine usata per generare questo rinnovo automatico."
        ),
        "Renewal - {0}": "Rinnovo - {0}",
        "Automatic renewal": "Rinnovo automatico",
        "Row vendor is the official vendor used by reports and budget calculations.": (
            "Il fornitore della riga è il fornitore ufficiale usato dai report e dai calcoli di budget."
        ),
        "System lifecycle used for row replacement and generated-row synchronization. It is read-only and is not used to exclude an Expense; delete the Expense instead.": (
            "Lifecycle tecnico usato per la sostituzione righe e la sincronizzazione delle righe generate. È di sola lettura e non serve a escludere una Spesa; eliminare invece la Spesa."
        ),
        "Amounts / VAT": "Importi e IVA",
        "Calculated Dates": "Date calcolate",
        "Project Stage": "Fase progetto",
        "Linked Sources": "Fonti collegate",
        "Financial Summary": "Riepilogo economico",
        "Replacement target. The replaced row will be marked as Replaced automatically.": (
            "Riga sostituita. La riga indicata verrà marcata automaticamente come Sostituita."
        ),
        "Optional contract related to this expense. The expense keeps its own cost center; the contract is a logical reference.": (
            "Contratto opzionale collegato a questa spesa. La spesa mantiene il proprio centro di costo; il contratto è un riferimento logico."
        ),
        "Projects are decision contexts; they do not create independent budget totals.": (
            "I progetti sono contesti decisionali; non creano totali di budget indipendenti."
        ),
        "Official budget totals are calculated from active expense rows only.": (
            "I totali ufficiali del budget sono calcolati solo dalle righe spesa attive."
        ),
        "At least one Contract Term is required.": "È richiesto almeno un termine contrattuale.",
        "Required when the project stage is Deferred.": (
            "Obbligatorio quando lo stato del progetto è Rimandato."
        ),
        "Vendor is required on ordinary expense rows (row #{0}).": (
            "Il fornitore è obbligatorio sulle righe di spesa ordinaria (riga #{0})."
        ),
        "Actual rows cannot be replaced.": "Le righe effettive non possono essere sostituite.",
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
        "Select the year when this deferred project must return to proposal stage.",
        "System-calculated status based on contract terms.",
        "Contract terms are mandatory because contract status and generated expenses are calculated from them.",
        "Automatically renewed contract term.",
        "Source term used to generate this automatic renewal.",
        "Renewal - {0}",
        "Automatic renewal",
        "Row vendor is the official vendor used by reports and budget calculations.",
        "System lifecycle used for row replacement and generated-row synchronization. It is read-only and is not used to exclude an Expense; delete the Expense instead.",
        "Amounts / VAT",
        "Calculated Dates",
        "Project Stage",
        "Linked Sources",
        "Financial Summary",
        "Replacement target. The replaced row will be marked as Replaced automatically.",
        "Optional contract related to this expense. The expense keeps its own cost center; the contract is a logical reference.",
        "Projects are decision contexts; they do not create independent budget totals.",
        "Official budget totals are calculated from active expense rows only.",
        "At least one Contract Term is required.",
        "Required when the project stage is Deferred.",
        "Vendor is required on ordinary expense rows (row #{0}).",
        "Actual rows cannot be replaced.",
        "Replacement cycle detected on row {0}.",
        "Select the plafond consumed by this expense. Plafonds from the same year are valid, even if they belong to another cost center.",
    }

    sources = _load_po_translations(_main_pot_path())
    missing = sorted(msgid for msgid in required_sources if msgid not in sources)
    assert not missing, f"Missing msgid entries in main.pot: {missing}"


def test_minimum_budget_refactor_i18n_targets_present_in_pot_and_it_po():
    expected = {
        "Enable only for unplanned or out-of-scope actual expenses. Extra expenses do not consume plafond.": (
            "Abilita solo per spese effettive non pianificate o fuori ambito. Le spese extra non consumano plafond."
        ),
        "Official budget totals are calculated from active expense rows only.": (
            "I totali ufficiali del budget sono calcolati solo dalle righe spesa attive."
        ),
        "Optional contract related to this expense. The expense keeps its own cost center; the contract is a logical reference.": (
            "Contratto opzionale collegato a questa spesa. La spesa mantiene il proprio centro di costo; il contratto è un riferimento logico."
        ),
        "Row vendor is the official vendor used by reports and budget calculations.": (
            "Il fornitore della riga è il fornitore ufficiale usato dai report e dai calcoli di budget."
        ),
        "Select the year when this deferred project must return to proposal stage.": (
            "Seleziona l’anno in cui questo progetto rimandato deve tornare allo stato proposto."
        ),
        "Select the plafond consumed by this expense. Plafonds from the same year are valid, even if they belong to another cost center.": (
            "Seleziona il plafond consumato da questa spesa. Sono validi i plafond dello stesso anno, anche se appartengono a un altro centro di costo."
        ),
    }

    pot_sources = _load_po_translations(_main_pot_path())
    pot_missing = sorted(msgid for msgid in expected if msgid not in pot_sources)
    assert not pot_missing, f"Missing msgid entries in main.pot: {pot_missing}"

    it_translations = _load_po_translations(_it_po_path())
    it_missing = sorted(msgid for msgid in expected if msgid not in it_translations)
    assert not it_missing, f"Missing msgid entries in it.po: {it_missing}"

    mismatched = {
        msgid: (it_translations.get(msgid), msgstr)
        for msgid, msgstr in expected.items()
        if it_translations.get(msgid) != msgstr
    }
    assert not mismatched, f"Mismatched translations in it.po: {mismatched}"
