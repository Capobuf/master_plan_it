# How to import baseline expenses from Excel/CSV

## Recommended approach (V1)
Use Frappe **Data Import** for `MPIT Baseline Expense`.

### Why baseline is the import target
Baseline is intentionally the “raw inbox”. It can contain messy lines that you clarify later via comments and status changes.

## Steps
1) Prepare a spreadsheet with columns matching `MPIT Baseline Expense` fields.
2) In Desk: Data Import → select `MPIT Baseline Expense`.
3) Upload the file and run import.
4) Review imported rows; add clarifications as comments; set status accordingly.

## Notes
- Normalize vendors only when you have enough information; otherwise leave vendor blank and classify later.
- For recurring spend, create `MPIT Contract` once the baseline line is validated.

