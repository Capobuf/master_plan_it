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


def _load_po_fuzzy_msgids(po_path: Path) -> set[str]:
    fuzzy_msgids: set[str] = set()
    pending_fuzzy = False

    for raw_line in po_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if line.startswith("#,") and "fuzzy" in line:
            pending_fuzzy = True
            continue
        if line.startswith("msgid "):
            msgid = _po_unquote(line[5:].strip())
            if pending_fuzzy and msgid:
                fuzzy_msgids.add(msgid)
            pending_fuzzy = False
            continue
        if not line:
            pending_fuzzy = False

    return fuzzy_msgids


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
        "Actual Total": "Spesa effettiva",
        "Forecast Remaining": "Forecast residuo",
        "Year-end Forecast": "Forecast fine anno",
        "Extra Budget": "Extra budget",
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
        "Financial View": "Vista finanziaria",
        "Report View": "Vista report",
        "Build-up": "Composizione",
        "Lines": "Righe",
        "Section Scope": "Ambito sezione",
        "All": "Tutto",
        "Expense Phase": "Fase spesa",
        "Show Zero Rows": "Mostra righe a zero",
        "Forecast Estimate": "Forecast stima",
        "Forecast Quote": "Forecast preventivo",
        "Approved Budget": "Progetti approvati",
        "Proposals": "Proposte da valutare",
        "Ideas": "Idee fuori budget",
        "Actual Standard": "Spesa ordinaria",
        "Actual On Plafond": "Spesa su plafond",
        "Actual Extra": "Extra budget",
        "Remaining": "Plafond residuo",
        "Over": "Sforamento plafond",
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


def test_mpit_economic_position_pdf_business_translation_targets_present():
    expected = {
        "IT Economic Overview": "Panoramica economica IT",
        "Budget, actual spend, forecasts and plafond": "Budget, consuntivo, previsioni e plafond",
        "Generated": "Generato",
        "Generated by": "Generato da",
        "Year": "Anno",
        "Financial View": "Vista finanziaria",
        "Report View": "Vista report",
        "Applied Filters": "Filtri applicati",
        "Executive Summary": "Sintesi direzionale",
        "Budget Build-up": "Composizione budget",
        "Detailed Lines": "Dettaglio righe",
        "Actual Spend": "Spesa effettiva",
        "Forecast Budget": "Budget previsto",
        "Actual Spend with Estimates and Quotes": "Spesa effettiva con stime e preventivi",
        "Estimates": "Stime",
        "Quotes": "Preventivi",
        "Approved Projects": "Progetti approvati",
        "Proposed Projects": "Proposte da valutare",
        "Ideas Outside Budget": "Idee fuori budget",
        "Plafond": "Plafond",
        "Ordinary Actual Spend": "Spesa ordinaria",
        "Actual Spend On Plafond": "Spesa su plafond",
        "Extra Budget": "Extra budget",
        "Consumed Plafond": "Plafond consumato",
        "Remaining Plafond": "Plafond residuo",
        "Over Plafond": "Sforamento plafond",
        "Budget approval note": "Nota di approvazione budget",
        "This document summarizes the IT economic position using data recorded in Master Plan IT. The approved budget is the printed PDF version shared and approved during the meeting.": (
            "Il presente documento riepiloga la situazione economica IT sulla base dei dati registrati in Master Plan IT. Il budget approvato fa riferimento alla versione PDF stampata, condivisa e approvata in sede di riunione."
        ),
        "Financial Legend": "Legenda finanziaria",
        "Actual rows already consumed in the selected year, including contract-generated expenses.": (
            "Righe effettive già consumate nell’anno selezionato, incluse le spese generate dai contratti."
        ),
        "Estimates and quotes included by the selected financial view.": (
            "Stime e preventivi inclusi dalla vista finanziaria selezionata."
        ),
        "Forecast values linked to approved projects; this is not the official approved PDF budget.": (
            "Valori previsionali collegati a progetti approvati; non rappresentano il budget ufficiale approvato tramite PDF."
        ),
        "Forecast values linked to proposed projects and useful for meeting discussion.": (
            "Valori previsionali collegati a progetti proposti e utili per la discussione in riunione."
        ),
        "Tracked ideas that remain outside the main budget totals.": (
            "Idee tracciate che restano fuori dai totali principali del budget."
        ),
        "Available plafond after consumed plafond.": (
            "Plafond disponibile dopo il plafond consumato."
        ),
        "Amount exceeding available plafond.": "Importo che supera il plafond disponibile.",
        "Meeting approval": "Approvazione riunione",
        "Meeting notes": "Note riunione",
        "Approved by": "Approvato da",
        "Approval date": "Data approvazione",
        "Signature": "Firma",
        "Generated by": "Generato da",
        "Choose whether to show actual spend, forecast values, or actual spend enriched with estimates and quotes.": (
            "Scegli se mostrare la spesa effettiva, i valori previsionali o la spesa effettiva arricchita con stime e preventivi."
        ),
        "Select the executive summary, budget build-up, or detailed source lines.": (
            "Seleziona la sintesi direzionale, la composizione del budget o il dettaglio delle righe origine."
        ),
        "Controls how many columns are printed in the PDF.": (
            "Controlla quante colonne vengono stampate nel PDF."
        ),
        "Controls the PDF page orientation. Auto selects the safer orientation for the selected profile.": (
            "Controlla l’orientamento della pagina PDF. Auto seleziona l’orientamento più sicuro per il profilo scelto."
        ),
        "Controls PDF table spacing without changing report values.": (
            "Controlla la spaziatura delle tabelle PDF senza modificare i valori del report."
        ),
        "Centro di costo": "Centro di costo",
        "Effettivo": "Effettivo",
        "Previsto": "Previsto",
        "Approvati": "Approvati",
        "Proposte": "Proposte",
        "Idee": "Idee",
        "Consumato": "Consumato",
        "Residuo": "Residuo",
        "Sforamento": "Sforamento",
        "Spesa/Riga": "Spesa/Riga",
        "Fornitore": "Fornitore",
        "Contratto": "Contratto",
        "Progetto": "Progetto",
        "Fase": "Fase",
        "Copertura": "Copertura",
        "Periodo/Data": "Periodo/Data",
        "Contributo annuo": "Contributo annuo",
        "Importo netto": "Importo netto",
        "Righe": "Righe",
        "Centri di costo": "Centri di costo",
        "Budget previsto": "Budget previsto",
        "Spesa effettiva": "Spesa effettiva",
        "Plafond residuo": "Plafond residuo",
        "Sforamento plafond": "Sforamento plafond",
        "Totale annuale": "Totale annuale",
        "Totale importo netto": "Totale importo netto",
    }

    pot_sources = _load_po_translations(_main_pot_path())
    pot_missing = sorted(msgid for msgid in expected if msgid not in pot_sources)
    assert not pot_missing, f"Missing msgid entries in main.pot: {pot_missing}"

    translations = _load_po_translations(_it_po_path())
    missing = sorted(msgid for msgid in expected if msgid not in translations)
    assert not missing, f"Missing msgid entries in it.po: {missing}"

    mismatched = {
        msgid: (translations.get(msgid), msgstr)
        for msgid, msgstr in expected.items()
        if translations.get(msgid) != msgstr
    }
    assert not mismatched, f"Mismatched translations in it.po: {mismatched}"

    fuzzy = _load_po_fuzzy_msgids(_it_po_path())
    fuzzy_expected = sorted(msgid for msgid in expected if msgid in fuzzy)
    assert not fuzzy_expected, f"Fuzzy MPIT Economic Position PDF translations in it.po: {fuzzy_expected}"


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
        "Actual Standard": "Spesa ordinaria",
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


