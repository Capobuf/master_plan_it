import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it, vi } from "vitest";
import {
  getExpense,
  type ExpenseColumnPreference,
  type ExpenseDetail,
  type ExpenseRegisterItem,
} from "../../api/expenses";
import ExpenseRegisterTable from "./ExpenseRegisterTable";

vi.mock("../../api/expenses", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/expenses")>();
  return {
    ...actual,
    getExpense: vi.fn(),
  };
});

const columns: ExpenseColumnPreference[] = [
  { key: "kind", visible: true },
  { key: "contract", visible: true },
  { key: "project", visible: true },
  { key: "cost_center", visible: true },
  { key: "vendor", visible: false },
  { key: "net", visible: true },
  { key: "vat", visible: true },
  { key: "gross", visible: true },
];

const expense: ExpenseRegisterItem = {
  id: 41,
  planning_year_id: 7,
  economic_year_label: 2026,
  cost_center_id: 3,
  cost_center_name: "Operations",
  kind: "ordinary",
  title: "Licenze operative",
  project_id: null,
  project_title: null,
  project_current: false,
  contract_id: 8,
  contract_title: "Contratto Cloud",
  contract_current: true,
  vendor_count: 1,
  vendor_summary: "Acme",
  row_count: 1,
  lock_version: 2,
  currency: "EUR",
  basis: "net",
  totals: {
    current_planning: { net: "100.00", vat: "22.00", gross: "122.00", official: "100.00" },
    actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
  },
};

describe("ExpenseRegisterTable", () => {
  it("keeps selection page-scoped, lazily expands rows, and separates the action menu", async () => {
    vi.mocked(getExpense).mockResolvedValue({
      rows: [{
        id: 51,
        position: 1,
        vendor_id: 9,
        vendor_name: "Acme",
        type: "estimate",
        is_current_planning: true,
        description: "Canone",
        quantity: "1.00",
        unit_price: "100.00",
        entered_amount: "100.00",
        amount_includes_vat: false,
        vat_rate: "22.00",
        spend_date: "2026-01-15",
        external_reference: null,
        lock_version: 1,
        is_system_managed: false,
        generated: false,
        contract_term_id: null,
        amount: expense.totals.current_planning,
      }],
    } as unknown as ExpenseDetail);

    render(<MemoryRouter><ExpenseRegisterTable expenses={[expense]} planningYearId={7} columnPreferences={columns} canEdit canDelete canViewProjects onChanged={vi.fn()} /></MemoryRouter>);

    expect(screen.queryByText(/Spese selezionate/)).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("checkbox", { name: "Seleziona Licenze operative" }));
    expect(screen.getByText("1 Spese selezionate")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Elimina" })).toBeInTheDocument();

    const expander = screen.getByRole("button", { name: "Espandi righe di Licenze operative" });
    const actions = screen.getByRole("button", { name: "Azioni Licenze operative" });
    expect(expander).not.toBe(actions);
    fireEvent.click(expander);

    await waitFor(() => expect(getExpense).toHaveBeenCalledWith(41, 7));
    expect(await screen.findByText("Canone")).toBeInTheDocument();
    expect(screen.getByText("Acme")).toBeInTheDocument();
  });

  it("renders column visibility as a controlled preference", () => {
    const { rerender } = render(<MemoryRouter><ExpenseRegisterTable expenses={[expense]} planningYearId={7} columnPreferences={columns} canEdit={false} canDelete={false} canViewProjects={false} onChanged={vi.fn()} /></MemoryRouter>);

    expect(screen.getByRole("columnheader", { name: "Contratto" })).toBeInTheDocument();

    const nextColumns = columns.map((column) => column.key === "contract" ? { ...column, visible: false } : column);
    rerender(<MemoryRouter><ExpenseRegisterTable expenses={[expense]} planningYearId={7} columnPreferences={nextColumns} canEdit={false} canDelete={false} canViewProjects={false} onChanged={vi.fn()} /></MemoryRouter>);

    expect(screen.queryByRole("columnheader", { name: "Contratto" })).not.toBeInTheDocument();
  });
});
