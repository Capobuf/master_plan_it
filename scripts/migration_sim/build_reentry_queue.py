#!/usr/bin/env python3
from __future__ import annotations

import argparse
import csv
import json
import re
from collections import Counter, defaultdict
from dataclasses import dataclass
from pathlib import Path
from typing import Any


CSV_FILES = {
    "cost_centers": "MPIT Centro di Costo.csv",
    "vendors": "MPIT Fornitore.csv",
    "contracts": "MPIT Contratto (1).csv",
    "planned_items": "MPIT Voce Pianificata.csv",
}


DATE_RE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
PROJECT_CODE_RE = re.compile(r"^PRJ-(\d+)$")


def _read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        return [dict(row) for row in reader]


def _clean(value: str | None) -> str:
    return (value or "").strip()


def _to_float(value: str | None) -> float | None:
    raw = _clean(value)
    if not raw:
        return None
    try:
        return float(raw)
    except ValueError:
        return None


def _to_int(value: str | None) -> int | None:
    raw = _clean(value)
    if not raw:
        return None
    try:
        return int(raw)
    except ValueError:
        return None


def _detect_value_kind(value: str) -> str:
    if DATE_RE.match(value):
        return "date_iso"
    if _to_float(value) is not None:
        return "numeric"
    return "text"


def _column_profile(rows: list[dict[str, str]], column: str) -> dict[str, Any]:
    non_empty_values = [_clean(row.get(column)) for row in rows if _clean(row.get(column))]
    value_kinds = Counter(_detect_value_kind(value) for value in non_empty_values)
    samples: list[str] = []
    seen: set[str] = set()
    for value in non_empty_values:
        if value in seen:
            continue
        samples.append(value)
        seen.add(value)
        if len(samples) >= 5:
            break

    return {
        "non_empty_count": len(non_empty_values),
        "empty_count": len(rows) - len(non_empty_values),
        "unique_non_empty_count": len(set(non_empty_values)),
        "value_kind_distribution": dict(value_kinds),
        "sample_values": samples,
    }


def _extract_years_from_dates(rows: list[dict[str, str]], columns: list[str]) -> set[int]:
    years: set[int] = set()
    for row in rows:
        for column in columns:
            value = _clean(row.get(column))
            if DATE_RE.match(value):
                years.add(int(value[:4]))
    return years


@dataclass
class ContractTerm:
    source_line: int
    term_row_id: str | None
    from_date: str | None
    to_date: str | None
    amount: float | None
    vat_rate: float | None
    billing_cycle: str | None
    attachment: str | None
    notes: str | None
    amount_includes_vat: int | None


@dataclass
class ContractRecord:
    source_line: int
    source_contract_id: str
    vendor: str | None
    cost_center: str | None
    description: str | None
    status: str | None
    start_date: str | None
    end_date: str | None
    auto_renew: int | None
    next_renewal_date: str | None
    current_amount: float | None
    billing_cycle: str | None
    notes: str | None
    attachment: str | None
    terms: list[ContractTerm]


