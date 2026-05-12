# Reference: Money Rules (VAT + Annualization)

Single reference for money normalization used by contracts, expenses, reports, and the financial engine.

## 1) Strict VAT mode

If `amount != 0`:

- `vat_rate` is mandatory (0 is valid).
- If `vat_rate` is missing on the row and missing in `MPIT Settings.default_vat_rate`, save is blocked.

If `amount == 0`:

- `vat_rate` can stay empty.

## 2) Net / VAT / Gross normalization

Let `r = vat_rate / 100`.

Input as net (`includes_vat = 0`):

- `net = amount`
- `gross = net * (1 + r)`
- `vat = gross - net`

Input as gross (`includes_vat = 1`):

- `gross = amount`
- `net = gross / (1 + r)`
- `vat = gross - net`

Rounding: `frappe.utils.flt(value, 2)`.

## 3) Contract annualization

Contracts can span multiple years. Term overlap is still used to compute year slices, but official budget totals are taken from generated `MPIT Expense Row` records.

- Contract terms are mandatory.
- Automatic sync generates `Closed` `MPIT Expense` rows for existing `MPIT Year` records only.
- Contract values are context/generator data and must not be added independently to official totals (to avoid double counting).

Billing cycle monthly equivalent:

- Monthly: `monthly = amount`
- Quarterly: `monthly = amount * 4 / 12`
- Annual: `monthly = amount / 12`

Year amount: `monthly * overlap_months`.

## 4) Expense annualization

`MPIT Expense` is strictly annual:

- Document belongs to one `year`.
- Expense rows must stay inside document year.
- Cross-year expense cases must be split into multiple annual expenses.

Row time mode:

- Point mode: `spend_date` only.
- Period mode: `start_date + end_date + distribution`.

## 5) Negative amounts

- Ordinary rows: negative values allowed only on `Actual`.
- Ordinary `Estimate` and `Quote`: negative not allowed.
- Plafond rows: negative values allowed for reduction/adjustment.
