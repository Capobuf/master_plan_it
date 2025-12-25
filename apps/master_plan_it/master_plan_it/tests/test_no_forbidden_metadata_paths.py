from pathlib import Path


def test_no_forbidden_metadata_paths():
    repo_root = Path(__file__).resolve().parents[4]
    forbidden = [
        repo_root / "apps/master_plan_it/master_plan_it/doctype",
        repo_root / "apps/master_plan_it/master_plan_it/report",
        repo_root / "apps/master_plan_it/master_plan_it/workflow",
        repo_root / "apps/master_plan_it/master_plan_it/workspace",
        repo_root / "apps/master_plan_it/master_plan_it/dashboard",
        repo_root / "apps/master_plan_it/master_plan_it/dashboard_chart",
        repo_root / "apps/master_plan_it/master_plan_it/number_card",
        repo_root / "apps/master_plan_it/master_plan_it/master_plan_it_dashboard",
        repo_root / "apps/master_plan_it/master_plan_it/print_format",
    ]

    for path in forbidden:
        assert not path.exists(), f"Forbidden metadata path exists: {path}"
