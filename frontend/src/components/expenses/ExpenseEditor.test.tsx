import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it, vi } from "vitest";
import * as expenseApi from "../../api/expenses";
import ExpenseEditor from "./ExpenseEditor";

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    data: { tenant: { id: 1 }, abilities: [] },
    loading: false,
    hasAbility: () => true,
  }),
}));

const selectPlanningYear = vi.fn();
vi.mock("../../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({
    selectedPlanningYear: { id: 7, year_label: 2026, active: true },
    selectedPlanningYearId: 7,
    selectPlanningYear,
  }),
}));

vi.mock("../../api/expenses", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/expenses")>();
  return {
    ...actual,
    getExpense: vi.fn(),
    listExpenseVendors: vi.fn().mockResolvedValue([]),
    listExpenseCostCenters: vi.fn().mockResolvedValue([{ id: 3, name: "Operations" }]),
    listExpensePlanningYears: vi.fn().mockResolvedValue([
      { id: 7, label: 2026, active: true },
      { id: 8, label: 2027, active: true },
    ]),
    listExpenseContracts: vi.fn().mockResolvedValue([]),
    listEligiblePlafondExpenses: vi.fn().mockResolvedValue([]),
  };
});

vi.mock("../../api/projects", () => ({ listProjectOptions: vi.fn().mockResolvedValue([]) }));

describe("ExpenseEditor", () => {
  it("uses the global year read-only and adds rows inline", async () => {
    render(<MemoryRouter><ExpenseEditor /></MemoryRouter>);

    expect(await screen.findByText("2026")).toBeInTheDocument();
    expect(screen.queryByRole("combobox", { name: /Anno/ })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "+ Aggiungi Riga" }));
    fireEvent.click(screen.getByRole("button", { name: "+ Aggiungi Riga" }));
    expect(screen.getAllByRole("group", { name: /Riga/ })).toHaveLength(3);
  });

  it("keeps an explicit future destination year only for credit flow", async () => {
    vi.mocked(expenseApi.getExpense).mockResolvedValue({
      id: 11,
      planning_year_id: 7,
      planning_year_label: 2026,
      cost_center_id: 3,
      kind: "ordinary",
      title: "Spesa originaria",
      project_id: null,
      contract_id: null,
    } as never);
    render(<MemoryRouter initialEntries={["/?credit_for_expense_id=11"]}><ExpenseEditor /></MemoryRouter>);

    const year = await screen.findByRole("combobox", { name: "Anno destinazione" });
    await waitFor(() => expect(year).toHaveValue("8"));
    expect(screen.queryByRole("option", { name: "2026" })).not.toBeInTheDocument();
    expect(screen.getByRole("option", { name: "2027" })).toBeInTheDocument();
    expect(expenseApi.getExpense).toHaveBeenCalledWith(11, 7);
  });
});
