# MPIT Budget Engine v2 — UI/UX Operating Spec

Source of truth: `docs/DECISIONS_AND_LOGIC_MPTI_BudgetEngine_v2.md`.  
Note: `EPIC_MPTI_BudgetEngine_v2.md` non trovato nel repo; il piano segue le decisioni confermate.

## A) User flows (essenziali)
- **Setup Cost Centers (tree)**: crea radice "All Cost Centers" (group) → aggiungi sottocentri attivi; marca `is_group` solo per nodi aggregatori; usa filtro `is_active` per pulire la vista.
- **Contract (ricorrente / rate / spread)**: inserisci titolo, vendor, category, cost center, auto_renew. Se ricorrente: compila billing_cycle + current_amount (+ VAT). Se spread: imposta spread_months + spread_start_date (nasconde billing/rate). Se rate schedule: aggiungi righe con effective_from e importo (nasconde spread). Imposta start/end/next_renewal_date (auto se mancante). Allega file.
- **Project (stima → quote → delta/saving)**: inserisci titolo, cost_center, date (start/end). Aggiungi allocations per anno/category. Aggiungi quotes per category (stato Approved/Informational). Le varianti approvate sono registrate come MPIT Actual Entry con entry_kind=Delta (progetto) per change log. Totali calcolati automaticamente (planned/quoted/expected).
- **Budget Baseline + Forecast**: crea Baseline (workflow Approved, non refreshabile). Crea Forecast (budget_kind=Forecast) → `Refresh from Sources` genera righe da contracts/projects; aggiungi linee manuali line_kind=Allowance per caps per cost center/category; `Set Active` marca forecast unico per anno. Totali net/gross in sola lettura.
- **Allowance lines nel Budget**: in tab Lines, aggiungi row line_kind=Allowance, category + cost_center, monthly/annual amount (net). VAT opzionale. `is_active` controlla inclusione.
- **Variance/Exception Entry**:
  - **Delta**: entry_kind=Delta, collega contract *oppure* project, category obbligatoria, importo può essere +/-; vendor opzionale; cost_center autocompilato da link. Status Recorded/Verified.
  - **Allowance Spend**: entry_kind=Allowance Spend, richiede cost_center, category, importo >= 0 (spesa reale), vendor opzionale. Nessun contract/project. Consuma il cap.
- **Consultazione**: Workspace “Master Plan IT” con shortcuts a Overview Dashboard, Budget Diff, Baseline vs Exceptions, Monthly Plan vs Exceptions, Projects Planned vs Exceptions, Renewals Window. Quick actions: Create Forecast, Refresh Forecast, Record Allowance Spend.

## B) Information Architecture
- **Primari (menu/Workspace)**: Cost Centers, Contracts, Projects, Budgets, Actual Entries (Variance), Reports (Budget Diff, Plan vs Exceptions, Renewals Window), Dashboard Overview.
- **Secondari/tecnici**: Settings, User Preferences, Categories, Vendors, Years.
- **Nomenclature**: usare “Variance / Exception Entry” come label utente per MPIT Actual Entry; “Allowance (Cap)” per line_kind=Allowance; “Forecast”/“Baseline” per budget_kind; evitare termini contabili complessi (net/gross usati solo come visualizzazione).

