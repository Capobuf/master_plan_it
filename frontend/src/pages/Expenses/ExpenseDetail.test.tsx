import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router";
import { describe, expect, it, vi } from "vitest";
import { getExpense, getExpenseHistory, type ExpenseDetail as ExpenseDetailData } from "../../api/expenses";
import ExpenseDetail from "./ExpenseDetail";

vi.mock("../../api/attachments", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/attachments")>();
  return { ...actual, listAttachments: vi.fn().mockResolvedValue({ data: [], meta: { used_bytes: "0", quota_bytes: "2147483648" }, abilities: { upload: true, download: true, delete: true } }) };
});

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    data: { tenant: { id: 1 }, abilities: [] },
    loading: false,
    hasAbility: () => true,
  }),
}));
vi.mock("../../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({ selectedPlanningYearId: 7, loading: false, selectPlanningYear: vi.fn() }),
}));
vi.mock("../../api/expenses", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/expenses")>();
  return { ...actual, getExpense: vi.fn(), getExpenseHistory: vi.fn() };
});

const money = { net: "100.00", vat: "22.00", gross: "122.00", official: "100.00" };
const detail = {
  id: 42, planning_year_id: 7, economic_year_label: 2026, cost_center_id: 3, cost_center_name: "Operations",
  kind: "ordinary", title: "Licenze operative", notes: "Nota", project_id: null, project_title: null,
  contract_id: 8, contract_title: "Contratto Cloud", current_planning_row_id: 51,
  warnings: [], lock_version: 2, revision_activity: [], currency: "EUR", basis: "net",
  budget_context: { state: "preparation", read_only: false },
  totals: { current_planning: money, actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" } },
  rows: [{ id: 51, position: 1, vendor_id: 9, vendor_name: "Acme Italia", type: "estimate", is_current_planning: true,
    description: "Canone", quantity: "1.00", unit_price: "100.00", entered_amount: "100.00",
    amount_includes_vat: false, vat_rate: "22.00",
    spend_date: "2026-01-15", external_reference: null, lock_version: 1, is_system_managed: false,
    generated: false, contract_term_id: null, amount: money }],
} as unknown as ExpenseDetailData;

describe("ExpenseDetail", () => {
  it("loads only the global year and exposes accessible actions and relation labels", async () => {
    vi.mocked(getExpense).mockResolvedValue(detail);
    render(<MemoryRouter initialEntries={["/expenses/42"]}><Routes><Route path="/expenses/:expenseId" element={<ExpenseDetail />} /></Routes></MemoryRouter>);

    await waitFor(() => expect(getExpense).toHaveBeenCalledWith(42, 7));
    expect(await screen.findByText("Contratto Cloud")).toBeInTheDocument();
    expect(screen.getByText("Acme Italia")).toBeInTheDocument();
    expect(screen.getAllByText("100,00 €").length).toBeGreaterThan(0);
    expect(screen.getByText("22,00 %")).toBeInTheDocument();
    for (const name of ["Modifica Spesa", "Elimina Spesa"]) {
      expect(screen.getByRole("button", { name })).toBeInTheDocument();
    }
  });

  it("loads the logical history only after opening the Storico tab", async () => {
    vi.mocked(getExpense).mockResolvedValue(detail);
    vi.mocked(getExpenseHistory).mockResolvedValue({
      data: [{ id: 9, operation: "update", actor: { kind: "system", label: "Sistema" }, timestamp: "2026-08-11T09:30:00Z", summary: "Note aggiornate", changed_count: 1, changed_fields: ["Note"], can_compare: true, can_restore: false }],
      links: { first: null, last: null, prev: null, next: null },
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    });

    render(<MemoryRouter initialEntries={["/expenses/42"]}><Routes><Route path="/expenses/:expenseId" element={<ExpenseDetail />} /></Routes></MemoryRouter>);
    await screen.findByText("Licenze operative");
    expect(getExpenseHistory).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole("tab", { name: "Storico" }));
    await waitFor(() => expect(getExpenseHistory).toHaveBeenCalledWith(42, 7));
    expect(await screen.findByText("Note aggiornate")).toBeInTheDocument();
    expect(screen.getAllByText("Sistema").length).toBeGreaterThan(0);
  });

  it("shows Allegati between Dettagli and Storico and groups Expense rows", async () => {
    vi.mocked(getExpense).mockResolvedValue(detail);
    render(<MemoryRouter initialEntries={["/expenses/42"]}><Routes><Route path="/expenses/:expenseId" element={<ExpenseDetail />} /></Routes></MemoryRouter>);
    await screen.findByText("Licenze operative");
    expect(screen.getAllByRole("tab").map((tab) => tab.textContent)).toEqual(["Dettagli", "Allegati", "Storico"]);
    fireEvent.click(screen.getByRole("tab", { name: "Allegati" }));
    expect(await screen.findByText("Righe della Spesa")).toBeInTheDocument();
    expect(screen.getByText(/Riga 1 · Canone/)).toBeInTheDocument();
  });
});
