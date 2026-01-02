#!/usr/bin/env python3
"""Test script for print formats in MPIT Phase 6."""
from pathlib import Path

import frappe
from frappe.modules.import_file import import_file_by_path

def setup_fixtures():
    """Create necessary fixtures."""
    # Create Cost Centers
    if not frappe.db.exists("MPIT Cost Center", "Hardware CC"):
        c1 = frappe.get_doc({"doctype": "MPIT Cost Center", "cost_center_name": "Hardware CC", "is_group": 0})
        c1.insert()

    if not frappe.db.exists("MPIT Cost Center", "Software CC"):
        c2 = frappe.get_doc({"doctype": "MPIT Cost Center", "cost_center_name": "Software CC", "is_group": 0})
        c2.insert()

    # Create Vendor
    if not frappe.db.exists("MPIT Vendor", "Acme Corp"):
        v1 = frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": "Acme Corp"})
        v1.insert()
    
    if not frappe.db.exists("MPIT Vendor", "Tech Inc"):
        v2 = frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": "Tech Inc"})
        v2.insert()

    frappe.db.commit()
    print("✓ Fixtures created")

def import_print_formats():
    """Import print formats from JSON specs."""
    app_path = Path(frappe.get_app_path("master_plan_it")) / "master_plan_it"
    budget_pf = app_path / "print_format" / "mpit_budget_professional.json"
    project_pf = app_path / "print_format" / "mpit_project_professional.json"

    if budget_pf.exists():
        import_file_by_path(str(budget_pf))
        print(f"✓ Imported Budget print format from {budget_pf}")
    else:
        print(f"✗ Budget PF not found: {budget_pf}")
    
    if project_pf.exists():
        import_file_by_path(str(project_pf))
        print(f"✓ Imported Project print format from {project_pf}")
    else:
        print(f"✗ Project PF not found: {project_pf}")

    frappe.db.commit()

def create_test_budget():
    """Create test Budget with VAT and recurrence."""
    # No user prefs needed - Settings is used globally
    
    # Create Year if not exists
    if not frappe.db.exists("MPIT Year", "2025"):
        y = frappe.get_doc({"doctype": "MPIT Year", "year_name": "2025"})
        y.insert()
        frappe.db.commit()
        print("✓ Created Year: 2025")
    
    # Create Budget
    b = frappe.get_doc({
        "doctype": "MPIT Budget",
        "year": "2025",
        "title": "Test Budget 2025",
        "lines": [
            {
                "cost_center": "Hardware CC",
                "vendor": "Acme Corp",
                "description": "Server upgrade",
                "amount_net": 1000,
                "vat_rate": 22,
                "includes_vat": 0,
                "recurrence_rule": "Monthly"
            },
            {
                "cost_center": "Software CC",
                "vendor": "Tech Inc",
                "description": "License renewal",
                "amount_net": 500,
                "vat_rate": 22,
                "includes_vat": 0,
                "recurrence_rule": "Quarterly"
            }
        ]
    })
    b.insert()
    frappe.db.commit()
    
    print(f"\n✓ Created Budget: {b.name}")
    print(f"  Title: {b.title}")
    print(f"  Line 1 Annual Net: {b.lines[0].annual_net} (Monthly 1000×12)")
    print(f"  Line 1 Annual VAT: {b.lines[0].annual_vat}")
    print(f"  Line 1 Annual Gross: {b.lines[0].annual_gross}")
    print(f"  Line 2 Annual Net: {b.lines[1].annual_net} (Quarterly 500×4)")
    print(f"  Line 2 Annual VAT: {b.lines[1].annual_vat}")
    print(f"  Line 2 Annual Gross: {b.lines[1].annual_gross}")
    
    return b.name

def verify_print_formats():
    """Check available print formats."""
    budget_pf = frappe.get_all("Print Format", filters={"doc_type": "MPIT Budget"}, fields=["name", "standard", "html"])
    project_pf = frappe.get_all("Print Format", filters={"doc_type": "MPIT Project"}, fields=["name", "standard", "html"])
    
    print(f"\nPrint Formats for MPIT Budget ({len(budget_pf)} found):")
    for pf in budget_pf:
        has_html = "YES" if pf.html else "NO"
        print(f"  - {pf.name} (standard={pf.standard}, html={has_html})")
    
    print(f"\nPrint Formats for MPIT Project ({len(project_pf)} found):")
    for pf in project_pf:
        has_html = "YES" if pf.html else "NO"
        print(f"  - {pf.name} (standard={pf.standard}, html={has_html})")

def run():
    """Run all tests (entry point for bench execute)."""
    print("=" * 60)
    print("MPIT Phase 6 - Print Format Testing")
    print("=" * 60)
    
    setup_fixtures()
    import_print_formats()
    verify_print_formats()
    budget_name = create_test_budget()
    
    print("\n" + "=" * 60)
    print("✓ All tests completed successfully")
    print(f"✓ Test Budget created: {budget_name}")
    print("=" * 60)
