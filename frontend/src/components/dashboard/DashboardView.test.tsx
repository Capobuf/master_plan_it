import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it, vi } from "vitest";
import type { ReportingDataset } from "../../api/dashboard";
import DashboardView from "./DashboardView";

vi.mock("react-apexcharts", () => ({
  default: ({ type }: { type: string }) => <div data-testid={`chart-${type}`} />,
}));

const dataset: ReportingDataset = {
  scope: { planning_year_id: 1, year: 2026, currency: "EUR", official_basis: "net" },
  summary: {
    official_basis: "net",
    currency: "EUR",
    amounts: {
      official_current_position: "245.00",
      planned: "200.00",
      actual: "45.00",
      plafond_overrun: "0.00",
    },
  },
  monthly: { "2026-01": "100.00", "2026-02": "145.00" },
  by_cost_center: { Software: "245.00" },
  by_project: { "Migrazione cloud": "200.00", "Senza progetto": "45.00" },
  has_economic_data: true,
  ancillary: {
    expenseCounts: { total: 2, open: 1, closed: 1 },
    recentExpenses: [{
      id: 42,
      label: "Licenze cloud",
      date: "2026-08-10T09:30:00Z",
      cost_center: "Software",
      project: "Migrazione cloud",
      vendor: "Cloud Italia",
      planned: "200.00",
      actual: "45.00",
      state: "open",
    }],
    upcomingContractEvents: [{
      id: 7,
      label: "Cloud annuale",
      date: "2026-10-01",
      event_type: "renewal",
    }],
  },
};

describe("DashboardView", () => {
  it("renders the annual overview, recent expenses and contract events", () => {
    render(
      <MemoryRouter>
        <DashboardView dataset={dataset} />
      </MemoryRouter>,
    );

    expect(screen.getByText("Posizione Economica")).toBeInTheDocument();
    expect(screen.getAllByText("Pianificato")).toHaveLength(2);
    expect(screen.getAllByText("Actual")).toHaveLength(2);
    expect(screen.getByText("Spese Aperte")).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Spese per Centro di Costo" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Andamento Mensile" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Spese per Progetto" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Stato Spese" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Ultime Spese" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Rinnovi e Scadenze" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Licenze cloud" })).toHaveAttribute("href", "/spese/42");
    expect(screen.getByRole("link", { name: "Cloud annuale" })).toHaveAttribute("href", "/contratti/7");
  });
});
