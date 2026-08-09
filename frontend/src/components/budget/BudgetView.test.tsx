import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it } from "vitest";
import type { AnnualBudget } from "../../api/budget";
import BudgetView from "./BudgetView";

const dataset: AnnualBudget = {
  mode: "current",
  requested_as_of: null,
  cutoff_utc: null,
  read_only: false,
  budget: {
    planning_year_id: 1,
    year: 2026,
    state: "preparation",
    lock_version: 1,
    warning: null,
    history_activated_at: null,
  },
  summary: {
    currency: "EUR",
    official_basis: "net",
    proposed: "100.00",
    initial_approved: "0.00",
    approved_variations: "0.00",
    approved_current: "0.00",
    actual: "0.00",
    residual: "0.00",
    variance: "0.00",
    utilization_percentage: null,
    plafond_overrun: "0.00",
    open_expenses: 1,
    closed_expenses: 0,
    unapproved_actual_expenses: 0,
  },
  expenses: [{
    id: 42,
    title: "Licenze software",
    kind: "ordinary",
    cost_center_id: 1,
    cost_center_name: "IT",
    project_id: null,
    project_title: null,
    contract_id: null,
    contract_title: null,
    vendor_id: null,
    vendor_name: null,
    state: "open",
    closure_outcome: null,
    current_planning_row_id: 1,
    funded_plafond_expense_id: null,
    planned: "100.00",
    approved: null,
    approved_basis: null,
    actual: "0.00",
    residual: null,
    variance: null,
    variance_final: false,
    has_actual: false,
  }],
};

describe("BudgetView", () => {
  it("links each annual expense to its detail page", () => {
    render(
      <MemoryRouter>
        <BudgetView dataset={dataset} asOf="" canApprove={false} canClose={false} onAsOfChange={() => undefined} onChange={() => undefined} />
      </MemoryRouter>,
    );

    expect(screen.getByRole("link", { name: "Licenze software" })).toHaveAttribute("href", "/spese/42");
  });
});
