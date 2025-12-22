# -*- coding: utf-8 -*-
"""MPIT tests: preflight validation

Intention:
- Specs must be valid before attempting sync.
"""

from pathlib import Path
import frappe

from master_plan_it.devtools.preflight import validate


def test_specs_preflight_valid():
    spec_dir = Path(frappe.get_app_path("master_plan_it")) / "spec"
    validate(spec_dir)