def test_economic_position_usage_and_filter_translation_targets_present():
    expected = {
        "Group": "Raggruppamento",
        "Confirmed Usage %": "Utilizzo confermato %",
        "Plafond Over": "Sforamento plafond",
        "Group By": "Raggruppa per",
        "Include Children": "Includi sottocentri",
        "Forecast Scope": "Ambito forecast",
        "Funding Scope": "Ambito finanziamento",
        "Actual + Approved Projects": "Effettivo + progetti approvati",
        "Actual + Proposed": "Effettivo + proposte",
        "Full Planning": "Pianificazione completa",
        "Hide Zero Rows": "Nascondi righe a zero",
        "Plafond availability is shown only when grouping by Cost Center or Funding.": (
            "La disponibilità del plafond è mostrata solo raggruppando per centro di costo o finanziamento."
        ),
        "Choose which active estimates and quotes are included in the remaining forecast.": (
            "Scegli quali stime e preventivi attivi includere nel forecast residuo."
        ),
        "Limit the report to standard, plafond-funded, or extra expenses.": (
            "Limita il report alle spese ordinarie, finanziate da plafond o extra."
        ),
    }

    pot_sources = _load_po_translations(_main_pot_path())
    translations = _load_po_translations(_it_po_path())

    pot_missing = sorted(msgid for msgid in expected if msgid not in pot_sources)
    assert not pot_missing, f"Missing msgid entries in main.pot: {pot_missing}"
    missing = sorted(msgid for msgid in expected if msgid not in translations)
    assert not missing, f"Missing msgid entries in it.po: {missing}"
    mismatched = {
        msgid: (translations.get(msgid), msgstr)
        for msgid, msgstr in expected.items()
        if translations.get(msgid) != msgstr
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
