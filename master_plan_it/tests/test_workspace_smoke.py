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
    assert "MPIT Economic Position" in link_targets
    assert "MPIT Year End Forecast" in link_targets
    assert "MPIT Expenses" in link_targets
    assert "MPIT Project Forecast vs Actual" in link_targets
    assert "MPIT Renewals And Commitments" in link_targets
    legacy_page = _s("mpit", "-", "overview")
    assert legacy_page not in link_targets

    legacy_page_links = [
        row
        for row in payload.get("links", [])
        if row.get("link_type") == "Page" and row.get("link_to") == legacy_page
    ]
    assert not legacy_page_links

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

    assert {"MPIT Forecast Total", "MPIT Actual Total", "MPIT Plafonds", "MPIT Remaining Plafond"} <= cards
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


def test_workspace_content_references_widget_labels():
    repo_root = Path(__file__).resolve().parents[1]
    workspace_json = repo_root / "master_plan_it" / "workspace" / "master_plan_it" / "master_plan_it.json"
    payload = json.loads(workspace_json.read_text())
    content = json.loads(payload["content"])

    chart_labels = {row.get("label") or row.get("chart_name") for row in payload.get("charts", [])}
    number_card_labels = {
        row.get("label") or row.get("number_card_name") for row in payload.get("number_cards", [])
    }

    for block in content:
        if block.get("type") == "chart":
            assert block.get("data", {}).get("chart_name") in chart_labels
        if block.get("type") == "number_card":
            assert block.get("data", {}).get("number_card_name") in number_card_labels
