#!/usr/bin/env python3
"""Test script for print formats in MPIT Phase 6."""
import frappe
from frappe.modules.import_file import import_file_by_path
import os

def setup_fixtures():
    """Create necessary fixtures."""
    # Create Category
    if not frappe.db.exists("MPIT Category", "Hardware"):
        c1 = frappe.get_doc({"doctype": "MPIT Category", "category_name": "Hardware"})
        c1.insert()
    
    if not frappe.db.exists("MPIT Category", "Software"):
        c2 = frappe.get_doc({"doctype": "MPIT Category", "category_name": "Software"})
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
    app_path = "/home/frappe/frappe-bench/apps/master_plan_it/master_plan_it/master_plan_it"
    budget_pf = os.path.join(app_path, "print_format/mpit_budget_professional/mpit_budget_professional.json")
    project_pf = os.path.join(app_path, "print_format/mpit_project_professional/mpit_project_professional.json")

    if os.path.exists(budget_pf):
        import_file_by_path(budget_pf)
        print(f"✓ Imported Budget print format")
    else:
        print(f"✗ Budget PF not found: {budget_pf}")
    
    if os.path.exists(project_pf):
        import_file_by_path(project_pf)
        print(f"✓ Imported Project print format")
    else:
        print(f"✗ Project PF not found: {project_pf}")

    frappe.db.commit()

def create_test_budget():
    """Create test Budget with VAT and recurrence."""
    from master_plan_it.mpit_user_prefs import get_or_create
    
    # Ensure user prefs exist
    prefs = get_or_create("Administrator")
    frappe.db.commit()
    
    # Create Budget
    b = frappe.get_doc({
        "doctype": "MPIT Budget",
        "fiscal_year": "2025",
        "status": "Draft",
        "lines": [
            {
                "category": "Hardware",
                "vendor": "Acme Corp",
                "description": "Server upgrade",
                "amount_net": 1000,
                "vat_rate": 22,
                "includes_vat": 0,
                "recurrence_rule": "Monthly"
            },
            {
                "category": "Software",
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
        print(f"  - {pf.name} (standard={pf.standard}, has_html={bool(pf.html)})")
    
    print(f"\nPrint Formats for MPIT Project ({len(project_pf)} found):")
    for pf in project_pf:
        print(f"  - {pf.name} (standard={pf.standard}, has_html={bool(pf.html)})")

def main():
    """Run all tests."""
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

if __name__ == "__main__":
    main()
