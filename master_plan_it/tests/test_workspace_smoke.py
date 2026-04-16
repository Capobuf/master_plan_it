from __future__ import annotations

import json
from pathlib import Path


def _s(*parts: str) -> str:
    return "".join(parts)


def test_workspace_has_expense_model_links_only():
    repo_root = Path(__file__).resolve().parents[1]
    workspace_json = repo_root / "master_plan_it" / "workspace" / "master_plan_it" / "master_plan_it.json"
    payload = json.loads(workspace_json.read_text())

    link_targets = {row.get("link_to") for row in payload.get("links", []) if row.get("link_to")}
    assert "MPIT Expense" in link_targets
    assert "MPIT Contract" in link_targets
    assert "MPIT Project" in link_targets
    assert "mpit-overview" in link_targets
    assert "MPIT Overview" in link_targets
    assert "MPIT Monthly Plan" in link_targets
    assert "MPIT Expenses" in link_targets
    assert "MPIT Project Forecast vs Actual" in link_targets
    assert "MPIT Renewals Window" in link_targets

    forbidden = {
        _s("MPIT ", "Bud", "get"),
        _s("MPIT ", "Bud", "get ", "Ad", "dendum"),
        _s("MPIT ", "Pla", "nned ", "Item"),
        _s("MPIT ", "Ac", "tual ", "Entry"),
        _s("MPIT ", "Bud", "get ", "Diff"),
        _s("MPIT ", "Projects ", "Pla", "nned vs Exceptions"),
        _s("MPIT ", "Bud", "get What-If"),
    }
    assert not (link_targets & forbidden)


def test_workspace_dashboard_uses_new_cards_and_charts():
    repo_root = Path(__file__).resolve().parents[1]
    dashboard_json = (
        repo_root / "master_plan_it" / "dashboard" / "master_plan_it_overview" / "master_plan_it_overview.json"
    )
    payload = json.loads(dashboard_json.read_text())
    dashboard = payload[0]

    cards = {row.get("card") for row in dashboard.get("cards", [])}
    charts = {row.get("chart") for row in dashboard.get("charts", [])}

    assert {"MPIT Forecast Total", "MPIT Actual Total", "MPIT Active Plafonds", "MPIT Remaining Plafond"} <= cards
    assert {
        "MPIT Forecast vs Actual by Cost Center",
        "MPIT Monthly Forecast vs Actual",
        "MPIT Plafond Usage by Cost Center",
    } <= charts

    forbidden_cards = {
        _s("Ac", "tual Entries (Verified)"),
        _s("Ad", "dendums (Approved)"),
        _s("Bud", "gets (Live)"),
        _s("Bud", "gets (Sna", "pshot)"),
    }
    forbidden_charts = {
        _s("MPIT Plan vs ", "Ca", "p", " vs Actual"),
        _s("MPIT ", "Bud", "get Totals"),
        _s("MPIT ", "Bud", "gets by Type"),
        _s("MPIT ", "Ca", "p", " vs Actual by Cost Center"),
        _s("MPIT ", "Pla", "nned Items ", "Coverage"),
        _s("MPIT Projects ", "Pla", "nned vs Exceptions"),
    }
    assert not (cards & forbidden_cards)
    assert not (charts & forbidden_charts)