def _normalize_contracts(rows: list[dict[str, str]]) -> tuple[list[ContractRecord], list[dict[str, Any]]]:
    records: list[ContractRecord] = []
    issues: list[dict[str, Any]] = []
    current: ContractRecord | None = None

    for idx, row in enumerate(rows, start=2):
        source_contract_id = _clean(row.get("ID"))
        if source_contract_id:
            current = ContractRecord(
                source_line=idx,
                source_contract_id=source_contract_id,
                vendor=_clean(row.get("Fornitore")) or None,
                cost_center=_clean(row.get("Centro di Costo")) or None,
                description=_clean(row.get("Descrizione")) or None,
                status=_clean(row.get("Stato")) or None,
                start_date=_clean(row.get("Data inizio")) or None,
                end_date=_clean(row.get("Data fine")) or None,
                auto_renew=_to_int(row.get("Rinnovo automatico")),
                next_renewal_date=_clean(row.get("Prossima data di rinnovo")) or None,
                current_amount=_to_float(row.get("Importo")),
                billing_cycle=_clean(row.get("Ciclo di Fatturazione")) or None,
                notes=_clean(row.get("Note")) or None,
                attachment=_clean(row.get("Allegato")) or None,
                terms=[],
            )
            records.append(current)

        term_row_id = _clean(row.get("ID (Termini)"))
        has_term_payload = any(
            _clean(row.get(field))
            for field in (
                "Data Inizio (Termini)",
                "Importo (Termini)",
                "Aliquota IVA (Termini)",
                "Ciclo di Fatturazione (Termini)",
                "Data Fine (Termini)",
                "Note (Termini)",
                "Allegato (Termini)",
                "Importo include IVA (Termini)",
            )
        )

        if not has_term_payload and not term_row_id:
            continue

        if current is None:
            issues.append(
                {
                    "type": "orphan_term_row",
                    "source_line": idx,
                    "reason": "Term row without preceding contract header",
                }
            )
            continue

        current.terms.append(
            ContractTerm(
                source_line=idx,
                term_row_id=term_row_id or None,
                from_date=_clean(row.get("Data Inizio (Termini)")) or None,
                to_date=_clean(row.get("Data Fine (Termini)")) or None,
                amount=_to_float(row.get("Importo (Termini)")),
                vat_rate=_to_float(row.get("Aliquota IVA (Termini)")),
                billing_cycle=_clean(row.get("Ciclo di Fatturazione (Termini)")) or None,
                attachment=_clean(row.get("Allegato (Termini)")) or None,
                notes=_clean(row.get("Note (Termini)")) or None,
                amount_includes_vat=_to_int(row.get("Importo include IVA (Termini)")),
            )
        )

    return records, issues


def _build_source_inventory(
    csv_rows: dict[str, list[dict[str, str]]],
    contracts: list[ContractRecord],
    contract_issues: list[dict[str, Any]],
) -> dict[str, Any]:
    inventory: dict[str, Any] = {"files": {}, "relationships": {}, "totals": {}}

    for key, rows in csv_rows.items():
        columns = list(rows[0].keys()) if rows else []
        inventory["files"][key] = {
            "row_count": len(rows),
            "columns": columns,
            "column_profiles": {column: _column_profile(rows, column) for column in columns},
        }

    contract_statuses = Counter(record.status or "EMPTY" for record in contracts)
    contract_billing_cycles = Counter(record.billing_cycle or "EMPTY" for record in contracts)
    term_billing_cycles = Counter(term.billing_cycle or "EMPTY" for record in contracts for term in record.terms)

    inventory["relationships"] = {
        "contract_header_count": len(contracts),
        "contract_term_count": sum(len(record.terms) for record in contracts),
        "contract_status_distribution": dict(contract_statuses),
        "contract_billing_cycle_distribution": dict(contract_billing_cycles),
        "term_billing_cycle_distribution": dict(term_billing_cycles),
        "contract_term_parsing_issues": contract_issues,
    }

    planned_rows = csv_rows["planned_items"]
    planned_type_totals: dict[str, float] = defaultdict(float)
    planned_statuses: Counter[str] = Counter()
    planned_project_codes: set[str] = set()
    for row in planned_rows:
        item_type = _clean(row.get("Tipo")) or "EMPTY"
        amount = _to_float(row.get("Importo (Netto)"))
        if amount is None:
            amount = _to_float(row.get("Importo"))
        if amount is not None:
            planned_type_totals[item_type] += amount
        planned_statuses[_clean(row.get("Stato")) or "EMPTY"] += 1
        code = _clean(row.get("Progetto"))
        if code:
            planned_project_codes.add(code)

    contract_current_amount_total = sum(record.current_amount or 0.0 for record in contracts)
    term_amount_total = sum(term.amount or 0.0 for record in contracts for term in record.terms)

    inventory["totals"] = {
        "contracts_current_amount_total": round(contract_current_amount_total, 2),
        "contract_terms_amount_total": round(term_amount_total, 2),
        "planned_items_net_total_by_type": {key: round(value, 2) for key, value in planned_type_totals.items()},
        "planned_items_status_distribution": dict(planned_statuses),
        "planned_project_codes": sorted(planned_project_codes),
    }

    return inventory


