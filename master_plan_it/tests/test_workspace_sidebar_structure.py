from __future__ import annotations

import json
from pathlib import Path


def _load_sidebar_items() -> list[dict]:
    sidebar_path = Path(__file__).resolve().parents[1] / "workspace_sidebar" / "master_plan_it.json"
    payload = json.loads(sidebar_path.read_text())
    return payload.get("items", [])


def _section_children(items: list[dict], section_label: str) -> list[str]:
    labels: list[str] = []
    in_section = False

    for item in items:
        if item.get("type") == "Section Break":
            in_section = item.get("label") == section_label
            continue
        if in_section and item.get("type") == "Link":
            labels.append(item.get("label"))

    return labels


def test_sidebar_top_level_order_matches_target():
    items = _load_sidebar_items()

    assert items[0].get("label") == "Home"
    assert items[0].get("type") == "Link"

    sections = [item.get("label") for item in items if item.get("type") == "Section Break"]
    assert sections == ["Management", "Analysis", "Operations", "System"]


def test_sidebar_management_and_operations_children_match_target():
    items = _load_sidebar_items()

    management_children = _section_children(items, "Management")
    operations_children = _section_children(items, "Operations")

    assert management_children == [
        "Expenses",
        "Contracts",
        "Projects",
        "Vendors",
        "Cost Centers",
        "Years",
    ]
    assert operations_children == ["New Expense", "New Contract", "New Project"]

    expense_occurrences = sum(1 for item in items if item.get("label") == "Expenses")
    assert expense_occurrences == 1
