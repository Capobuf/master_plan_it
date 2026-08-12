import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import type { ReportingDataset } from "../../api/dashboard";
import DashboardView from "./DashboardView";

const measure = (official: string) => ({ net: official, vat: "0.00", gross: official, official });

const dataset: ReportingDataset = {
  planning_year_id: 1,
  economic_year_label: 2026,
  currency: "EUR",
  basis: "net",
  totals: { current_planning: measure("200.00"), actual: measure("45.00") },
  has_economic_data: true,
  expense_count: 1,
  recent_expenses: [],
  monthly: [],
  by_type: [],
  by_cost_center: [],
  by_project: [],
  generated_contract_planning: [],
  active_contracts: [],
  upcoming_contract_events: [],
};

describe("DashboardView", () => {
  it("renders only the canonical annual projection totals", () => {
    render(<DashboardView dataset={dataset} />);

    expect(screen.getByText("Pianificazione corrente")).toBeInTheDocument();
    expect(screen.getByText("200,00 €")).toBeInTheDocument();
    expect(screen.getByText("Effettivi")).toBeInTheDocument();
    expect(screen.getByText("45,00 €")).toBeInTheDocument();
    expect(screen.queryByText("Spese Aperte")).not.toBeInTheDocument();
    expect(screen.queryByText("Stato Spese")).not.toBeInTheDocument();
  });

  it("shows an explicit empty state without inventing fallback totals", () => {
    render(<DashboardView dataset={{ ...dataset, has_economic_data: false }} />);

    expect(screen.getByText("Nessun dato economico")).toBeInTheDocument();
    expect(screen.queryByText("Pianificazione corrente")).not.toBeInTheDocument();
  });
});