## C) Form layout (tabs/sections)
- **MPIT Cost Center**: Tab Overview con Cost Center Name, Parent, Is Group. Sezione Advanced (collassabile) con old_parent, lft/rgt readonly.
- **MPIT Contract**: Tabs → Overview (title, vendor, category, cost_center, contract_kind, status, auto_renew); Pricing (billing_cycle, current_amount, includes VAT, vat_rate, net/vat/gross readonly); Spread (spread_months, spread_start_date, spread_end_date readonly) visibile solo se spread_months valorizzato e nasconde billing/rate; Rate Schedule (table) visibile solo se nessuno spread e serve per price changes; Renewal & Dates (start_date, end_date, next_renewal_date); Notes/Attachment.
- **MPIT Project**: Tabs → Overview (title, status, cost_center, start/end, description); Financials (planned_total_net, quoted_total_net, expected_total_net readonly, financial_summary HTML); Allocations (table); Quotes (table). Progressive disclosure: dates optional but coerenti.
- **MPIT Budget**: Tabs → Overview (year reqd, title, budget_kind, workflow_state, is_active_forecast shown solo per Forecast, baseline_ref solo per Forecast, buttons Set Active/Refresh solo per Forecast); Lines (table with helper text per allowance caps/manual lines); Totals (collassabile) con monthly/annual/net/vat/gross readonly.
- **MPIT Budget Line**: Sections → Overview (line_kind, source_key readonly, category reqd, vendor, description); Pricing (qty, unit_price, monthly_amount, annual_amount); VAT (includes, vat_rate, net/vat/gross readonly, collassabile); Recurrence & Period (recurrence_rule, period_start/end); Annualized Totals (readonly); Links (contract/project/cost_center); Flags (is_generated readonly). For allowances: cost_center consigliato, recurrence "Monthly/Annual/None".
- **MPIT Actual Entry (Variance / Exception Entry)**: Tabs → Overview (posting_date reqd, year readonly, status, entry_kind, description); Amounts (amount reqd, includes VAT, vat_rate, net/vat/gross readonly); Links (category reqd; contract/project only if Delta; cost_center mandatory if Allowance Spend; budget/budget_line_ref readonly utility). Help: Delta = contract/project only; Allowance Spend = cost center only.
- **MPIT Settings**: Tab Defaults with currency, renewal_window_days; Naming section (budget/project/actual prefixes+digits); VAT defaults; Print settings.

## D) List view / filters / indicators
- **Cost Center**: list columns name, parent, is_group.
- **Contract**: list columns title, vendor, category, next_renewal_date, status; filters status, auto_renew, category, vendor; indicator by status (Active=green, Pending Renewal=orange, Expired/Cancelled=red).
- **Project**: list columns title, cost_center, status, planned_total_net; filters status, cost_center, category (via allocations), year (via allocations); indicator Approved/In Progress=blue/green, On Hold=orange, Cancelled/Completed=grey.
- **Budget**: list columns name, year, budget_kind, is_active_forecast, workflow_state; filters year, budget_kind, is_active_forecast; indicator Baseline=purple, Active Forecast=green, other Forecast=blue.
- **Budget Line (grid)**: show line_kind, category, vendor, monthly_amount, annual_amount; no is_active filter needed.
- **Actual Entry**: list columns posting_date, entry_kind, category, contract/project/cost_center, amount, status; filters year, entry_kind, status, category, cost_center; indicator Delta=blue, Allowance Spend=green, Verified=bold.

## E) Dashboard & Workspace
- Workspace “Master Plan IT”: keep Overview header; shortcuts: Overview Dashboard (view), Budget Diff, Baseline vs Exceptions, Monthly Plan vs Exceptions, Projects Planned vs Exceptions, Renewals Window, Variance Entry (MPIT Actual Entry), Create Budget (MPIT Budget). Quick actions (URL type) for “Create Forecast Budget” (/app/mpit-budget/new?budget_kind=Forecast) and “Refresh Active Forecast” (/app/query-report/MPIT%20Current%20Plan%20vs%20Exceptions?is_active_forecast=1) if acceptable; “Record Allowance Spend” -> new MPIT Actual Entry with entry_kind=Allowance Spend preset via route options.
- Remove any V1 references (Baseline Expense, Amendments, portfolio bucket, custom_period_months) from workspace/charts/reports.

## F) Hard UX rules
- Cost Center mandatory per entries and lines; Category non utilizzata.
- Cost Center central for allowances: required on Allowance Spend and suggested on allowance budget lines.
- Spread lives only on Contract; mutually exclusive with rate schedule and billing cycle.
- Rate schedule rows strictly increasing effective_from; gaps allowed.
- No actual ledger beyond variance/allowance; net is master, gross is computed view-only.

## G) Changeset outline (per DocType)
- Adjust labels/help to user-facing names (Variance/Exception, Allowance cap).
- Apply depends_on/mandatory_depends_on to hide/show spread vs rate, Delta vs Allowance Spend links.
- Reduce visible fields by default via collapsible sections; keep readonly computations grouped.
- Set list view and filter metadata to surface key fields and statuses.
- Workspace shortcuts updated to v2 flows and quick actions.
