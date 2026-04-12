from pathlib import Path

import frappe


def _get_app_package_path() -> Path:
    return Path(frappe.get_app_path("master_plan_it"))


def test_po_translation_files_exist():
    app_path = _get_app_package_path()
    pot_candidates = [
        app_path / "locale" / "main.pot",
        app_path.parent / "master_plan_it" / "locale" / "main.pot",
    ]
    po_candidates = [
        app_path / "locale" / "it.po",
        app_path.parent / "master_plan_it" / "locale" / "it.po",
    ]

    assert any(path.exists() for path in pot_candidates), "Expected locale/main.pot"
    assert any(path.exists() for path in po_candidates), "Expected locale/it.po"


def test_legacy_csv_translation_file_removed():
    app_path = _get_app_package_path()
    legacy_candidates = [
        app_path / "translations" / "it.csv",
        app_path.parent / "master_plan_it" / "translations" / "it.csv",
    ]

    assert not any(path.exists() for path in legacy_candidates), (
        "Legacy translations/it.csv should be removed after CSV->PO migration"
    )