def _build_entry_queue(
    csv_rows: dict[str, list[dict[str, str]]],
    contracts: list[ContractRecord],
) -> dict[str, Any]:
    years: set[int] = set()
    years.update(
        _extract_years_from_dates(
            csv_rows["contracts"],
            ["Data inizio", "Data fine", "Prossima data di rinnovo", "Data Inizio (Termini)", "Data Fine (Termini)"],
        )
    )
    years.update(_extract_years_from_dates(csv_rows["planned_items"], ["Data inizio", "Data fine", "Data di Spesa"]))
    years.update(
        {
            int(_clean(row.get("Anno")))
            for row in csv_rows["cost_centers"]
            if _clean(row.get("Anno")).isdigit()
        }
    )

    cost_centers_queue = []
    for idx, row in enumerate(csv_rows["cost_centers"], start=2):
        name = _clean(row.get("Nome Centro di Costo"))
        if not name:
            continue
        cost_centers_queue.append(
            {
                "source_line": idx,
                "cost_center_name": name,
                "is_group": int(_to_int(row.get("Contenitore")) or 0),
                "parent_cost_center": _clean(row.get("Centro di Costo Padre")) or None,
                "abbr": _clean(row.get("Abbreviation")) or None,
                "legacy_year": _clean(row.get("Anno")) or None,
            }
        )

    vendors_queue = []
    for idx, row in enumerate(csv_rows["vendors"], start=2):
        vendor_name = _clean(row.get("Nome fornitore"))
        if not vendor_name:
            continue
        vendors_queue.append(
            {
                "source_line": idx,
                "vendor_name": vendor_name,
                "contact_phone": _clean(row.get("Telefono di contatto")) or None,
                "contact_email": _clean(row.get("Email di contatto")) or None,
                "vat_id": _clean(row.get("Partita IVA")) or None,
                "notes": _clean(row.get("Note")) or None,
                "is_active": int(_to_int(row.get("Attivo")) or 0),
            }
        )

    contracts_queue = []
    for record in contracts:
        contracts_queue.append(
            {
                "source_line": record.source_line,
                "source_contract_id": record.source_contract_id,
                "vendor": record.vendor,
                "cost_center": record.cost_center,
                "description": record.description,
                "status": record.status,
                "start_date": record.start_date,
                "end_date": record.end_date,
                "auto_renew": record.auto_renew,
                "next_renewal_date": record.next_renewal_date,
                "current_amount": record.current_amount,
                "billing_cycle": record.billing_cycle,
                "notes": record.notes,
                "attachment": record.attachment,
                "terms": [
                    {
                        "source_line": term.source_line,
                        "term_row_id": term.term_row_id,
                        "from_date": term.from_date,
                        "to_date": term.to_date,
                        "amount": term.amount,
                        "vat_rate": term.vat_rate,
                        "billing_cycle": term.billing_cycle,
                        "attachment": term.attachment,
                        "notes": term.notes,
                        "amount_includes_vat": term.amount_includes_vat,
                    }
                    for term in record.terms
                ],
            }
        )

    planned_items_queue = []
    for idx, row in enumerate(csv_rows["planned_items"], start=2):
        project_code = _clean(row.get("Progetto")) or None
        item_id = _clean(row.get("ID")) or None
        planned_items_queue.append(
            {
                "source_line": idx,
                "source_item_id": item_id,
                "project_code": project_code,
                "description": _clean(row.get("Descrizione")) or None,
                "amount": _to_float(row.get("Importo")),
                "type": _clean(row.get("Tipo")) or None,
                "status": _clean(row.get("Stato")) or None,
                "start_date": _clean(row.get("Data inizio")) or None,
                "end_date": _clean(row.get("Data fine")) or None,
                "spend_date": _clean(row.get("Data di Spesa")) or None,
                "amount_includes_vat": _to_int(row.get("Importo include IVA")),
                "vat_rate": _to_float(row.get("Aliquota IVA")),
                "amount_net": _to_float(row.get("Importo (Netto)")),
                "amount_vat": _to_float(row.get("Importo IVA")),
                "amount_gross": _to_float(row.get("Importo (Lordo)")),
                "vendor": _clean(row.get("Fornitore")) or None,
                "quote_reference": _clean(row.get("Riferimento Preventivo")) or None,
                "attachments": _clean(row.get("Allegati")) or None,
                "is_covered": _to_int(row.get("È Coperto")),
                "covered_by_type": _clean(row.get("Tipo Coperto da")) or None,
                "covered_by": _clean(row.get("Coperto da")) or None,
                "out_of_horizon": _to_int(row.get("Fuori Orizzonte")),
            }
        )

    project_code_sequence = []
    for item in planned_items_queue:
        project_code = item.get("project_code")
        if not project_code:
            continue
        if project_code in project_code_sequence:
            continue
        project_code_sequence.append(project_code)

    parsed_project_codes = []
    for code in project_code_sequence:
        match = PROJECT_CODE_RE.match(code)
        parsed_project_codes.append(
            {
                "project_code": code,
                "project_number": int(match.group(1)) if match else None,
            }
        )

    blockers = []
    for item in planned_items_queue:
        blockers.append(
            {
                "source_line": item["source_line"],
                "source_item_id": item["source_item_id"],
                "entity": "MPIT Expense",
                "issue_type": "model_mismatch",
                "blocking_fields": ["cost_center", "funding_mode"],
                "reason": (
                    "Legacy planned item row has no explicit cost center and no explicit "
                    "funding-mode mapping (On Plafond vs Extra) required by MPIT Expense."
                ),
            }
        )

    return {
        "years_to_ensure": sorted(years),
        "cost_centers": cost_centers_queue,
        "vendors": vendors_queue,
        "contracts": contracts_queue,
        "planned_items": planned_items_queue,
        "project_codes_from_planned_items": parsed_project_codes,
        "planned_item_blockers": blockers,
    }


