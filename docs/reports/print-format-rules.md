# Print Format Rules

## Profiles

- `Executive`: up to 6 columns, no source row IDs, no VAT/gross detail.
- `Standard`: up to 8 columns, meeting-friendly totals and short legends.
- `Detailed`: up to 10 columns, operational detail, landscape allowed.
- `Audit`: export-friendly technical detail, still printable.

## A4 Rules

Default report print templates use A4 portrait with compact typography and
repeat table headers. Wider audit-style outputs may switch to A4 landscape by
changing only the `@page` size rule.

## Table Grouping

Reports should use row-based grouping instead of month-as-column or
wide-matrix layouts. Long tables must keep header rows repeatable and avoid
page breaks inside rows.

## No Business Logic In Templates

HTML print templates may:

- show applied filters;
- loop over rows;
- show already-computed summary values;
- conditionally display already-computed fields.

HTML print templates must not:

- recalculate actuals or forecasts;
- recalculate variance;
- infer project stages;
- calculate plafond consumption;
- derive statuses.

## Readability

Use compact but legible font sizes, stable table widths, right-aligned numeric
columns, and a short footer explaining that values come from the Python
financial engine.
