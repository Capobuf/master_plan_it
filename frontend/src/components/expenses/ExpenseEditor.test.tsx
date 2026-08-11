import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "../../api/client";
import * as expenseApi from "../../api/expenses";
import type { ExpenseDetail } from "../../api/expenses";
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
    createExpense: vi.fn(),
    updateExpense: vi.fn(),
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
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(expenseApi.listExpenseVendors).mockResolvedValue([]);
    vi.mocked(expenseApi.listExpenseCostCenters).mockResolvedValue([{ id: 3, name: "Operations" }]);
    vi.mocked(expenseApi.listExpensePlanningYears).mockResolvedValue([
      { id: 7, label: 2026, active: true },
      { id: 8, label: 2027, active: true },
    ]);
    vi.mocked(expenseApi.listExpenseContracts).mockResolvedValue([]);
    vi.mocked(expenseApi.listEligiblePlafondExpenses).mockResolvedValue([]);
  });

  it("uses the global year without repeating it in the editor and adds rows inline", async () => {
    render(<MemoryRouter><ExpenseEditor /></MemoryRouter>);

    await screen.findByRole("button", { name: "+ Aggiungi Riga" });
    expect(screen.queryByRole("combobox", { name: /Anno/ })).not.toBeInTheDocument();
    expect(document.querySelector("#expense-editor-year")).not.toBeInTheDocument();
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

  it("highlights and focuses the indexed row field returned by the API", async () => {
    vi.mocked(expenseApi.listExpenseVendors).mockResolvedValue([{ id: 5, name: "Fornitore corrente" }]);
    vi.mocked(expenseApi.getExpense).mockResolvedValue({
      id: 8,
      planning_year_id: 7,
      planning_year_label: 2026,
      cost_center_id: 3,
      kind: "ordinary",
      title: "Spesa da modificare",
      notes: "Nota corrente",
      project_id: null,
      contract_id: null,
      credit_for_expense_id: null,
      state: "open",
      lock_version: 1,
      rows: [{
        id: 9,
        position: 1,
        vendor_id: 5,
        type: "actual",
        description: "Riga corrente",
        quantity: null,
        unit_price: null,
        entered_amount: "100.00",
        amount_includes_vat: false,
        vat_rate: "22.00",
        is_extra: false,
        funded_plafond_expense_id: null,
        spend_date: "2026-01-15",
        external_reference: null,
        lock_version: 1,
        is_current_planning: false,
      }],
    } as ExpenseDetail);
    vi.mocked(expenseApi.updateExpense).mockRejectedValue(new ApiError({
      message: "I dati inseriti non sono validi.",
      status: 422,
      code: "VALIDATION_FAILED",
      fields: {
        "rows.1.vendor_id": ["Ordinary expense rows require a vendor."],
        "rows.1.unit_price": ["The amount must be a decimal with at most 2 places."],
      },
      correlationId: "validation-reference",
    }));
    render(<MemoryRouter><ExpenseEditor expenseId={8} /></MemoryRouter>);

    await screen.findByDisplayValue("Spesa da modificare");
    fireEvent.click(screen.getByRole("button", { name: "+ Aggiungi Riga" }));
    fireEvent.change(screen.getByRole("textbox", { name: "Descrizione riga 2" }), { target: { value: "Nuova riga" } });
    fireEvent.change(screen.getByRole("textbox", { name: "Importo riga 2" }), { target: { value: "250,00" } });
    fireEvent.click(screen.getByRole("button", { name: "Salva modifiche" }));

    expect(await screen.findByRole("heading", { name: "Controlla i dati" })).toBeInTheDocument();
    const invalidVendor = screen.getByRole("combobox", { name: "Fornitore riga 2" });
    expect(invalidVendor).toHaveAttribute("aria-invalid", "true");
    expect(screen.getByText("Seleziona un Fornitore per questa riga.")).toBeInTheDocument();
    const invalidUnitPrice = screen.getByRole("textbox", { name: "Prezzo Unitario riga 2" });
    expect(invalidUnitPrice).toHaveAttribute("aria-invalid", "true");
    expect(invalidUnitPrice).toHaveAccessibleDescription("Inserisci un prezzo valido con massimo 2 decimali.");
    await waitFor(() => expect(document.activeElement).toBe(invalidVendor));
    expect(screen.queryByText(/Riga 2 · Fornitore/)).not.toBeInTheDocument();
    expect(screen.queryByText(/rows\.1\.vendor_id/)).not.toBeInTheDocument();
  });

  it("highlights and focuses the first invalid local field", async () => {
    render(<MemoryRouter><ExpenseEditor /></MemoryRouter>);

    const submit = await screen.findByRole("button", { name: "Crea spesa" });
    fireEvent.click(submit);

    const costCenter = screen.getByRole("combobox", { name: "Centro di Costo" });
    expect(costCenter).toHaveAttribute("aria-invalid", "true");
    expect(screen.getByText("Seleziona un Centro di Costo.")).toBeInTheDocument();
    await waitFor(() => expect(document.activeElement).toBe(costCenter));
  });
});
