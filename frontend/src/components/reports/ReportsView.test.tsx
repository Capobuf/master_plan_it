import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { listExpenseCostCenters, listExpenseVendors } from "../../api/expenses";
import { listProjectOptions } from "../../api/projects";
import { getReports, type ReportsResponse } from "../../api/reports";
import ReportsView from "./ReportsView";

vi.mock("react-apexcharts", () => ({
  default: ({ type }: { type: string }) => <div data-testid={`chart-${type}`} />,
}));

vi.mock("../../api/expenses", () => ({
  listExpenseCostCenters: vi.fn(),
  listExpenseVendors: vi.fn(),
}));

vi.mock("../../api/projects", () => ({
  listProjectOptions: vi.fn(),
}));

vi.mock("../../api/reports", () => ({
  getReports: vi.fn(),
}));

const response: ReportsResponse = {
  data: [{
    key: "project:20",
    label: "Migrazione ERP",
    group_by: "project",
    proposed: "1200.00",
    approved: "1000.00",
    actual: "850.00",
    residual: "150.00",
    variance: "-150.00",
    utilization_percentage: "85.00",
    open_expenses: 2,
    closed_expenses: 1,
    unapproved_actual_expenses: 1,
    plafond_expenses: 0,
  }],
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
  mode: "current",
  requested_as_of: null,
  cutoff_utc: null,
  read_only: false,
  budget: { planning_year_id: 1, year: 2026, state: "approved", lock_version: 1, warning: null, history_activated_at: null },
  summary: {
    currency: "EUR",
    official_basis: "net",
    proposed: "1200.00",
    approved_current: "1000.00",
    actual: "850.00",
    residual: "150.00",
    variance: "-150.00",
    utilization_percentage: "85.00",
    open_expenses: 2,
    closed_expenses: 1,
    unapproved_actual_expenses: 1,
  },
  global_plafond_overrun: "50.00",
  visualization: {
    groups: [{ key: "project:20", label: "Migrazione ERP", proposed: "1200.00", approved: "1000.00", actual: "850.00", residual: "150.00", variance: "-150.00", utilization_percentage: "85.00" }],
    proposed_breakdown: [{ key: "project:20", label: "Migrazione ERP", proposed: "1200.00" }],
    expense_states: { open: 2, closed: 1, total: 3 },
  },
  filters: { planning_year_id: 1, cost_center_id: null, project_id: 20, vendor_id: null, state: null, group_by: "project" },
};

describe("ReportsView", () => {
  beforeEach(() => {
    vi.mocked(getReports).mockResolvedValue(response);
    vi.mocked(listExpenseCostCenters).mockResolvedValue([{ id: 10, name: "IT" }]);
    vi.mocked(listProjectOptions).mockResolvedValue([{ id: 20, title: "Migrazione ERP", stage: "approved" }]);
    vi.mocked(listExpenseVendors).mockResolvedValue([{ id: 30, name: "Vendor Italia" }]);
  });

  it("submits draft filters, reveals historical cutoff and renders the analytical response", async () => {
    render(<ReportsView tenantId={1} planningYearId={1} canView canLoadCostCenters canLoadProjects canLoadVendors />);

    await waitFor(() => expect(getReports).toHaveBeenCalledTimes(1));
    fireEvent.change(screen.getByLabelText("Raggruppa per"), { target: { value: "project" } });
    fireEvent.change(screen.getByLabelText("Centro di Costo"), { target: { value: "10" } });
    fireEvent.change(screen.getByLabelText("Progetto"), { target: { value: "20" } });
    fireEvent.change(screen.getByLabelText("Fornitore"), { target: { value: "30" } });
    fireEvent.change(screen.getByLabelText("Stato Spesa"), { target: { value: "open" } });
    fireEvent.change(screen.getByLabelText("Vista"), { target: { value: "historical" } });
    expect(screen.getByLabelText("Cutoff")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Cutoff"), { target: { value: "2026-08-10T12:30" } });
    expect(getReports).toHaveBeenCalledTimes(1);

    fireEvent.click(screen.getByRole("button", { name: "Applica filtri" }));

    await waitFor(() => expect(getReports).toHaveBeenLastCalledWith({
      planning_year_id: 1,
      page: 1,
      per_page: 15,
      group_by: "project",
      cost_center_id: 10,
      project_id: 20,
      vendor_id: 30,
      state: "open",
      as_of: "2026-08-10T12:30",
    }));
    expect(screen.getAllByText("Proposto").length).toBeGreaterThan(0);
    expect(screen.getByRole("heading", { name: "Proposto vs Approvato vs Actual per Progetto" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Ripartizione del Proposto per Progetto" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Scostamento per Progetto" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Stato Spese" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Attenzioni" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Dettaglio per Progetto" })).toBeInTheDocument();
    expect(screen.getAllByText((content) => content.includes("−150,00")).length).toBeGreaterThan(0);
  });
});
