"""Script per rinominare i documenti esistenti con il nuovo naming series."""
from __future__ import annotations

import frappe


def run() -> None:
    """Rinomina tutti i documenti MPIT Baseline Expense e MPIT Budget con naming series."""
    
    # 1. Rinomina Baseline Expenses
    print("=== Rinominazione MPIT Baseline Expense ===\n")
    
    baselines = frappe.get_all(
        "MPIT Baseline Expense",
        filters={},
        fields=["name", "year", "creation"],
        order_by="creation asc"
    )
    
    for i, doc in enumerate(baselines, 1):
        old_name = doc.name
        year = doc.year or "2025"
        new_name = f"BAS-{year}-{i:05d}"
        
        if old_name != new_name:
            try:
                # Aggiorna naming_series nel documento
                frappe.db.set_value("MPIT Baseline Expense", old_name, "naming_series", f"BAS-.YYYY.-")
                # Rinomina il documento
                frappe.rename_doc("MPIT Baseline Expense", old_name, new_name, force=True)
                print(f"  {old_name} -> {new_name}")
            except Exception as e:
                print(f"  ERRORE {old_name}: {e}")
    
    frappe.db.commit()
    print(f"\nRinominati {len(baselines)} Baseline Expenses\n")
    
    # 2. Rinomina Budgets
    print("=== Rinominazione MPIT Budget ===\n")
    
    budgets = frappe.get_all(
        "MPIT Budget",
        filters={},
        fields=["name", "year", "creation"],
        order_by="creation asc"
    )
    
    for i, doc in enumerate(budgets, 1):
        old_name = doc.name
        year = doc.year or "2025"
        new_name = f"BUD-{year}-{i:05d}"
        
        if old_name != new_name:
            try:
                # Aggiorna naming_series nel documento
                frappe.db.set_value("MPIT Budget", old_name, "naming_series", f"BUD-.YYYY.-")
                # Rinomina il documento
                frappe.rename_doc("MPIT Budget", old_name, new_name, force=True)
                print(f"  {old_name} -> {new_name}")
            except Exception as e:
                print(f"  ERRORE {old_name}: {e}")
    
    frappe.db.commit()
    print(f"\nRinominati {len(budgets)} Budgets\n")
    
    # 3. Aggiorna i contatori delle serie
    print("=== Aggiornamento contatori serie ===\n")
    
    # Conta quanti documenti per anno
    baseline_count = len(baselines)
    budget_count = len(budgets)
    
    # Aggiorna/crea i record Series per baseline
    series_name = "BAS-2025-"
    if frappe.db.exists("Series", series_name):
        frappe.db.set_value("Series", series_name, "current", baseline_count)
    else:
        frappe.get_doc({
            "doctype": "Series",
            "name": series_name,
            "current": baseline_count
        }).insert(ignore_permissions=True)
    print(f"  Serie {series_name} -> {baseline_count}")
    
    # Aggiorna/crea i record Series per budget
    series_name = "BUD-2025-"
    if frappe.db.exists("Series", series_name):
        frappe.db.set_value("Series", series_name, "current", budget_count)
    else:
        frappe.get_doc({
            "doctype": "Series",
            "name": series_name,
            "current": budget_count
        }).insert(ignore_permissions=True)
    print(f"  Serie {series_name} -> {budget_count}")
    
    frappe.db.commit()
    print("\n=== Completato ===")
