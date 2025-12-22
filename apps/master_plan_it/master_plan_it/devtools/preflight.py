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
                if opt in known and opt != dn:
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

    _validate_workflows(spec_dir)


def _load_workflow_specs(spec_dir: Path) -> List[Dict[str, Any]]:
    workflows_dir = spec_dir / "workflows"
    if not workflows_dir.exists():
        return []
    out: List[Dict[str, Any]] = []
    for p in sorted(workflows_dir.glob("*.json")):
        out.append(json.loads(p.read_text(encoding="utf-8")))
    return out


def _validate_workflows(spec_dir: Path) -> None:
    """Validate workflow specs if present (optional directory)."""
    workflows = _load_workflow_specs(spec_dir)
    if not workflows:
        return

    required_keys = {"workflow_name", "document_type", "workflow_state_field", "states", "transitions"}
    for wf in workflows:
        missing = [k for k in required_keys if k not in wf]
        if missing:
            raise ValueError(f"Workflow spec '{wf.get('workflow_name')}' missing keys: {missing}")
        if not isinstance(wf.get("states"), list) or not wf["states"]:
            raise ValueError(f"Workflow spec '{wf['workflow_name']}' must include non-empty 'states'")
        if not isinstance(wf.get("transitions"), list) or not wf["transitions"]:
            raise ValueError(f"Workflow spec '{wf['workflow_name']}' must include non-empty 'transitions'")

        state_names: Set[str] = set()
        for st in wf["states"]:
            state = st.get("state")
            if not state:
                raise ValueError(f"Workflow '{wf['workflow_name']}' has a state missing 'state' name")
            if state in state_names:
                raise ValueError(f"Workflow '{wf['workflow_name']}' has duplicate state '{state}'")
            state_names.add(state)
            if not st.get("allow_edit"):
                raise ValueError(f"Workflow '{wf['workflow_name']}' state '{state}' missing allow_edit")
            if "doc_status" not in st:
                raise ValueError(f"Workflow '{wf['workflow_name']}' state '{state}' missing doc_status")
            if st.get("doc_status") not in (0, 1, 2):
                raise ValueError(f"Workflow '{wf['workflow_name']}' state '{state}' has invalid doc_status {st.get('doc_status')}")

        for tr in wf["transitions"]:
            if not tr.get("state") or not tr.get("next_state") or not tr.get("action"):
                raise ValueError(f"Workflow '{wf['workflow_name']}' has a transition missing state/next_state/action")
            if tr["state"] not in state_names or tr["next_state"] not in state_names:
                raise ValueError(f"Workflow '{wf['workflow_name']}' transition uses unknown state {tr['state']} -> {tr['next_state']}")
