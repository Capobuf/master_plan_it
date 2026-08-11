import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it, vi } from "vitest";
import { listExpenses } from "../../api/expenses";
import ExpenseRegister from "./ExpenseRegister";

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({ data: { tenant: { id: 1 } }, loading: false, hasAbility: () => true }),
}));
vi.mock("../../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({
    activePlanningYears: [{ id: 7, year_label: 2026, active: true }],
    selectedPlanningYearId: 7,
    loading: false,
    selectPlanningYear: vi.fn(),
  }),
}));
vi.mock("../../api/expenses", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/expenses")>();
  return {
    ...actual,
    listExpenses: vi.fn(),
    listExpenseCostCenters: vi.fn().mockResolvedValue([]),
    listExpenseVendors: vi.fn().mockResolvedValue([]),
    listExpenseContracts: vi.fn().mockResolvedValue([]),
  };
});
vi.mock("../../api/projects", () => ({ listProjectOptions: vi.fn().mockResolvedValue([]) }));

describe("ExpenseRegister", () => {
  it("ignores a legacy local year and always requests the global Planning Year", async () => {
    vi.mocked(listExpenses).mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
      links: { first: null, last: null, prev: null, next: null },
      totals: { net: "0.00", vat: "0.00", gross: "0.00", currency: "EUR", official_basis: "net" },
      column_preferences: [],
    });
    render(<MemoryRouter initialEntries={["/?planning_year_id=999"]}><ExpenseRegister /></MemoryRouter>);

    await waitFor(() => expect(listExpenses).toHaveBeenCalledWith(expect.objectContaining({ planning_year_id: 7, per_page: 25 })));
    expect(screen.queryByLabelText(/Anno di pianificazione/i)).not.toBeInTheDocument();
    expect(await screen.findByText("Nessuna spesa")).toBeInTheDocument();
  });

  it("returns to page one and clears page selection when page size changes", async () => {
    vi.mocked(listExpenses).mockClear();
    vi.mocked(listExpenses).mockImplementation(async (params) => ({
      data: [{
        id: 41,
        planning_year_id: 7,
        planning_year_label: 2026,
        cost_center_id: null,
        cost_center_name: null,
        kind: "ordinary",
        state: "open",
        title: "Cloud platform services",
        project_id: null,
        project_title: null,
        project_current: false,
        contract_id: null,
        contract_title: null,
        contract_current: false,
        vendor_count: 0,
        vendor_summary: "—",
        row_count: 1,
        lock_version: 1,
        totals: { net: "100.00", vat: "22.00", gross: "122.00", currency: "EUR", official_basis: "net" },
      }],
      meta: { current_page: params.page ?? 1, last_page: 3, per_page: params.per_page ?? 25, total: 51 },
      links: { first: null, last: null, prev: null, next: null },
      totals: { net: "100.00", vat: "22.00", gross: "122.00", currency: "EUR", official_basis: "net" },
      column_preferences: [{ key: "net", visible: true }],
    }));

    render(<MemoryRouter initialEntries={["/?q=Cloud&page=3"]}><ExpenseRegister /></MemoryRouter>);

    const rowCheckbox = await screen.findByRole("checkbox", { name: "Seleziona Cloud platform services" });
    fireEvent.click(rowCheckbox);
    expect(screen.getByText("1 Spese selezionate")).toBeInTheDocument();

    fireEvent.change(screen.getByRole("combobox", { name: "Righe per pagina" }), { target: { value: "50" } });

    await waitFor(() => expect(listExpenses).toHaveBeenLastCalledWith(expect.objectContaining({
      planning_year_id: 7,
      q: "Cloud",
      page: 1,
      per_page: 50,
    })));
    expect(screen.queryByText(/Spese selezionate/)).not.toBeInTheDocument();
  });
});
