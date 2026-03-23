import json
import re
from pathlib import Path


def test_no_forbidden_metadata_paths():
    repo_root = Path(__file__).resolve().parents[2]
    forbidden = [
        repo_root / "master_plan_it/doctype",
        repo_root / "master_plan_it/report",
        repo_root / "master_plan_it/workflow",
        repo_root / "master_plan_it/workspace",
        repo_root / "master_plan_it/workspace_sidebar",
        repo_root / "master_plan_it/dashboard",
        repo_root / "master_plan_it/dashboard_chart",
        repo_root / "master_plan_it/number_card",
        repo_root / "master_plan_it/master_plan_it_dashboard",
        repo_root / "master_plan_it/print_format",
        repo_root / "master_plan_it/notification",
    ]

    for path in forbidden:
        assert not path.exists(), f"Forbidden metadata path exists: {path}"


def test_workspace_sidebar_in_canonical_path():
    """Workspace Sidebar JSON must be at {module}/workspace_sidebar/{name}/{name}.json.

    A flat file at workspace_sidebar/{name}.json is NOT picked up by frappe.reload_doc
    and will not be loaded during bench migrate.
    """
    repo_root = Path(__file__).resolve().parents[2]
    ws_sidebar_root = repo_root / "master_plan_it/master_plan_it/workspace_sidebar"

    flat_json = list(ws_sidebar_root.glob("*.json"))
    assert not flat_json, (
        "Workspace Sidebar JSON files must not be at workspace_sidebar/*.json (flat). "
        "Move each to workspace_sidebar/{name}/{name}.json: "
        + ", ".join(str(f) for f in flat_json)
    )

    # At least one valid nested file must exist
    nested_json = list(ws_sidebar_root.glob("*/*.json"))
    assert nested_json, "Expected at least one Workspace Sidebar JSON at workspace_sidebar/{name}/{name}.json"


def test_number_card_dirs_have_init():
    """Every number_card subdirectory must have an __init__.py.

    Missing __init__.py creates an inconsistency in the Python package structure
    relative to all other number_card subdirectories in this app.
    """
    repo_root = Path(__file__).resolve().parents[2]
    nc_root = repo_root / "master_plan_it/master_plan_it/number_card"
    violations = []

    for entry in nc_root.iterdir():
        if not entry.is_dir() or entry.name.startswith("_"):
            continue
        if not (entry / "__init__.py").exists():
            violations.append(str(entry.relative_to(repo_root)))

    assert not violations, (
        "number_card subdirectories missing __init__.py:\n" + "\n".join(violations)
    )


def test_dashboards_are_only_in_canonical_path():
    repo_root = Path(__file__).resolve().parents[2]
    app_root = repo_root
    canonical_dashboard_dir = repo_root / "master_plan_it/master_plan_it/dashboard"

    dashboard_files = []
    for json_path in app_root.rglob("*.json"):
        if "node_modules" in json_path.parts or "__pycache__" in json_path.parts:
            continue
        try:
            payload = json.loads(json_path.read_text())
        except Exception:
            continue

        # Frappe fixture files can be a list of records or a single dict
        records = payload if isinstance(payload, list) else [payload]
        is_dashboard = any(
            isinstance(r, dict) and r.get("doctype") == "Dashboard"
            for r in records
        )
        if is_dashboard:
            dashboard_files.append(json_path)
            assert canonical_dashboard_dir in json_path.parents, (
                f"Dashboard JSON outside canonical path: {json_path}"
            )

    assert dashboard_files, "Expected at least one Dashboard JSON in canonical path"


def test_no_legacy_five_element_filter_tuples():
    """Number Card and Dashboard Chart filters must use 4-element tuples.

    Frappe v16 dropped support for the legacy 5th boolean element in filter
    arrays (the 'and_not' flag from older Frappe versions). Any remaining
    5-element tuple in filters_json or dynamic_filters_json will silently
    misbehave or raise errors at runtime.
    """
    repo_root = Path(__file__).resolve().parents[2]
    metadata_root = repo_root / "master_plan_it/master_plan_it"
    violations = []

    for json_path in metadata_root.rglob("*.json"):
        if "node_modules" in json_path.parts or "__pycache__" in json_path.parts:
            continue
        try:
            payload = json.loads(json_path.read_text())
        except Exception:
            continue

        if not isinstance(payload, dict):
            continue

        doctype = payload.get("doctype", "")
        if doctype not in ("Number Card", "Dashboard Chart"):
            continue

        for key in ("filters_json", "dynamic_filters_json"):
            raw = payload.get(key)
            if not raw:
                continue
            try:
                filters = json.loads(raw)
            except Exception:
                continue
            if not isinstance(filters, list):
                continue
            for entry in filters:
                if isinstance(entry, list) and len(entry) == 5:
                    violations.append(
                        f"{json_path.relative_to(repo_root)}: field '{key}' "
                        f"has 5-element tuple {entry!r} (remove the 5th element)"
                    )

    assert not violations, "Legacy 5-element filter tuples found:\n" + "\n".join(violations)


