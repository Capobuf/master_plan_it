import type { AnnualBudget, BudgetApprovalPreview } from "../../../api/budget";
import type { EconomicMeasure } from "../../../api/projection";

export type FixtureMeasure = Readonly<EconomicMeasure>;

/** Foundation fixtures retained for the approval, history and annulment slices. */
export type FixtureContributor = Readonly<{
  source_identity: string;
  kind: "ordinary_current_planning" | "plafond_allocation";
  amount: FixtureMeasure;
}>;

export type FixtureBlocker = Readonly<{
  source_identity: string;
  category: "actuals" | "extra_budget" | "rectifications" | "closures";
  redacted: boolean;
  amount: FixtureMeasure | null;
  drill_down: Readonly<{ authorized: boolean; href: string | null }>;
}>;

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
  surface_fingerprint: `sha256:${"c".repeat(64)}`,
  composition,
  total: net("3620.00", "796.40", "4416.40"),
  contributors: [
    {
      source_identity: "expense-row:501",
      kind: "ordinary_current_planning",
      expense: { id: 81, title: "Licenze annuali" },
      row: { id: 501, type: "quote", description: "Preventivo scelto" },
      plafond: null,
      dimensions: { cost_center: { id: 9, name: "Infrastruttura" }, vendor: { id: 14, name: "Fornitore Demo" }, project: { id: 18, title: "Programma cloud" }, contract: { id: 44, title: "Contratto cloud" } },
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
      drill_down: { href: "/api/v1/plafonds/82", authorized: true },
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
    {
      source_identity: "deleted-source:opaque",
      reason: "soft_deleted",
      expense: null,
      row: null,
      amount: net("999.99", "220.00", "1219.99"),
      detail: null,
      drill_down: { href: null, authorized: false },
    },
  ],
  can_approve: true,
  empty_composition: false,
};

export const annualBudgetFixture: AnnualBudget = {
  planning_year: budgetProposalFixture.planning_year,
  currency: budgetProposalFixture.currency,
  basis: budgetProposalFixture.basis,
  surface_fingerprint: budgetProposalFixture.surface_fingerprint,
  economic_base: { basis: "net", locked_at: null },
  proposal: { composition: budgetProposalFixture.composition, total: budgetProposalFixture.total },
  approved_snapshot: null,
  informative_evaluations: net("100.00", "22.00", "122.00"),
  actuals: net("0.00", "0.00", "0.00"),
  actions: { can_view_approval_preview: true, can_approve: true, can_annul_active_approval: false },
};

export const activeApprovalFixture = {
  id: 91,
  status: "active",
  effective_date: "2026-08-13",
  recorded_at: "2026-08-13T10:30:00Z",
  approved_by: { id: 5, name: "Mario Rossi" },
  note: "Approvazione iniziale",
  total: budgetProposalFixture.total,
} as const;

export const annulledApprovalFixture = {
  ...activeApprovalFixture,
  status: "annulled",
  annulled_at: "2026-08-13T11:00:00Z",
  annulled_by: { id: 5, name: "Mario Rossi" },
  annulment_note: "Correzione della proposta",
} as const;

const rawOverlap: FixtureBlocker = {
  source_identity: "expense-row:502",
  category: "actuals",
  redacted: false,
  amount: net("0.00", "0.00", "0.00"),
  drill_down: { authorized: true, href: "/api/v1/budget/25/approvals/91/blockers/expense-row:502" },
};

const redactedOverlap: FixtureBlocker = {
  source_identity: "blocked-source:V_VXvYJHFyd4kJX8QvM_RQ",
  category: "actuals",
  redacted: true,
  amount: null,
  drill_down: { authorized: false, href: null },
};

/** A single source deliberately remains in both canonical non-exclusive blocker groups. */
export const blockerOverlapFixtures = {
  raw: {
    actuals: [rawOverlap],
    extra_budget: [{ ...rawOverlap, category: "extra_budget" }],
    rectifications: [],
    closures: [],
  },
  redacted: {
    actuals: [redactedOverlap],
    extra_budget: [{ ...redactedOverlap, category: "extra_budget" }],
    rectifications: [],
    closures: [],
  },
} as const;

// Approval fixtures intentionally contain no caller-selected items or editable approved amounts.
export type ApprovalConfirmationFixture = Readonly<{
  effective_date: string;
  note: string | null;
  composition: typeof budgetProposalFixture.composition;
}>;