def _write_json(path: Path, payload: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, indent=2, ensure_ascii=True) + "\n", encoding="utf-8")


def main() -> None:
    parser = argparse.ArgumentParser(description="Build MPIT CSV source inventory and UI re-entry queue.")
    parser.add_argument(
        "--source-root",
        type=Path,
        default=Path(__file__).resolve().parents[3],
        help="Folder containing the MPIT CSV files (default: /root/masterplan).",
    )
    parser.add_argument(
        "--output-root",
        type=Path,
        default=Path(__file__).resolve().parents[2] / "artifacts" / "csv_reentry",
        help="Output folder for generated JSON artifacts.",
    )
    args = parser.parse_args()

    csv_paths = {key: args.source_root / filename for key, filename in CSV_FILES.items()}
    missing = [str(path) for path in csv_paths.values() if not path.exists()]
    if missing:
        raise SystemExit(f"Missing CSV files: {missing}")

    csv_rows = {key: _read_csv(path) for key, path in csv_paths.items()}
    contracts, contract_issues = _normalize_contracts(csv_rows["contracts"])
    source_inventory = _build_source_inventory(csv_rows, contracts, contract_issues)
    entry_queue = _build_entry_queue(csv_rows, contracts)

    _write_json(args.output_root / "source_inventory.json", source_inventory)
    _write_json(args.output_root / "entry_queue.json", entry_queue)


if __name__ == "__main__":
    main()
