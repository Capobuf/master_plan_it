import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { listExpenses } from "../../api/expenses";
import ExpenseRegister from "./ExpenseRegister";

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({ data: { tenant: { id: 1 } }, loading: false, hasAbility: () => true }),
}));
let selectedPlanningYearId = 7;
vi.mock("../../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({
    activePlanningYears: [
      { id: 7, year_label: 2026, active: true },
      { id: 8, year_label: 2027, active: true },
    ],
    selectedPlanningYearId,
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
  beforeEach(() => {
    selectedPlanningYearId = 7;
    vi.clearAllMocks();
  });

  it("ignores a legacy local year and always requests the global Planning Year", async () => {
    vi.mocked(listExpenses).mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
      links: { first: null, last: null, prev: null, next: null },
      totals: {
        current_planning: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
        actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
      },
      column_preferences: [],
    });
    render(<MemoryRouter initialEntries={["/?planning_year_id=999"]}><ExpenseRegister /></MemoryRouter>);

    await waitFor(() => expect(listExpenses).toHaveBeenCalledWith(expect.objectContaining({ planning_year_id: 7, per_page: 25 })));
    expect(screen.queryByLabelText(/Anno di pianificazione/i)).not.toBeInTheDocument();
    expect(await screen.findByText("Nessuna spesa")).toBeInTheDocument();
  });

  it("returns to page one and clears page selection when page size changes", async () => {
    vi.mocked(listExpenses).mockImplementation(async (params) => ({
      data: [{
        id: 41,
        planning_year_id: 7,
        economic_year_label: 2026,
        cost_center_id: 3,
        cost_center_name: "Operations",
        kind: "ordinary",
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
        currency: "EUR",
        basis: "net",
        totals: {
          current_planning: { net: "100.00", vat: "22.00", gross: "122.00", official: "100.00" },
          actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
        },
      }],
      meta: { current_page: params?.page ?? 1, last_page: 3, per_page: params?.per_page ?? 25, total: 51 },
      links: { first: null, last: null, prev: null, next: null },
      totals: {
        current_planning: { net: "100.00", vat: "22.00", gross: "122.00", official: "100.00" },
        actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
      },
      column_preferences: [{ key: "net", visible: true }],
    }));

    render(<MemoryRouter initialEntries={["/?q=Cloud&page=3"]}><ExpenseRegister /></MemoryRouter>);

    const rowCheckbox = await screen.findByRole("checkbox", { name: "Seleziona Cloud platform services" });
    expect(screen.getByRole("button", { name: "Colonne" })).toBeInTheDocument();
    expect(document.querySelector("[data-expense-filters]")).toContainElement(screen.getByRole("button", { name: "Colonne" }));
    expect(screen.queryByText(/Spese selezionate/)).not.toBeInTheDocument();
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

  it("never renders a late response from the previously selected year", async () => {
    let resolve2026!: (value: Awaited<ReturnType<typeof listExpenses>>) => void;
    const responseFor = (year: number, title: string): Awaited<ReturnType<typeof listExpenses>> => ({
      data: [{
        id: year,
        planning_year_id: year,
        economic_year_label: year === 7 ? 2026 : 2027,
        cost_center_id: 3,
        cost_center_name: "Operations",
        kind: "ordinary",
        title,
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
        currency: "EUR",
        basis: "net",
        totals: {
          current_planning: { net: "100.00", vat: "22.00", gross: "122.00", official: "100.00" },
          actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
        },
      }],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
      links: { first: null, last: null, prev: null, next: null },
      totals: {
        current_planning: { net: "100.00", vat: "22.00", gross: "122.00", official: "100.00" },
        actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
      },
      column_preferences: [{ key: "net", visible: true }],
    });
    vi.mocked(listExpenses).mockImplementation((params) => params?.planning_year_id === 7
      ? new Promise((resolve) => { resolve2026 = resolve; })
      : Promise.resolve(responseFor(8, "Spesa 2027")));

    const view = render(<MemoryRouter><ExpenseRegister /></MemoryRouter>);
    await waitFor(() => expect(listExpenses).toHaveBeenCalledWith(expect.objectContaining({ planning_year_id: 7 })));

    selectedPlanningYearId = 8;
    view.rerender(<MemoryRouter><ExpenseRegister /></MemoryRouter>);
    expect(await screen.findByText("Spesa 2027")).toBeInTheDocument();

    resolve2026(responseFor(7, "Spesa 2026 tardiva"));
    await waitFor(() => expect(screen.queryByText("Spesa 2026 tardiva")).not.toBeInTheDocument());
    expect(screen.getByText("Spesa 2027")).toBeInTheDocument();
  });
});
