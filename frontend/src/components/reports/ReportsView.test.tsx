import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
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
    expense_id: null,
    cost_center_id: null,
    project_id: 20,
    contract_id: null,
    vendor_id: null,
    currency: "EUR",
    basis: "net",
    totals: {
      current_planning: { net: "1200.00", vat: "264.00", gross: "1464.00", official: "1200.00" },
      actual: { net: "850.00", vat: "187.00", gross: "1037.00", official: "850.00" },
    },
    proposed: "1200.00",
    approved: "1000.00",
    actual: "850.00",
    residual: "150.00",
    variance: "-150.00",
    utilization_percentage: "85.00",
    unapproved_actual_expenses: 1,
    plafond_expenses: 0,
    lines: [{
      expense_id: 81,
      row_id: 91,
      planning_year_id: 1,
      economic_year_label: 2026,
      type: "actual",
      is_current_planning: false,
      contributes_to_current_planning: false,
      description: "Consuntivo migrazione",
      notes: "Fattura verificata",
      spend_date: "2027-01-15",
      cost_center_id: 10,
      vendor_id: 30,
      vendor_name: "Vendor Italia",
      project_id: 20,
      contract_id: null,
      amount: { net: "850.00", vat: "187.00", gross: "1037.00", official: "850.00" },
    }],
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
    unapproved_actual_expenses: 1,
  },
  currency: "EUR",
  basis: "net",
  totals: {
    current_planning: { net: "1200.00", vat: "264.00", gross: "1464.00", official: "1200.00" },
    actual: { net: "850.00", vat: "187.00", gross: "1037.00", official: "850.00" },
  },
  filters: { planning_year_id: 1, cost_center_id: null, project_id: 20, contract_id: null, vendor_id: null, group_by: "project", as_of: null },
};

describe("ReportsView", () => {
  beforeEach(() => {
    vi.mocked(getReports).mockResolvedValue(response);
    vi.mocked(listExpenseCostCenters).mockResolvedValue([{ id: 10, name: "IT" }]);
    vi.mocked(listProjectOptions).mockResolvedValue([{ id: 20, title: "Migrazione ERP", stage: "approved" }]);
    vi.mocked(listExpenseVendors).mockResolvedValue([{ id: 30, name: "Vendor Italia" }]);
  });

  it("applies discrete filters automatically and omits the Attenzioni panel", async () => {
    render(<MemoryRouter><ReportsView tenantId={1} planningYearId={1} canView canLoadCostCenters canLoadProjects canLoadVendors /></MemoryRouter>);

    await waitFor(() => expect(getReports).toHaveBeenCalledTimes(1));
    fireEvent.change(screen.getByLabelText("Raggruppa per"), { target: { value: "project" } });

    await waitFor(() => expect(getReports).toHaveBeenLastCalledWith({
      planning_year_id: 1,
      page: 1,
      per_page: 15,
      group_by: "project",
    }));
    expect(getReports).toHaveBeenCalledTimes(2);

    fireEvent.change(screen.getByLabelText("Progetto"), { target: { value: "20" } });

    await waitFor(() => expect(getReports).toHaveBeenLastCalledWith({
      planning_year_id: 1,
      page: 1,
      per_page: 15,
      group_by: "project",
      project_id: 20,
    }));
    expect(getReports).toHaveBeenCalledTimes(3);

    expect(screen.queryByRole("button", { name: "Applica filtri" })).not.toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Attenzioni" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Azzera filtri" })).toBeInTheDocument();
    expect(screen.getAllByText("Pianificazione corrente").length).toBeGreaterThan(0);
    expect(screen.getAllByText("Effettivi").length).toBeGreaterThan(0);
    expect(screen.getByRole("heading", { name: "Pianificazione, approvato ed Effettivi per Progetto" })).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Stato Spese" })).not.toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Dettaglio per Progetto" })).toBeInTheDocument();
    expect(screen.getAllByText((content) => content.includes("−150,00")).length).toBeGreaterThan(0);
  });

  it("waits for a historical cutoff and returns automatically to the current view", async () => {
    render(<MemoryRouter><ReportsView tenantId={1} planningYearId={1} canView canLoadCostCenters canLoadProjects canLoadVendors /></MemoryRouter>);

    await waitFor(() => expect(getReports).toHaveBeenCalledTimes(1));
    fireEvent.change(screen.getByLabelText("Vista"), { target: { value: "historical" } });

    expect(screen.getByLabelText("Cutoff")).toBeInTheDocument();
    expect(getReports).toHaveBeenCalledTimes(1);

    fireEvent.change(screen.getByLabelText("Cutoff"), { target: { value: "2026-08-10T12:30" } });

    await waitFor(() => expect(getReports).toHaveBeenLastCalledWith({
      planning_year_id: 1,
      page: 1,
      per_page: 15,
      group_by: "cost_center",
      as_of: "2026-08-10T12:30",
    }));
    expect(getReports).toHaveBeenCalledTimes(2);

    fireEvent.change(screen.getByLabelText("Vista"), { target: { value: "current" } });

    expect(screen.queryByLabelText("Cutoff")).not.toBeInTheDocument();
    await waitFor(() => expect(getReports).toHaveBeenLastCalledWith({
      planning_year_id: 1,
      page: 1,
      per_page: 15,
      group_by: "cost_center",
    }));
    expect(getReports).toHaveBeenCalledTimes(3);
  });

  it("expands a group into authoritative expense lines with an accessible drill-down", async () => {
    render(<MemoryRouter><ReportsView tenantId={1} planningYearId={1} canView canLoadCostCenters canLoadProjects canLoadVendors /></MemoryRouter>);

    const group = await screen.findByRole("button", { name: /Migrazione ERP/ });
    expect(group).toHaveAttribute("aria-expanded", "false");
    fireEvent.click(group);

    expect(group).toHaveAttribute("aria-expanded", "true");
    expect(screen.getByRole("list", { name: "Righe economiche di Migrazione ERP" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Consuntivo migrazione" })).toHaveAttribute("href", "/spese/81");
    expect(screen.getByText(/Effettivo · Data Effettiva 2027-01-15/)).toBeInTheDocument();
    expect(screen.getByText("Fattura verificata")).toBeInTheDocument();
  });

  it("labels allocation adjustments and links them to the Plafond document", async () => {
    vi.mocked(getReports).mockResolvedValue({
      ...response,
      data: [{
        ...response.data[0],
        lines: [{
          ...response.data[0].lines[0],
          expense_id: 41,
          row_id: 501,
          type: "allocation_adjustment",
          description: "Allocazione iniziale",
          spend_date: "2026-01-10",
        }],
      }],
    });
    render(<MemoryRouter><ReportsView tenantId={1} planningYearId={1} canView canLoadCostCenters canLoadProjects canLoadVendors /></MemoryRouter>);

    fireEvent.click(await screen.findByRole("button", { name: /Migrazione ERP/ }));

    expect(screen.getByRole("link", { name: "Allocazione iniziale" })).toHaveAttribute("href", "/plafonds/41");
    expect(screen.getByText(/Variazione Allocazione/)).toBeInTheDocument();
  });
});
