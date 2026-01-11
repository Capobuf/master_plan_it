
import frappe
from frappe.utils import flt

def verify_project_financials():
    # 1. Create Project
    if not frappe.db.exists("MPIT Cost Center", "Test CC"):
        cc = frappe.get_doc({"doctype": "MPIT Cost Center", "cost_center_name": "Test CC", "code": "TCC"}).insert()
    else:
        cc = frappe.get_doc("MPIT Cost Center", "Test CC")

    proj = frappe.get_doc({
        "doctype": "MPIT Project",
        "title": "Financial Test Project",
        "status": "Approved",
        "cost_center": cc.name,
        "start_date": "2026-01-01",
        "end_date": "2026-12-31"
    }).insert(ignore_permissions=True)

    # 2. Add Planned Item (Estimate 1000)
    pi = frappe.get_doc({
        "doctype": "MPIT Planned Item",
        "project": proj.name,
        "description": "Test Item",
        "amount": 1000,
        "start_date": "2026-01-01",
        "end_date": "2026-12-31",
        "distribution": "all",
        "item_type": "Estimate"
    }).submit()

    print(f"DEBUG: PI Project: {pi.project}, Docstatus: {pi.docstatus}")
    print(f"DEBUG: DB Items: {frappe.db.get_all('MPIT Planned Item', fields=['name', 'project', 'docstatus'])}")

    proj.reload()
    print(f"Planned: {proj.planned_total_net}, Expected: {proj.expected_total_net}")
    assert flt(proj.planned_total_net) == 1000.0
    assert flt(proj.expected_total_net) == 1000.0
    assert flt(proj.actual_total_net) == 0.0
    assert flt(proj.variance_net) == 0.0 # 1000 - 1000
    assert flt(proj.utilization_pct) == 0.0

    # 3. Add Actual Entry (Delta 500)
    ae = frappe.get_doc({
        "doctype": "MPIT Actual Entry",
        "entry_kind": "Delta",
        "project": proj.name,
        "planned_item": pi.name,
        "amount": 500,
        "posting_date": "2026-06-01",
        "status": "Verified" # Must be verified to count
    }).insert(ignore_permissions=True)

    print(f"DEBUG: AE Name: {ae.name}, Status: {ae.status}, Kind: {ae.entry_kind}, Project: {ae.project}, Net: {ae.amount_net}")
    print(f"DEBUG: DB Actuals: {frappe.db.sql('select name, project, status, entry_kind, amount_net from `tabMPIT Actual Entry`')}")

    # Project should update automatically now
    proj.reload()
    
    print(f"Actual: {proj.actual_total_net}, Variance: {proj.variance_net}, Util: {proj.utilization_pct}")
    assert flt(proj.actual_total_net) == 500.0
    assert flt(proj.variance_net) == 500.0 # 1000 (Plan) - 500 (Expected) = 500 Savings
    assert flt(proj.utilization_pct) == 50.0

    # 4. Add another Actual (Delta 600) -> Total 1100 (Over budget)
    ae2 = frappe.get_doc({
        "doctype": "MPIT Actual Entry",
        "entry_kind": "Delta",
        "project": proj.name,
        "planned_item": pi.name,
        "amount": 600,
        "posting_date": "2026-07-01",
        "status": "Verified"
    }).insert(ignore_permissions=True)

    proj.reload()

    print(f"Actual: {proj.actual_total_net}, Variance: {proj.variance_net}, Util: {proj.utilization_pct}")
    assert flt(proj.actual_total_net) == 1100.0
    assert flt(proj.variance_net) == -100.0 # 1000 (Plan) - 1100 (Expected) = -100 Overrun
    assert flt(proj.utilization_pct) == 110.0

    print("Success! Financials verified.")
    
    # Cleanup
    frappe.db.rollback()

verify_project_financials()
