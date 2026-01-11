import json
from pathlib import Path


def test_no_forbidden_metadata_paths():
    repo_root = Path(__file__).resolve().parents[2]
    forbidden = [
        repo_root / "master_plan_it/doctype",
        repo_root / "master_plan_it/report",
        repo_root / "master_plan_it/workflow",
        repo_root / "master_plan_it/workspace",
        repo_root / "master_plan_it/dashboard",
        repo_root / "master_plan_it/dashboard_chart",
        repo_root / "master_plan_it/number_card",
        repo_root / "master_plan_it/master_plan_it_dashboard",
        repo_root / "master_plan_it/print_format",
    ]

    for path in forbidden:
        assert not path.exists(), f"Forbidden metadata path exists: {path}"


def test_dashboards_are_only_in_canonical_path():
    repo_root = Path(__file__).resolve().parents[2]
    app_root = repo_root
    canonical_dashboard_dir = repo_root / "master_plan_it/master_plan_it/dashboard"

    dashboard_files = []
    for json_path in app_root.rglob("*.json"):
        try:
            payload = json.loads(json_path.read_text())
        except Exception:
            continue

        if payload.get("doctype") == "Dashboard":
            dashboard_files.append(json_path)
            assert canonical_dashboard_dir in json_path.parents, f"Dashboard JSON outside canonical path: {json_path}"

    assert dashboard_files, "Expected at least one Dashboard JSON in canonical path"
