"""Script per importare budget dal CSV come MPIT Budget con linee."""
from __future__ import annotations

import csv
import frappe

CSV_PATH = "/home/frappe/frappe-bench/sites/budget.csv"

# Mapping stato italiano -> status inglese
STATUS_MAP = {
    "Approvato": "Approved",
    "Richiede Revisione": "In Review",
    "In Revisione": "In Review",
    "": "Draft",
}

# Mapping Area -> Category
CATEGORIES = [
    "Infrastruttura",
    "Progetti",
    "Servizi Cloud",
    "Servizi Professionali",
    "Sicurezza",
    "Software/Gestionali",
    "Spese una Tantum",
]


def parse_euro(value: str) -> float:
    """Converte stringa euro italiana in float."""
    if not value:
        return 0.0
    # Rimuovi € e spazi
    value = value.replace("€", "").strip()
    # Formato italiano: 1.234,56 -> 1234.56
    value = value.replace(".", "").replace(",", ".")
    try:
        return float(value)
    except ValueError:
        return 0.0


def parse_qty(value: str) -> float:
    """Parse quantity field."""
    if not value:
        return 1.0
    try:
        return float(value.strip())
    except ValueError:
        return 1.0


def ensure_categories() -> None:
    """Crea le categorie se non esistono."""
    for cat in CATEGORIES:
        if not frappe.db.exists("MPIT Category", cat):
            doc = frappe.get_doc({
                "doctype": "MPIT Category",
                "category_name": cat
            })
            doc.insert(ignore_permissions=True)
            print(f"  Creata categoria: {cat}")
    frappe.db.commit()


def ensure_vendor(name: str) -> str | None:
    """Crea vendor se non esiste, ritorna il nome."""
    if not name:
        return None
    name = name.strip()
    if not name:
        return None
    if not frappe.db.exists("MPIT Vendor", name):
        doc = frappe.get_doc({
            "doctype": "MPIT Vendor",
            "vendor_name": name
        })
        doc.insert(ignore_permissions=True)
        print(f"  Creato vendor: {name}")
        frappe.db.commit()
    return name


