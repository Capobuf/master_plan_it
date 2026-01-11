# ADR 0010: Report PDF Printing v1

**Status:** Accepted  
**Date:** 2025-12-28  
**Deciders:** Development Team  
**Tags:** reports, printing, pdf, ux

## Context

MPIT includes 6 Script Reports that users need to print or export as PDF. The main challenge is that several reports have many columns (8-12), which causes:

1. **PDF cutoff**: columns get cut off on the right side when printing on A4
2. **Illegible output**: text becomes too small when trying to fit all columns
3. **Inconsistent experience**: no standard way to control print layout

Users requested a way to:
- Print reports in a readable format
- Control which columns appear in print
- Adjust page orientation and density
- Get a warning when the output might be problematic

## Decision

We implement **Report Print Formats** (Jinja2 HTML templates) for all 6 MPIT reports with:

### 1. Dynamic Column Rendering

Templates iterate over the report's `columns` output rather than hardcoding column names. This ensures:
- Print output matches report output
- Adding/removing columns in Python automatically reflects in print
- No drift between UI and print views

### 2. Column Profiles (print_profile filter)

Three profiles control which columns appear:

| Profile | Purpose |
|---------|---------|
| **Standard** | Essential columns for quick overview |
| **Compact** | Minimal columns, fits on narrow printouts |
| **All** | Every non-hidden column |

Profile definitions are embedded in each template as a simple fieldname list, making them easy to adjust per-report.

### 3. Orientation Control (print_orientation filter)

| Option | Behavior |
|--------|----------|
| **Auto** | Portrait if <8 columns, Landscape if ≥8 |
| **Portrait** | Force portrait regardless of column count |
| **Landscape** | Force landscape regardless of column count |

### 4. Density Control (print_density filter)

| Density | Font | Padding | Use Case |
|---------|------|---------|----------|
| **Normal** | 10px | 6px | Default, readable |
| **Compact** | 9px | 4px | More columns |
| **Ultra** | 8px | 3px | Maximum columns |

### 5. Non-Blocking Warning

When column count exceeds threshold (10), a warning banner appears:
> "Many columns detected. For better readability, consider using Compact profile, Landscape orientation with Ultra density, or export to Excel/CSV."

This is informational — printing proceeds regardless.

## Alternatives Considered

### Alternative 1: Custom Column Selector UI

**Rejected.** Would require:
- Custom JavaScript for column selection
- Server-side handling of column preferences
- Per-user column storage
- Significant UI complexity

Our approach (profile-based) is simpler and covers the main use cases.

### Alternative 2: Server-Side PDF Generation with Column Wrapping

**Rejected.** Complex to implement and would require:
- Custom PDF generation logic
- Handling of column overflow/wrapping
- Loss of browser print dialog benefits

### Alternative 3: Force CSV/Excel Export for Wide Reports

**Rejected.** Users explicitly want PDF output for:
- Email attachments
- Physical printing
- Archive/compliance

Export remains available as a fallback.

## Consequences

### Positive

1. **Consistent experience**: All 6 reports use the same print filter pattern
2. **File-first**: Templates are in version control, deployable without UI configuration
3. **Predictable output**: Profile names (Standard/Compact/All) are self-documenting
4. **Graceful degradation**: Warning helps users before they waste paper/time
5. **No custom JavaScript**: Uses native Frappe print functionality
6. **No external dependencies**: Relies on wkhtmltopdf already in Frappe stack

### Negative

1. **Profile maintenance**: Adding columns to a report may require updating profile definitions
2. **Limited customization**: Users can't create custom column selections (must use predefined profiles)
3. **wkhtmltopdf limitations**: Some CSS features don't work perfectly in wkhtmltopdf

### Neutral

1. **Filter count increases**: Each report now has 3 additional filters (profile/orientation/density)
2. **Template complexity**: Jinja2 templates are more complex than simple hardcoded HTML

## Implementation Details

### Files Changed

For each of the 6 reports:
- `.py`: Columns normalized to dict format with explicit `fieldname` (3 reports converted)
- `.js`: Filters added/updated (6 reports)
- `.html`: Template created/rewritten with dynamic rendering (6 templates)
- `.json`: Filters removed (consolidated in .js) (3 reports)

### Column Normalization

Three reports used string-format columns (`"Label:Type:Width"`). These were converted to dict format:

```python
# Before
columns = [_("Budget") + ":Link/MPIT Budget:180"]

# After
columns = [{"label": _("Budget"), "fieldname": "budget", "fieldtype": "Link", "options": "MPIT Budget", "width": 180}]
```

This ensures `fieldname` is explicit and matches data keys, enabling reliable dynamic rendering.

### Template Structure

All templates follow this structure:

```jinja2
{# 1. Read print filters #}
{%- set profile = (filters.print_profile or "Standard")|string %}

{# 2. Define profile column lists #}
{%- set profile_columns = {"Standard": [...], "Compact": [...], "All": []} %}

{# 3. Filter columns by profile #}
{%- set visible_columns = [...filtered...] %}

{# 4. Compute orientation #}
{%- set page_orientation = "landscape" if col_count >= 8 else "portrait" %}

{# 5. Render table iterating over visible_columns #}
```

## References

- [Reference: Printing Reports PDF](../reference/08-printing-reports-pdf.md)
- [Frappe Report Print Format Documentation](https://frappeframework.com/docs/user/en/desk/reports/report-builder#print-format)
