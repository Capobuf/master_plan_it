import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "../../api/client";
import * as plafondApi from "../../api/plafonds";
import PlafondEditor from "./PlafondEditor";

const hasAbility = vi.fn(() => true);
const registerDirtySource = vi.fn();
vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({ data: { tenant: { default_vat_rate: "22.00" } }, hasAbility }),
}));
vi.mock("../../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({ selectedPlanningYearId: 25, registerDirtySource }),
}));
vi.mock("../../hooks/useWorkspaceContextGuard", () => ({
  useWorkspaceContextGuard: (source: string, dirty: boolean) => registerDirtySource(source, dirty),
}));
vi.mock("../../api/expenses", () => ({ listExpenseCostCenters: vi.fn().mockResolvedValue([{ id: 9, name: "Infrastruttura" }]) }));
vi.mock("../../api/plafonds", () => ({ createPlafond: vi.fn(), addAllocationAdjustment: vi.fn(), previewAllocationAdjustment: vi.fn() }));

const measures = {
  allocation: { net: "3500.00", vat: "770.00", gross: "4270.00", official: "3500.00" },
  coverage_planned: { net: "4200.00", vat: "924.00", gross: "5124.00", official: "4200.00" },
  consumed: { net: "2500.00", vat: "550.00", gross: "3050.00", official: "2500.00" },
  available: { net: "1000.00", vat: "220.00", gross: "1220.00", official: "1000.00" },
};
const plafond = {
  id: 41, planning_year_id: 25, economic_year_label: 2026, kind: "plafond" as const, title: "Plafond Infrastruttura", notes: null,
  cost_center: { id: 9, name: "Infrastruttura" }, lock_version: 3, currency: "EUR", basis: "net" as const, measures,
  budget_context: { state: "preparation" as const, read_only: false }, allocation_adjustments: [], covered_rows: [],
};

describe("PlafondEditor", () => {
  beforeEach(() => { vi.clearAllMocks(); hasAbility.mockReturnValue(true); });

  it("creates through the dedicated nested initial_allocation payload and registers dirty state", async () => {
    vi.mocked(plafondApi.createPlafond).mockResolvedValue(plafond);
    render(<MemoryRouter><PlafondEditor /></MemoryRouter>);
    await screen.findByRole("option", { name: "Infrastruttura" });
    fireEvent.change(screen.getByLabelText("Titolo"), { target: { value: "Plafond Infrastruttura" } });
    fireEvent.change(screen.getByLabelText("Centro di Costo"), { target: { value: "9" } });
    fireEvent.change(screen.getByLabelText("Descrizione"), { target: { value: "Allocazione iniziale" } });
    fireEvent.change(screen.getByLabelText("Variazione Allocazione"), { target: { value: "3000,00" } });
    fireEvent.click(screen.getByRole("checkbox", { name: "IVA inclusa" }));
    fireEvent.click(screen.getByRole("button", { name: "Crea Plafond" }));
    await waitFor(() => expect(plafondApi.createPlafond).toHaveBeenCalledOnce());
    expect(plafondApi.createPlafond).toHaveBeenCalledWith(expect.objectContaining({ planning_year_id: 25, cost_center_id: 9, initial_allocation: expect.objectContaining({ description: "Allocazione iniziale", entered_amount: "3000", amount_includes_vat: true }) }));
    expect(registerDirtySource).toHaveBeenCalledWith("plafond-create", true);
  });

  it("uses calculated allocation XOR and preserves values after a structured server error", async () => {
    vi.mocked(plafondApi.addAllocationAdjustment).mockRejectedValue(new ApiError({
      message: "I dati inseriti non sono validi.",
      status: 422,
      code: "PLAFOND_INSUFFICIENT",
      details: {
        plafond_expense_id: 41,
        currency: "EUR",
        basis: "net",
        allocated: "3500.00",
        available: "1000.00",
        required: "2500.00",
        shortage: "200.00",
        impact: { requested: "-1200.00", current: measures, proposed: { ...measures, available: { net: "-200.00", vat: "-44.00", gross: "-244.00", official: "-200.00" } }, blocking_rows: [] },
      },
    }));
    render(<MemoryRouter><PlafondEditor plafond={plafond} /></MemoryRouter>);
    fireEvent.change(screen.getByLabelText("Descrizione"), { target: { value: "Riduzione" } });
    fireEvent.change(screen.getByLabelText("Quantità (alternativa)"), { target: { value: "2" } });
    fireEvent.change(screen.getByLabelText("Prezzo unitario (alternativa)"), { target: { value: "-600" } });
    fireEvent.click(screen.getByRole("button", { name: "Aggiungi variazione" }));
    await waitFor(() => expect(plafondApi.addAllocationAdjustment).toHaveBeenCalledOnce());
    expect(plafondApi.addAllocationAdjustment).toHaveBeenCalledWith(41, expect.objectContaining({ lock_version: 3, adjustment: expect.objectContaining({ quantity: "2", unit_price: "-600" }) }));
    expect(vi.mocked(plafondApi.addAllocationAdjustment).mock.calls[0][1].adjustment).not.toHaveProperty("entered_amount");
    expect(await screen.findByText("Capienza insufficiente")).toBeInTheDocument();
    expect(screen.getByText("−1.200,00 €")).toBeInTheDocument();
    expect(screen.getByText(/Riduci l.importo, aumenta l.Allocazione, dividi la Spesa o rimuovi la copertura/)).toBeInTheDocument();
    expect(screen.getByLabelText("Descrizione")).toHaveValue("Riduzione");
    expect(screen.getByLabelText("Quantità (alternativa)")).toHaveValue("2");
  });

  it("uses the server preview and disables mutation for read-only or unauthorized states", async () => {
    const readOnly = { ...plafond, budget_context: { ...plafond.budget_context, read_only: true } };
    const { rerender } = render(<MemoryRouter><PlafondEditor plafond={readOnly} /></MemoryRouter>);
    expect(screen.getByText("Plafond in sola lettura")).toBeInTheDocument();
    hasAbility.mockReturnValue(false);
    rerender(<MemoryRouter><PlafondEditor plafond={plafond} /></MemoryRouter>);
    expect(screen.getByText("Operazione non disponibile")).toBeInTheDocument();
  });
});
