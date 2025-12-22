# -*- coding: utf-8 -*-
"""MPIT DevTools: deterministic sync (no Desk UI)

PURPOSE
- Apply MPIT specs (DocTypes + Roles + Workflows + Module Def) into a Frappe site without using the Desk UI.
- Idempotent: safe to run multiple times.
- Deterministic: specs are applied in dependency order.

ENTRYPOINT
- bench --site <site> execute master_plan_it.devtools.sync.sync_all

SPEC LOCATION
- apps/master_plan_it/spec/doctypes/*.json
- apps/master_plan_it/spec/workflows/*.json

IMPORTANT
- This sync creates/updates DocTypes using Frappe's own DocType API (DB-first).
- Optionally, it promotes DocTypes to Standard and exports them to files in the app using export_to_files.
  This is how you make the app portable via Git without manual Desk actions.
"""

from __future__ import annotations

import json
import os
from pathlib import Path
from typing import Any, Dict, List, Set

import frappe
from frappe.modules.export_file import export_to_files

from master_plan_it.devtools.preflight import validate as preflight_validate


SPEC_DIR_ENV = "MPIT_SPEC_DIR"
ROLES = ["vCIO Manager", "Client Editor", "Client Viewer"]
REPORT_SPEC_DIR = "reports"
NUMBER_CARD_SPEC_DIR = "number_cards"
DASHBOARD_CHART_SPEC_DIR = "dashboard_charts"
DASHBOARD_SPEC_DIR = "dashboards"


def _repo_spec_dir() -> Path:
    app_path = Path(frappe.get_app_path("master_plan_it"))
    return app_path / "spec"


def _spec_dir() -> Path:
    override = os.environ.get(SPEC_DIR_ENV)
    return Path(override) if override else _repo_spec_dir()


def _load_specs(spec_dir: Path) -> List[Dict[str, Any]]:
    doctypes_dir = spec_dir / "doctypes"
    out: List[Dict[str, Any]] = []
    for p in sorted(doctypes_dir.glob("*.json")):
        out.append(json.loads(p.read_text(encoding="utf-8")))
    return out


def _load_workflow_specs(spec_dir: Path) -> List[Dict[str, Any]]:
    workflows_dir = spec_dir / "workflows"
    if not workflows_dir.exists():
        return []
    out: List[Dict[str, Any]] = []
    for p in sorted(workflows_dir.glob("*.json")):
        out.append(json.loads(p.read_text(encoding="utf-8")))
    return out


def _load_specs_in_dir(spec_dir: Path, subdir: str) -> List[Dict[str, Any]]:
    path = spec_dir / subdir
    if not path.exists():
        return []
    out: List[Dict[str, Any]] = []
    for p in sorted(path.glob("*.json")):
        out.append(json.loads(p.read_text(encoding="utf-8")))
    return out


def _deps_for(d: Dict[str, Any], known: Set[str]) -> Set[str]:
    deps: Set[str] = set()
    for f in d.get("fields", []):
        if f.get("fieldtype") in ("Link", "Table"):
            opt = f.get("options")
            if opt in known and opt != d["name"]:
                deps.add(opt)
    return deps


