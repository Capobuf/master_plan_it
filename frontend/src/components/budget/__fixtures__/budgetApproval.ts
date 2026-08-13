import type { AnnualBudget, BudgetApprovalPreview } from "../../../api/budget";
import type { EconomicMeasure } from "../../../api/projection";

export type FixtureMeasure = Readonly<EconomicMeasure>;

const net = (value: string, vat: string, gross: string): FixtureMeasure => ({
  net: value,
  vat,
  gross,
  official: value,
});

const composition = {
  schema_version: "budget-proposal-composition/v1",
  fingerprint: `sha256:${"a".repeat(64)}`,
  versions: { budget_lock_version: 7, projection_version: "annual-economic-projection/v1" },
  contributor_count: 2,
} as const;

export const budgetProposalFixture: BudgetApprovalPreview = {
  planning_year: { id: 25, year_label: 2026, state: "preparation", lock_version: 7 },
  currency: "EUR",
  basis: "net",
  composition,
  total: net("3620.00", "796.40", "4416.40"),
  contributors: [
    {
      source_identity: "expense-row:501",
      kind: "ordinary_current_planning",
      expense: { id: 81, title: "Licenze annuali" },
      row: { id: 501, type: "quote", description: "Preventivo scelto" },
      plafond: null,
      dimensions: { cost_center: { id: 9, name: "Infrastruttura" }, vendor: null, project: null, contract: null },
      amount: net("120.00", "26.40", "146.40"),
      source_lock_version: 4,
      drill_down: { href: "/api/v1/expenses/81", authorized: true },
    },
    {
      source_identity: "plafond-allocation:82",
      kind: "plafond_allocation",
      expense: { id: 82, title: "Infrastruttura condivisa" },
      row: null,
      plafond: { id: 82, title: "Infrastruttura condivisa" },
      dimensions: { cost_center: { id: 9, name: "Infrastruttura" }, vendor: null, project: null, contract: null },
      amount: net("3500.00", "770.00", "4270.00"),
      source_lock_version: 5,
      drill_down: { href: "/api/v1/expenses/82", authorized: true },
    },
  ],
  exclusions: [
    {
      source_identity: "expense-row:500",
      reason: "alternative_planning",
      expense: { id: 81, title: "Licenze annuali" },
      row: { id: 500, type: "estimate", description: "Stima iniziale" },
      amount: net("100.00", "22.00", "122.00"),
      detail: "Una sola pianificazione corrente per Spesa contribuisce alla proposta.",
      drill_down: { href: "/api/v1/expenses/81", authorized: true },
    },
    {
      source_identity: "expense-row:502",
      reason: "covered_by_plafond",
      expense: { id: 83, title: "Licenze coperte" },
      row: { id: 502, type: "quote", description: "Copertura prevista" },
      amount: net("4200.00", "924.00", "5124.00"),
      detail: "La pianificazione è coperta dall’allocazione del Plafond.",
      drill_down: { href: "/api/v1/expenses/83", authorized: true },
    },
  ],
  can_approve: true,
  empty_composition: false,
};

export const annualBudgetFixture: AnnualBudget = {
  planning_year: budgetProposalFixture.planning_year,
  currency: budgetProposalFixture.currency,
  basis: budgetProposalFixture.basis,
  economic_base: { basis: "net", locked_at: null },
  proposal: { composition: budgetProposalFixture.composition, total: budgetProposalFixture.total },
  approved_snapshot: null,
  informative_evaluations: net("100.00", "22.00", "122.00"),
  actuals: net("0.00", "0.00", "0.00"),
  actions: { can_view_approval_preview: true, can_approve: true, can_annul_active_approval: false },
};
