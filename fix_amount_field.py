#!/usr/bin/env python3
"""
Script to fix the amount field in MPIT Budget Line DocType
"""
import frappe

frappe.init(site='budget.zeroloop.it')
frappe.connect()

# Get the DocType
dt = frappe.get_doc('DocType', 'MPIT Budget Line')

# Find the amount field
amount_field = None
for f in dt.fields:
    if f.fieldname == 'amount':
        amount_field = f
        break

if amount_field:
    # Update field properties
    amount_field.hidden = 0
    amount_field.read_only = 0
    amount_field.in_list_view = 1
    amount_field.label = 'Amount'
    
    # Save the DocType
    dt.save()
    frappe.db.commit()
    
    print('✅ Campo amount aggiornato con successo!')
    print(f'   - hidden: {amount_field.hidden}')
    print(f'   - read_only: {amount_field.read_only}')
    print(f'   - in_list_view: {amount_field.in_list_view}')
    print(f'   - label: {amount_field.label}')
else:
    print('❌ Campo amount non trovato!')

# Also ensure the boolean flag is visible in list view
inc_field = None
for f in dt.fields:
    if f.fieldname == 'amount_includes_vat':
        inc_field = f
        break

if inc_field:
    inc_field.hidden = 0
    inc_field.read_only = 0
    inc_field.in_list_view = 1
    inc_field.label = 'Amount Includes VAT'
    dt.save()
    frappe.db.commit()
    print('✅ Campo amount_includes_vat aggiornato con successo!')
    print(f'   - hidden: {inc_field.hidden}')
    print(f'   - read_only: {inc_field.read_only}')
    print(f'   - in_list_view: {inc_field.in_list_view}')
    print(f'   - label: {inc_field.label}')
else:
    print('❌ Campo amount_includes_vat non trovato!')

frappe.destroy()
