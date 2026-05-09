from __future__ import annotations

import json
from pathlib import Path


def test_contract_has_optional_project_link_field():
    repo_root = Path(__file__).resolve().parents[1]
    contract_json = (
        repo_root / "master_plan_it" / "doctype" / "mpit_contract" / "mpit_contract.json"
    )
    payload = json.loads(contract_json.read_text())

    project_fields = [field for field in payload.get("fields", []) if field.get("fieldname") == "project"]
    assert project_fields, "MPIT Contract must define fieldname 'project'"

    field = project_fields[0]
    assert field.get("fieldtype") == "Link"
    assert field.get("options") == "MPIT Project"
    assert not bool(field.get("reqd")), "MPIT Contract.project must be optional"


def test_project_dashboard_keeps_contract_item():
    repo_root = Path(__file__).resolve().parents[1]
    dashboard_py = (
        repo_root
        / "master_plan_it"
        / "doctype"
        / "mpit_project"
        / "mpit_project_dashboard.py"
    )
    text = dashboard_py.read_text()

    assert '"fieldname": "project"' in text
    assert '"label": _("Operations")' in text
    assert '"items": ["MPIT Expense", "MPIT Contract"]' in text
