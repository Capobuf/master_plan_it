from pathlib import Path

import frappe


def test_translation_file_exists():
    app_path = Path(frappe.get_app_path("master_plan_it"))
    candidates = [
        app_path.parent / "translations" / "it.csv",
        app_path / "translations" / "it.csv",
        app_path.parent / "master_plan_it" / "translations" / "it.csv",
    ]

    assert any(path.exists() for path in candidates), "Expected at least one translation CSV (it.csv) in translations/"
