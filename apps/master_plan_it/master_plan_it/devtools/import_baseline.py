"""Script per importare baseline expenses dal CSV."""
from __future__ import annotations

import csv
import frappe

CSV_PATH = "/home/frappe/frappe-bench/sites/Analisi Costi IT e Proposta Budget - Spese Ricorrenti - Attuale.csv"

# Mapping stato italiano -> status inglese
STATUS_MAP = {
    "Approvato": "Validated",
    "Richiede Revisione": "Needs Clarification",
    "In Revisione": "In Review",
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
    """Importa le baseline expenses dal CSV."""
    print("=== Import Baseline Expenses ===\n")
    
    # 1. Crea categorie
    print("1. Creazione categorie...")
    ensure_categories()
    
    # 2. Leggi e importa
    print("\n2. Importazione righe...")
    
    with open(CSV_PATH, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        count = 0
        skipped = 0
        
        for row in reader:
            stato = row.get("Stato", "").strip()
            area = row.get("Area", "").strip()
            produttore = row.get("Produttore", "").strip()
            fornitore = row.get("Fornitore", "").strip()
            prodotto = row.get("Prodotto", "").strip()
            
            # Parse new fields: Prezzo (unit price), Qty, Totale (annual)
            prezzo_str = row.get("Prezzo", "")
            qty_str = row.get("Qty", "1")
            totale_str = row.get("Totale", "")
            note = row.get("Note", "").strip()
            
            # Skip righe vuote o senza totale
            if not area or not totale_str:
                skipped += 1
                continue
            
            # Parse valori numerici
            qty = parse_qty(qty_str)
            unit_price = parse_euro(prezzo_str)  # Prezzo mensile unitario
            annual_amount = parse_euro(totale_str)  # Totale annuo
            
            if annual_amount <= 0:
                skipped += 1
                continue
            
            # Determina vendor (usa fornitore se diverso da produttore, altrimenti produttore)
            vendor_name = fornitore if fornitore and fornitore != produttore else produttore
            vendor = ensure_vendor(vendor_name)
            
            # Crea anche il produttore come vendor se diverso
            if produttore and produttore != fornitore:
                ensure_vendor(produttore)
            
            # Mappa status
            status = STATUS_MAP.get(stato, "In Review")
            
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
            
            # Determina expense_kind dalla categoria
            if area in ("Spese una Tantum", "Progetti"):
                expense_kind = "One-off"
                recurrence = "None"
            else:
                expense_kind = "Subscription"
                recurrence = "Monthly"  # Il prezzo nel CSV è mensile
            
            # Crea baseline expense con nuovi campi
            doc = frappe.get_doc({
                "doctype": "MPIT Baseline Expense",
                "year": "2025",
                "status": status,
                "expense_kind": expense_kind,
                "category": category,
                "vendor": vendor,
                "description": description,
                # Nuovi campi pricing
                "qty": qty,
                "unit_price": unit_price,  # Prezzo mensile unitario
                "annual_amount": annual_amount,  # Totale annuo - monthly verrà calcolato
                "amount_includes_vat": 1,  # I prezzi nel CSV sembrano lordi
                "vat_rate": 22,
                "recurrence_rule": recurrence,
            })
            doc.insert(ignore_permissions=True)
            count += 1
            print(f"  [{count}] {category} - {vendor or 'N/A'}: Qty {qty} x €{unit_price:.2f} = €{annual_amount:.2f}/anno")
    
    frappe.db.commit()
    print(f"\n=== Completato: {count} righe importate, {skipped} saltate ===")
