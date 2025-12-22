# -*- coding: utf-8 -*-
"""MPIT DevTools: deterministic sync (no Desk UI)

PURPOSE
- Apply MPIT specs (DocTypes + optional Roles/Module Def) into a Frappe site without using the Desk UI.
- Idempotent: safe to run multiple times.
- Deterministic: specs are applied in dependency order.

ENTRYPOINT
- bench --site <site> execute master_plan_it.devtools.sync.sync_all

SPEC LOCATION
- apps/master_plan_it/spec/doctypes/*.json

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


def _deps_for(d: Dict[str, Any], known: Set[str]) -> Set[str]:
    deps: Set[str] = set()
    for f in d.get("fields", []):
        if f.get("fieldtype") in ("Link", "Table"):
            opt = f.get("options")
            if opt in known:
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


def sync_all() -> str:
    spec_dir = _spec_dir()

    # Preflight must run first: fail-fast, no partial imports.
    preflight_validate(spec_dir)

    specs = _load_specs(spec_dir)
    specs = _toposort(specs)

    for d in specs:
        _apply_doctype(d)

    frappe.db.commit()
    frappe.clear_cache()
    return "OK"
