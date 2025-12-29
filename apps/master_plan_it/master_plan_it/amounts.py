"""Amount calculation utilities for MPIT documents.

Provides bidirectional calculations between:
- qty × unit_price → line_total
- monthly_amount ↔ annual_amount (based on recurrence)
- amount → net/vat/gross split

The priority logic:
1. If unit_price is set: calculate from qty × unit_price based on recurrence
2. Else if monthly_amount is set and annual_amount is empty: annual = monthly × 12
3. Else if annual_amount is set and monthly_amount is empty: monthly = annual / 12
4. Else use annual_amount as the master value
"""

from __future__ import annotations

from frappe.utils import flt


def get_recurrence_multiplier(recurrence_rule: str, custom_period_months: int | None = None) -> int:
    """Return the number of periods per year for a given recurrence rule.
    
    Args:
        recurrence_rule: Monthly, Quarterly, Annual, Custom, None
        custom_period_months: Number of months per period (for Custom rule)
    
    Returns:
        Number of periods per year (e.g., 12 for Monthly, 4 for Quarterly)
    """
    if recurrence_rule == "Monthly":
        return 12
    elif recurrence_rule == "Quarterly":
        return 4
    elif recurrence_rule == "Annual":
        return 1
    elif recurrence_rule == "Custom" and custom_period_months:
        return max(1, 12 // custom_period_months)
    else:  # None or unrecognized
        return 1


def compute_amounts(
    qty: float | None,
    unit_price: float | None,
    monthly_amount: float | None,
    annual_amount: float | None,
    recurrence_rule: str | None = "Monthly",
    custom_period_months: int | None = None,
) -> dict:
    """Compute monthly and annual amounts from input values.
    
    Priority:
    1. qty × unit_price (if unit_price is set)
    2. monthly_amount → annual (if monthly is set and annual is empty)
    3. annual_amount → monthly (if annual is set)
    
    Args:
        qty: Quantity (default behavior: 1 if None)
        unit_price: Price per unit per period
        monthly_amount: Monthly amount (input or calculated)
        annual_amount: Annual amount (input or calculated)
        recurrence_rule: How often the cost recurs
        custom_period_months: Custom period in months
    
    Returns:
        dict with keys: monthly_amount, annual_amount
    """
    qty = flt(qty) or 1.0
    unit_price = flt(unit_price)
    monthly_amount = flt(monthly_amount)
    annual_amount = flt(annual_amount)
    
    multiplier = get_recurrence_multiplier(recurrence_rule, custom_period_months)
    
    # Priority 1: Calculate from qty × unit_price
    if unit_price > 0:
        line_total_per_period = qty * unit_price
        
        if recurrence_rule == "Monthly":
            # unit_price is per month
            computed_monthly = line_total_per_period
            computed_annual = flt(computed_monthly * 12, 2)
        elif recurrence_rule == "Quarterly":
            # unit_price is per quarter
            computed_annual = flt(line_total_per_period * 4, 2)
            computed_monthly = flt(computed_annual / 12, 2)
        elif recurrence_rule == "Annual":
            # unit_price is per year
            computed_annual = flt(line_total_per_period, 2)
            computed_monthly = flt(computed_annual / 12, 2)
        elif recurrence_rule == "Custom" and custom_period_months:
            # unit_price is per custom period
            periods_per_year = 12 / custom_period_months
            computed_annual = flt(line_total_per_period * periods_per_year, 2)
            computed_monthly = flt(computed_annual / 12, 2)
        else:
            # None or unknown: treat as one-time/annual
            computed_annual = flt(line_total_per_period, 2)
            computed_monthly = flt(computed_annual / 12, 2)
        
        return {
            "monthly_amount": computed_monthly,
            "annual_amount": computed_annual,
        }
    
    # Priority 2: Bidirectional monthly ↔ annual
    if monthly_amount > 0 and annual_amount == 0:
        # User entered monthly, calculate annual
        computed_annual = flt(monthly_amount * 12, 2)
        return {
            "monthly_amount": monthly_amount,
            "annual_amount": computed_annual,
        }
    
    if annual_amount > 0 and monthly_amount == 0:
        # User entered annual, calculate monthly
        computed_monthly = flt(annual_amount / 12, 2)
        return {
            "monthly_amount": computed_monthly,
            "annual_amount": annual_amount,
        }
    
    # Both set or both empty: use annual as master (or 0 if empty)
    if annual_amount > 0:
        computed_monthly = flt(annual_amount / 12, 2)
        return {
            "monthly_amount": computed_monthly,
            "annual_amount": annual_amount,
        }
    
    if monthly_amount > 0:
        computed_annual = flt(monthly_amount * 12, 2)
        return {
            "monthly_amount": monthly_amount,
            "annual_amount": computed_annual,
        }
    
    # All zeros
    return {
        "monthly_amount": 0.0,
        "annual_amount": 0.0,
    }


def compute_vat_split(
    amount: float,
    vat_rate: float | None,
    amount_includes_vat: bool = False,
) -> dict:
    """Compute net, VAT, and gross amounts from an input amount.
    
    Args:
        amount: The input amount (annual_amount typically)
        vat_rate: VAT rate as percentage (e.g., 22 for 22%)
        amount_includes_vat: Whether the input amount includes VAT
    
    Returns:
        dict with keys: amount_net, amount_vat, amount_gross
    """
    amount = flt(amount, 2)
    vat_rate = flt(vat_rate, 2)
    
    if not amount:
        return {
            "amount_net": 0.0,
            "amount_vat": 0.0,
            "amount_gross": 0.0,
        }
    
    if not vat_rate:
        # No VAT: net = gross = amount
        return {
            "amount_net": amount,
            "amount_vat": 0.0,
            "amount_gross": amount,
        }
    
    vat_multiplier = vat_rate / 100.0
    
    if amount_includes_vat:
        # Amount is gross, calculate net
        gross = amount
        net = flt(gross / (1 + vat_multiplier), 2)
        vat = flt(gross - net, 2)
    else:
        # Amount is net, calculate gross
        net = amount
        vat = flt(net * vat_multiplier, 2)
        gross = flt(net + vat, 2)
    
    return {
        "amount_net": net,
        "amount_vat": vat,
        "amount_gross": gross,
    }


def compute_line_amounts(
    qty: float | None,
    unit_price: float | None,
    monthly_amount: float | None,
    annual_amount: float | None,
    vat_rate: float | None,
    amount_includes_vat: bool = False,
    recurrence_rule: str | None = "Monthly",
    custom_period_months: int | None = None,
    overlap_months: int = 12,
) -> dict:
    """Compute all amounts for a budget line or expense.
    
    This is the main entry point that combines amount calculation with VAT split.
    
    Args:
        qty: Quantity
        unit_price: Price per unit per period
        monthly_amount: Monthly amount
        annual_amount: Annual amount
        vat_rate: VAT rate as percentage
        amount_includes_vat: Whether input amounts include VAT
        recurrence_rule: Recurrence rule for unit_price calculation
        custom_period_months: Custom period months
        overlap_months: Number of months the period overlaps with fiscal year (1-12)
    
    Returns:
        dict with all computed values
    """
    # Step 1: Compute monthly/annual amounts
    amounts = compute_amounts(
        qty=qty,
        unit_price=unit_price,
        monthly_amount=monthly_amount,
        annual_amount=annual_amount,
        recurrence_rule=recurrence_rule,
        custom_period_months=custom_period_months,
    )
    
    # Step 2: Compute VAT split based on annual amount
    vat_split = compute_vat_split(
        amount=amounts["annual_amount"],
        vat_rate=vat_rate,
        amount_includes_vat=amount_includes_vat,
    )
    
    # Step 3: Compute annualized values based on overlap
    # If overlap is less than 12 months, scale the amounts proportionally
    overlap_ratio = overlap_months / 12.0 if overlap_months else 1.0
    
    annual_net = flt(vat_split["amount_net"] * overlap_ratio, 2)
    annual_vat = flt(vat_split["amount_vat"] * overlap_ratio, 2)
    annual_gross = flt(vat_split["amount_gross"] * overlap_ratio, 2)
    
    return {
        "monthly_amount": amounts["monthly_amount"],
        "annual_amount": amounts["annual_amount"],
        "amount_net": vat_split["amount_net"],
        "amount_vat": vat_split["amount_vat"],
        "amount_gross": vat_split["amount_gross"],
        # Annualized values consider overlap with fiscal year
        "annual_net": annual_net,
        "annual_vat": annual_vat,
        "annual_gross": annual_gross,
    }