def test_report_js_name_matches_json_name():
    """Every report JS must register under the exact name declared in the JSON.

    Frappe loads the JS for a report by matching frappe.query_reports[<name>]
    against the report's 'name' field. A mismatch means the JS is never loaded
    and filters/formatters are silently skipped.
    """
    repo_root = Path(__file__).resolve().parents[2]
    report_root = repo_root / "master_plan_it/master_plan_it/report"
    violations = []

    for report_dir in report_root.iterdir():
        if not report_dir.is_dir():
            continue

        json_files = list(report_dir.glob("*.json"))
        js_files = list(report_dir.glob("*.js"))
        if not json_files or not js_files:
            continue

        try:
            report_name = json.loads(json_files[0].read_text()).get("name", "")
        except Exception:
            continue

        js_text = js_files[0].read_text()
        # Match: frappe.query_reports["<name>"]
        match = re.search(r'frappe\.query_reports\["([^"]+)"\]', js_text)
        if not match:
            continue

        registered_name = match.group(1)
        if registered_name != report_name:
            violations.append(
                f"{js_files[0].relative_to(repo_root)}: "
                f"JS registers '{registered_name}' but JSON name is '{report_name}'"
            )

    assert not violations, "Report JS/JSON name mismatches found:\n" + "\n".join(violations)


def test_no_manual_db_commit_in_doctype_controllers():
    """frappe.db.commit() must not appear in doctype controller files.

    In Frappe v16, document hooks (on_trash, on_update, validate, etc.) execute
    inside the framework-managed transaction.  A manual commit inside any of
    these hooks breaks atomicity: it either commits partial writes before the
    full operation succeeds, or prevents a rollback after an error.

    Legitimate uses of frappe.db.commit() (migration scripts, standalone CLI
    scripts, background-job entry points) live outside the doctype/ tree.
    This check only scans controller files — one *.py per doctype — not tests,
    not hooks.py, not utility modules.
    """
    repo_root = Path(__file__).resolve().parents[2]
    doctype_root = repo_root / "master_plan_it/master_plan_it/doctype"
    violations = []

    for py_path in doctype_root.rglob("*.py"):
        if "__pycache__" in py_path.parts:
            continue
        # Skip test files — test helpers sometimes need explicit commit for isolation
        if py_path.name.startswith("test_"):
            continue

        text = py_path.read_text()
        if re.search(r'\bfrappe\.db\.commit\s*\(', text):
            violations.append(str(py_path.relative_to(repo_root)))

    assert not violations, (
        "frappe.db.commit() found in doctype controller(s) — remove it:\n"
        + "\n".join(violations)
    )


def test_no_frappe_flags_in_test_reads_in_app_source():
    """App source code must read test-mode state via frappe.in_test, not frappe.flags.in_test.

    In Frappe v16, frappe.in_test is the canonical property for reading test
    mode. Direct reads of frappe.flags.in_test are deprecated and must not
    appear in production app code (controllers, hooks, utils).
    Test files themselves are excluded from this check.
    """
    repo_root = Path(__file__).resolve().parents[2]
    app_src = repo_root / "master_plan_it/master_plan_it"
    violations = []

    for py_path in app_src.rglob("*.py"):
        if "__pycache__" in py_path.parts:
            continue
        # Skip test files — they may set custom flags but not read in_test
        if py_path.name.startswith("test_"):
            continue

        text = py_path.read_text()
        # Match reads: frappe.flags.in_test used in a boolean context (if/not/and/or)
        # Writing frappe.flags.in_test is also forbidden in non-test code
        if re.search(r'\bfrappe\.flags\.in_test\b', text):
            violations.append(str(py_path.relative_to(repo_root)))

    assert not violations, (
        "frappe.flags.in_test found in app source (use frappe.in_test instead):\n"
        + "\n".join(violations)
    )


def test_notification_fixtures_have_required_v16_fields():
    """Notification fixtures must have 'channel' and 'date_changed' set for Frappe v16.

    Frappe v16 made 'channel' mandatory on all Notification records, and replaced
    the old 'reference_date_field' key with 'date_changed' for date-based events
    ('Days Before' / 'Days After').  Fixtures missing these fields cause
    frappe.MandatoryError / ValidationError during bench migrate and are silently
    skipped, leaving stale notification rules in the database.
    """
    repo_root = Path(__file__).resolve().parents[2]
    fixture_path = repo_root / "master_plan_it/master_plan_it/fixtures/notification.json"

    if not fixture_path.exists():
        return  # No notification fixture — nothing to check.

    notifications = json.loads(fixture_path.read_text())
    violations = []

    date_based_events = {"Days Before", "Days After"}

    for n in notifications:
        name = n.get("name", "<unnamed>")
        if not n.get("channel"):
            violations.append(f"{name}: missing 'channel' (required in Frappe v16)")
        if n.get("event") in date_based_events and not n.get("date_changed"):
            violations.append(
                f"{name}: event='{n['event']}' requires 'date_changed' field name "
                f"(old 'reference_date_field' key is no longer used in Frappe v16)"
            )

    assert not violations, (
        "Notification fixture incompatibilities with Frappe v16:\n" + "\n".join(violations)
    )