def run() -> None:
    """Importa il budget dal CSV come MPIT Budget con linee.
    
    Mappatura CSV -> MPIT Budget Line:
    - Prezzo = unit_price (prezzo MENSILE per unità, IVA ESCLUSA)
    - Qty = qty
    - Totale = annual_amount (Prezzo × Qty × 12)
    - Tutti i prezzi nel CSV sono MENSILI e NETTI (IVA esclusa)
    """
    print("=== Import Budget 2025 ===\n")
    
    # 1. Crea categorie
    print("1. Creazione categorie...")
    ensure_categories()
    
    # 2. Leggi CSV e raccogli le linee
    print("\n2. Lettura righe dal CSV...")
    print("   Mappatura: Prezzo=mensile/unità (NETTO), Totale=annuale (NETTO)")
    print()
    
    lines_data = []
    csv_total = 0.0
    
    with open(CSV_PATH, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        
        for row in reader:
            area = row.get("Area", "").strip()
            produttore = row.get("Produttore", "").strip()
            fornitore = row.get("Fornitore", "").strip()
            prodotto = row.get("Prodotto", "").strip()
            
            # Parse campi numerici
            prezzo_str = row.get("Prezzo", "")
            qty_str = row.get("Qty", "1")
            totale_str = row.get("Totale", "")
            note = row.get("Note", "").strip()
            
            # Skip righe senza totale
            if not totale_str:
                continue
            
            # Skip righe senza Area (sono righe totale del foglio)
            if not area:
                skip_val = parse_euro(totale_str)
                print(f"  [SKIP] Riga senza Area (totale foglio): €{skip_val:,.2f}")
                continue
            
            qty = parse_qty(qty_str)
            unit_price = parse_euro(prezzo_str)  # Prezzo MENSILE per unità (NETTO)
            annual_from_csv = parse_euro(totale_str)  # Totale annuale dal CSV
            
            if annual_from_csv <= 0:
                continue
            
            csv_total += annual_from_csv
            
            # Determina vendor
            vendor_name = fornitore if fornitore and fornitore != produttore else produttore
            vendor = ensure_vendor(vendor_name)
            
            # Crea anche il produttore come vendor se diverso
            if produttore and produttore != fornitore:
                ensure_vendor(produttore)
            
            # Mappa categoria
            category = area if area in CATEGORIES else "Spese una Tantum"
            
            # Costruisci descrizione
            description_parts = []
            if prodotto:
                description_parts.append(prodotto)
            if produttore and produttore != vendor_name:
                description_parts.append(f"Produttore: {produttore}")
            if note:
                description_parts.append(f"Note: {note}")
            description = "\n".join(description_parts)
            
            # Determina cost_type (ma recurrence è SEMPRE Monthly)
            if area in ("Spese una Tantum", "Progetti"):
                cost_type = "CAPEX"
            else:
                cost_type = "OPEX"
            
            # IMPORTANTE:
            # - Tutti i prezzi nel CSV sono MENSILI → recurrence_rule = "Monthly"
            # - Tutti i prezzi sono IVA ESCLUSA → amount_includes_vat = 0
            lines_data.append({
                "category": category,
                "vendor": vendor,
                "description": description,
                "qty": qty,
                "unit_price": unit_price,  # Prezzo MENSILE per unità
                "monthly_amount": 0,  # Calcolato dal sistema: qty × unit_price
                "annual_amount": 0,   # Calcolato dal sistema: monthly × 12
                "amount_includes_vat": 0,  # Prezzi NETTI (IVA esclusa)
                "vat_rate": 22,
                "recurrence_rule": "Monthly",  # TUTTI i prezzi sono mensili
                "cost_type": cost_type,
            })
            
            expected_annual = qty * unit_price * 12
            print(f"  {category} | {vendor or 'N/A'} | {qty}× €{unit_price:.2f}/mese = €{expected_annual:.2f}/anno")
    
    print(f"\n  Totale linee: {len(lines_data)}")
    print(f"  Totale NETTO da CSV: €{csv_total:,.2f}")
    
    # 3. Elimina budget esistente
    print("\n3. Verifica budget esistente...")
    existing = frappe.db.get_value("MPIT Budget", {"year": "2025"}, "name")
    if existing:
        frappe.delete_doc("MPIT Budget", existing, force=True)
        frappe.db.commit()
        print(f"  Eliminato: {existing}")
    
    # 4. Crea il Budget
    print("\n4. Creazione MPIT Budget 2025...")
    
    budget = frappe.get_doc({
        "doctype": "MPIT Budget",
        "year": "2025",
        "title": "Budget IT 2025",
        "workflow_state": "Draft",
        "lines": lines_data,
    })
    
    budget.insert(ignore_permissions=True)
    frappe.db.commit()
    
    # 5. Riepilogo
    print(f"\n{'='*50}")
    print(f"Budget creato: {budget.name}")
    print(f"{'='*50}")
    print(f"  Anno: {budget.year}")
    print(f"  Titolo: {budget.title}")
    print(f"  Linee: {len(budget.lines)}")
    print(f"  Totale Mensile: €{budget.total_amount_monthly:,.2f}")
    print(f"  Totale Annuale (NETTO): €{budget.total_amount_net:,.2f}")
    print(f"  IVA 22%: €{budget.total_amount_vat:,.2f}")
    print(f"  Totale LORDO: €{budget.total_amount_gross:,.2f}")
    
    # 6. Verifica
    print(f"\n{'='*50}")
    print("VERIFICA")
    print(f"{'='*50}")
    print(f"  CSV Totale NETTO:  €{csv_total:,.2f}")
    print(f"  Import NETTO:      €{budget.total_amount_net:,.2f}")
    diff = abs(csv_total - budget.total_amount_net)
    if diff < 1:
        print(f"  ✅ Totali corrispondono! (diff: €{diff:.2f})")
    else:
        print(f"  ❌ ERRORE: Differenza €{diff:,.2f}")


def delete_budget() -> None:
    """Elimina il budget 2025 esistente."""
    existing = frappe.db.get_value("MPIT Budget", {"year": "2025"}, "name")
    if existing:
        frappe.delete_doc("MPIT Budget", existing, force=True)
        frappe.db.commit()
        print(f"Eliminato budget: {existing}")
    else:
        print("Nessun budget 2025 trovato")
