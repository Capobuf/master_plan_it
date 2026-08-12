import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { applyBudgetApproval, type AnnualBudget, type AnnualBudgetExpense } from "../../api/budget";
import BudgetApprovalModal from "./BudgetApprovalModal";

vi.mock("../../api/budget", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/budget")>();
  return { ...actual, applyBudgetApproval: vi.fn() };
});

function expense(id: number, title: string, lockVersion: number): AnnualBudgetExpense {
  return {
    id,
    title,
    kind: "ordinary",
    cost_center_id: 10,
    cost_center_name: "IT",
    project_id: null,
    project_title: null,
    contract_id: null,
    contract_title: null,
    vendor_id: null,
    vendor_name: null,
    currency: "EUR",
    basis: "net",
    totals: {
      current_planning: { net: "100.00", vat: "22.00", gross: "122.00", official: "100.00" },
      actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
    },
    current_planning_row_id: null,
    funded_plafond_expense_id: null,
    planned: "100.00",
    approved: null,
    approved_basis: null,
    actual: "0.00",
    residual: null,
    variance: null,
    has_actual: false,
    lock_version: lockVersion,
  };
}

const dataset: AnnualBudget = {
  mode: "current",
  requested_as_of: null,
  cutoff_utc: null,
  read_only: false,
  budget: {
    planning_year_id: 20,
    year: 2026,
    state: "preparation",
    lock_version: 2,
    warning: null,
    history_activated_at: null,
  },
  summary: {
    currency: "EUR",
    official_basis: "net",
    proposed: "200.00",
    initial_approved: "0.00",
    approved_variations: "0.00",
    approved_current: "0.00",
    actual: "0.00",
    residual: "0.00",
    variance: "0.00",
    utilization_percentage: null,
    unapproved_actual_expenses: 0,
  },
  currency: "EUR",
  basis: "net",
  totals: {
    current_planning: { net: "200.00", vat: "44.00", gross: "244.00", official: "200.00" },
    actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
  },
  expenses: [expense(101, "Expense A", 3), expense(102, "Expense B", 4)],
};

describe("BudgetApprovalModal", () => {
  it("distinguishes an explicit zero approval from an unselected expense", async () => {
    vi.mocked(applyBudgetApproval).mockResolvedValue(dataset);
    render(
      <BudgetApprovalModal
        dataset={dataset}
        isOpen
        onClose={vi.fn()}
        onApplied={vi.fn()}
      />,
    );

    fireEvent.click(screen.getByRole("checkbox", { name: /Expense A/ }));
    fireEvent.change(screen.getByLabelText("Nuovo approvato Expense A"), { target: { value: "0.00" } });
    fireEvent.click(screen.getByRole("button", { name: "Apri calendario: Data di efficacia" }));
    fireEvent.click(screen.getByLabelText(/Agosto 9, 2026/i));
    fireEvent.click(screen.getByRole("button", { name: "Registra decisione" }));

    await waitFor(() => {
      expect(applyBudgetApproval).toHaveBeenCalledWith(20, {
        budget_lock_version: 2,
        effective_date: "2026-08-09",
        items: [{
          expense_id: 101,
          expense_lock_version: 3,
          approved_amount: "0.00",
        }],
      });
    });
  });
});