def _toposort(specs: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    by_name = {d["name"]: d for d in specs}
    known = set(by_name.keys())
    deps = {n: _deps_for(by_name[n], known) for n in known}

    indeg = {n: len(deps[n]) for n in known}
    queue = sorted([n for n in known if indeg[n] == 0])
    ordered: List[str] = []

    while queue:
        n = queue.pop(0)
        ordered.append(n)
        for x in known:
            if n in deps[x]:
                deps[x].remove(n)
                indeg[x] -= 1
                if indeg[x] == 0:
                    queue.append(x)
                    queue.sort()

    if len(ordered) != len(known):
        raise ValueError("DocType dependency cycle detected. Resolve internal Link/Table cycles among MPIT DocTypes.")
    return [by_name[n] for n in ordered]


def _ensure_module_def(module_name: str) -> None:
    if frappe.db.exists("Module Def", module_name):
        return
    doc = frappe.get_doc({
        "doctype": "Module Def",
        "module_name": module_name,
        "app_name": "master_plan_it",
        "custom": 0,
    })
    doc.insert(ignore_permissions=True)


def _ensure_role(role_name: str) -> None:
    if frappe.db.exists("Role", role_name):
        return
    doc = frappe.get_doc({
        "doctype": "Role",
        "role_name": role_name,
        "desk_access": 1,
    })
    doc.insert(ignore_permissions=True)


def _ensure_roles() -> None:
    for role in ROLES:
        _ensure_role(role)


def _apply_doctype(spec: Dict[str, Any]) -> None:
    name = spec["name"]
    module = spec.get("module", "Master Plan IT")
    _ensure_module_def(module)

    if frappe.db.exists("DocType", name):
        dt = frappe.get_doc("DocType", name)
    else:
        dt = frappe.new_doc("DocType")
        dt.name = name
        dt.module = module
        # Create as Custom first to reduce friction; we'll promote after save.
        dt.custom = 1

    # Apply core flags
    for key in [
        "istable",
        "issingle",
        "is_submittable",
        "track_changes",
        "allow_rename",
        "allow_copy",
        "allow_import",
        "autoname",
        "title_field",
        "sort_field",
        "sort_order",
        "quick_entry",
        "editable_grid",
        "is_tree",
        "nsm_parent_field",
    ]:
        if key in spec:
            setattr(dt, key, spec[key])

    # Fields (ordered)
    if "fields" in spec:
        dt.fields = []
        for f in spec["fields"]:
            dt.append("fields", f)

    # Permissions (optional; child tables often omit)
    if "permissions" in spec and isinstance(spec["permissions"], list):
        dt.permissions = []
        for p in spec["permissions"]:
            dt.append("permissions", p)

    dt.flags.ignore_version = True
    dt.ignore_version = True
    dt.save(ignore_permissions=True)

    # Promote to standard and export to files (optional but recommended)
    if spec.get("promote_to_standard", True):
        dt.custom = 0
        dt.save(ignore_permissions=True)

        export_to_files(
            record_list=[["DocType", dt.name]],
            record_module=module,
            create_init=True,
        )


def _ensure_workflow_state(name: str, style: str | None = None) -> None:
    if frappe.db.exists("Workflow State", name):
        if style:
            doc = frappe.get_doc("Workflow State", name)
            if doc.style != style:
                doc.style = style
                doc.save(ignore_permissions=True)
        return
    doc = frappe.get_doc({"doctype": "Workflow State", "workflow_state_name": name})
    if style:
        doc.style = style
    doc.insert(ignore_permissions=True)


def _ensure_workflow_action(name: str) -> None:
    if frappe.db.exists("Workflow Action Master", name):
        return
    doc = frappe.get_doc({"doctype": "Workflow Action Master", "workflow_action_name": name})
    doc.insert(ignore_permissions=True)


def _ensure_workflow_prereqs(workflows: List[Dict[str, Any]]) -> None:
    """Ensure Workflow State and Action records exist before attaching workflows."""
    state_styles: Dict[str, str | None] = {}
    actions: Set[str] = set()

    for wf in workflows:
        for st in wf.get("states", []):
            state_styles.setdefault(st["state"], st.get("style"))
        for tr in wf.get("transitions", []):
            if tr.get("action"):
                actions.add(tr["action"])
            if tr.get("state"):
                state_styles.setdefault(tr["state"], None)
            if tr.get("next_state"):
                state_styles.setdefault(tr["next_state"], None)

    for state, style in state_styles.items():
        _ensure_workflow_state(state, style)
    for action in sorted(actions):
        _ensure_workflow_action(action)


def _apply_workflow(spec: Dict[str, Any]) -> None:
    """Create/update a Workflow definition from spec."""
    name = spec["workflow_name"]
    if frappe.db.exists("Workflow", name):
        wf = frappe.get_doc("Workflow", name)
    else:
        wf = frappe.new_doc("Workflow")
    wf.workflow_name = name

    module = spec.get("module", "Master Plan IT")
    _ensure_module_def(module)

    wf.module = module
    wf.document_type = spec["document_type"]
    wf.workflow_state_field = spec["workflow_state_field"]
    wf.is_active = spec.get("is_active", 1)
    wf.send_email_alert = spec.get("send_email_alert", 0)

    wf.states = []
    for st in spec.get("states", []):
        wf.append("states", st)

    wf.transitions = []
    for tr in spec.get("transitions", []):
        wf.append("transitions", tr)

    wf.flags.ignore_mandatory = True
    wf.save(ignore_permissions=True)

    export_to_files(
        record_list=[["Workflow", wf.name]],
        record_module=module,
        create_init=True,
    )


def _apply_report(spec: Dict[str, Any]) -> None:
    name = spec["report_name"]
    if frappe.db.exists("Report", name):
        doc = frappe.get_doc("Report", name)
    else:
        doc = frappe.new_doc("Report")
        doc.report_name = name

    module = spec.get("module", "Master Plan IT")
    _ensure_module_def(module)

    doc.module = module
    doc.ref_doctype = spec["ref_doctype"]
    doc.report_type = spec["report_type"]
    doc.is_standard = spec.get("is_standard", "Yes")
    doc.prepared_report = spec.get("prepared_report", 0)
    doc.add_total_row = spec.get("add_total_row", 1)
    doc.disable_prepared_report = spec.get("disable_prepared_report", 1)
    doc.disabled = spec.get("disabled", 0)

    doc.roles = []
    for r in spec.get("roles", []):
        doc.append("roles", r)

    doc.save(ignore_permissions=True)

    export_to_files(
        record_list=[["Report", doc.name]],
        record_module=module,
        create_init=True,
    )


def _apply_number_card(spec: Dict[str, Any]) -> None:
    name = spec.get("name") or spec["label"]
    if frappe.db.exists("Number Card", name):
        doc = frappe.get_doc("Number Card", name)
    else:
        doc = frappe.new_doc("Number Card")
        doc.name = name

    doc.label = spec["label"]
    doc.type = spec.get("type", "Report")
    doc.report_name = spec.get("report_name")
    doc.report_field = spec.get("report_field")
    doc.function = spec.get("function")
    doc.document_type = spec.get("document_type")
    doc.aggregate_function_based_on = spec.get("aggregate_function_based_on")
    doc.is_public = spec.get("is_public", 0)
    doc.is_standard = spec.get("is_standard", 1)
    doc.show_percentage_stats = spec.get("show_percentage_stats", 0)
    doc.filters_json = spec.get("filters_json")
    doc.dynamic_filters_json = spec.get("dynamic_filters_json")
    doc.module = spec.get("module", "Master Plan IT")
    doc.save(ignore_permissions=True)

    export_to_files(
        record_list=[["Number Card", doc.name]],
        record_module=doc.module,
        create_init=True,
    )


def _apply_dashboard_chart(spec: Dict[str, Any]) -> None:
    name = spec["chart_name"]
    if frappe.db.exists("Dashboard Chart", name):
        doc = frappe.get_doc("Dashboard Chart", name)
    else:
        doc = frappe.new_doc("Dashboard Chart")
        doc.chart_name = name

    module = spec.get("module", "Master Plan IT")
    _ensure_module_def(module)

    for key in [
        "chart_type",
        "report_name",
        "use_report_chart",
        "x_field",
        "y_axis",
        "source",
        "document_type",
        "parent_document_type",
        "based_on",
        "value_based_on",
        "group_by_type",
        "group_by_based_on",
        "aggregate_function_based_on",
        "number_of_groups",
        "is_public",
        "heatmap_year",
        "timespan",
        "from_date",
        "to_date",
        "time_interval",
        "timeseries",
        "type",
        "show_values_over_chart",
        "currency",
        "filters_json",
        "dynamic_filters_json",
        "custom_options",
        "color",
    ]:
        if key in spec:
            setattr(doc, key, spec[key])

    # Report charts require filters_json; default to empty JSON if not provided
    if not getattr(doc, "filters_json", None):
        doc.filters_json = "{}"

    doc.is_standard = spec.get("is_standard", 1)
    doc.module = module

    doc.save(ignore_permissions=True)

    export_to_files(
        record_list=[["Dashboard Chart", doc.name]],
        record_module=module,
        create_init=True,
    )


def _apply_dashboard(spec: Dict[str, Any]) -> None:
    name = spec["dashboard_name"]
    if frappe.db.exists("Dashboard", name):
        doc = frappe.get_doc("Dashboard", name)
    else:
        doc = frappe.new_doc("Dashboard")
        doc.dashboard_name = name

    module = spec.get("module", "Master Plan IT")
    _ensure_module_def(module)

    doc.is_standard = spec.get("is_standard", 1)
    doc.module = module
    doc.is_default = spec.get("is_default", 0)
    doc.chart_options = spec.get("chart_options")

    doc.charts = []
    for ch in spec.get("charts", []):
        doc.append("charts", ch)

    doc.cards = []
    for card in spec.get("cards", []):
        doc.append("cards", card)

    doc.save(ignore_permissions=True)

    export_to_files(
        record_list=[["Dashboard", doc.name]],
        record_module=module,
        create_init=True,
    )


def sync_all() -> str:
    frappe.flags.in_patch = True
    frappe.flags.in_install = True
    frappe.flags.ignore_version = True
    spec_dir = _spec_dir()

    # Preflight must run first: fail-fast, no partial imports.
    preflight_validate(spec_dir)

    doctypes = _load_specs(spec_dir)
    workflows = _load_workflow_specs(spec_dir)
    reports = _load_specs_in_dir(spec_dir, REPORT_SPEC_DIR)
    number_cards = _load_specs_in_dir(spec_dir, NUMBER_CARD_SPEC_DIR)
    dashboard_charts = _load_specs_in_dir(spec_dir, DASHBOARD_CHART_SPEC_DIR)
    dashboards = _load_specs_in_dir(spec_dir, DASHBOARD_SPEC_DIR)

    # Roles must exist before we attach permissions/workflows.
    _ensure_roles()

    doctypes = _toposort(doctypes)
    for d in doctypes:
        _apply_doctype(d)

    if workflows:
        _ensure_workflow_prereqs(workflows)
        for wf in workflows:
            _apply_workflow(wf)

    for r in reports:
        _apply_report(r)

    for nc in number_cards:
        _apply_number_card(nc)

    for dc in dashboard_charts:
        _apply_dashboard_chart(dc)

    for dash in dashboards:
        _apply_dashboard(dash)

    frappe.db.commit()
    frappe.clear_cache()
    return "OK"
