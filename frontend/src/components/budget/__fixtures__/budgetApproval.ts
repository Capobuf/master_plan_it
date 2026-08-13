export type FixtureMeasure = Readonly<{
  net: string;
  vat: string;
  gross: string;
  official: string;
}>;

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

export const budgetProposalFixture = {
  composition: {
    schema_version: "budget-proposal-composition/v1",
    fingerprint: `sha256:${"a".repeat(64)}`,
    versions: { budget_lock_version: 7, projection_version: "annual-economic-projection/v1" },
    contributor_count: 2,
  },
  total: net("3620.00", "796.40", "4416.40"),
  contributors: [
    { source_identity: "expense-row:501", kind: "ordinary_current_planning", amount: net("120.00", "26.40", "146.40") },
    { source_identity: "plafond-allocation:81", kind: "plafond_allocation", amount: net("3500.00", "770.00", "4270.00") },
  ] satisfies FixtureContributor[],
  exclusions: [
    { source_identity: "expense-row:500", reason: "alternative_planning" },
    { source_identity: "expense-row:502", reason: "covered_by_plafond" },
  ],
  can_approve: true,
  empty_composition: false,
} as const;

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
