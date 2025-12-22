# -*- coding: utf-8 -*-
"""MPIT DevTools: preflight validation (no side effects)

This module validates the JSON specs before any DB mutation.
It is designed to stop drift and prevent partial imports.

SPEC FORMAT (minimal)
- spec/doctypes/*.json:
    {
      "name": "MPIT Vendor",
      "module": "Master Plan IT",
      "promote_to_standard": true,
      "istable": 0,
      "issingle": 0,
      "is_submittable": 0,
      "fields": [ {field dict}, ... ],
      "permissions": [ {perm dict}, ... ]   # optional
    }

Validation rules:
- Each DocType must have name + module + fields.
- Field dict must include: fieldname, fieldtype, label (except for Section/Column breaks).
- Link fields require options; Table fields require options (child doctype).
- Select fields require options.
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any, Dict, List, Set, Tuple

FIELD_TYPES_REQUIRING_LABEL = {
    "Data", "Text", "Small Text", "Long Text", "Int", "Float", "Currency", "Check",
    "Date", "Datetime", "Time", "Link", "Dynamic Link", "Select", "Table", "Attach",
    "Attach Image", "HTML", "Markdown Editor"
}

FIELD_TYPES_NO_LABEL = {"Section Break", "Column Break", "Tab Break"}

def _load_all_doctype_specs(spec_dir: Path) -> List[Dict[str, Any]]:
    doctypes_dir = spec_dir / "doctypes"
    if not doctypes_dir.exists():
        return []
    out: List[Dict[str, Any]] = []
    for p in sorted(doctypes_dir.glob("*.json")):
        out.append(json.loads(p.read_text(encoding="utf-8")))
    return out


def validate(spec_dir: Path) -> None:
    doctypes = _load_all_doctype_specs(spec_dir)

    if not doctypes:
        raise ValueError(f"No DocType specs found in {spec_dir/'doctypes'}")

    names = [d.get("name") for d in doctypes]
    if any(not n for n in names):
        raise ValueError("Some DocType specs are missing 'name'")

    if len(set(names)) != len(names):
        raise ValueError("Duplicate DocType names in specs")

    known: Set[str] = set(names)

    # validate fields and collect dependencies
    for d in doctypes:
        if not d.get("module"):
            raise ValueError(f"DocType {d['name']} missing 'module'")
        fields = d.get("fields")
        if not isinstance(fields, list) or not fields:
            raise ValueError(f"DocType {d['name']} must include non-empty 'fields' list")

        for f in fields:
            ft = f.get("fieldtype")
            fn = f.get("fieldname")

            if not ft:
                raise ValueError(f"DocType {d['name']} has a field missing 'fieldtype'")

            if ft not in FIELD_TYPES_NO_LABEL:
                if not f.get("label"):
                    raise ValueError(f"DocType {d['name']} field '{fn}' missing 'label' (fieldtype={ft})")

            # Many fieldtypes require fieldname (break fields may omit)
            if ft not in FIELD_TYPES_NO_LABEL and not fn:
                raise ValueError(f"DocType {d['name']} has a field missing 'fieldname' (fieldtype={ft})")

            # Options validation
            if ft in ("Link", "Dynamic Link", "Table"):
                if not f.get("options"):
                    raise ValueError(f"DocType {d['name']} field '{fn}' (type {ft}) missing 'options'")

            if ft == "Select":
                if not f.get("options"):
                    raise ValueError(f"DocType {d['name']} field '{fn}' (Select) missing 'options'")

    # Table dependency validation (Table options must be another DocType spec that is istable=1)
    specs_by_name = {d["name"]: d for d in doctypes}
    for d in doctypes:
        for f in d["fields"]:
            if f.get("fieldtype") == "Table":
                child = f["options"]
                if child not in known:
                    raise ValueError(f"DocType {d['name']} references missing child table DocType '{child}'")
                if not specs_by_name[child].get("istable", 0):
                    raise ValueError(f"DocType {d['name']} Table field '{f.get('fieldname')}' options '{child}' is not marked istable=1")

    # Simple topological cycle detection (Table deps + Link deps only within MPIT set)
    deps: Dict[str, Set[str]] = {n: set() for n in known}
    for d in doctypes:
        dn = d["name"]
        for f in d["fields"]:
            ft = f.get("fieldtype")
            if ft in ("Link", "Table"):
                opt = f.get("options")
                if opt in known:
                    deps[dn].add(opt)

    # Kahn
    indeg = {n: 0 for n in known}
    for n in known:
        for m in deps[n]:
            indeg[n] += 1

    queue = [n for n in known if indeg[n] == 0]
    ordered = []
    while queue:
        n = sorted(queue)[0]
        queue.remove(n)
        ordered.append(n)
        for x in known:
            if n in deps[x]:
                indeg[x] -= 1
                deps[x].remove(n)
                if indeg[x] == 0:
                    queue.append(x)

    if len(ordered) != len(known):
        raise ValueError("Dependency cycle detected among DocType specs (Link/Table). Break the cycle or remove internal Link deps.")
