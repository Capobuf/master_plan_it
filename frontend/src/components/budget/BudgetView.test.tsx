import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { annualBudgetFixture, budgetProposalFixture } from "./__fixtures__/budgetApproval";
import BudgetView from "./BudgetView";

vi.mock("./BudgetProposalImpact", () => ({ default: () => <p>Impatto caricato</p> }));
vi.mock("./BudgetApprovalHistory", () => ({ default: ({ planningYearId }: { planningYearId: number }) => <p>Cronologia per anno {planningYearId}</p> }));

describe("BudgetView", () => {
  it("shows the server-authored Preparation overview without legacy approval controls", () => {
    render(<BudgetView dataset={annualBudgetFixture} preview={budgetProposalFixture} />);

    expect(screen.getByText("Stato del Budget: Preparazione")).toBeInTheDocument();
    expect(screen.getByText("Base economica:")).toHaveTextContent("Netto");
    expect(screen.getByText("Budget proposto")).toBeInTheDocument();
    expect(screen.getByText("Valutazioni informative")).toBeInTheDocument();
    expect(screen.getByText("Effettivi correnti")).toBeInTheDocument();
    expect(screen.getByText("Impatto caricato")).toBeInTheDocument();
    expect(screen.getByText("Cronologia per anno 25")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /registra|chiudi/i })).not.toBeInTheDocument();
    expect(screen.queryByRole("checkbox")).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/nuovo approvato/i)).not.toBeInTheDocument();
  });

  it("keeps the immutable approved snapshot distinct from live proposal, evaluations, and actuals", () => {
    render(<BudgetView
      dataset={{
        ...annualBudgetFixture,
        planning_year: { ...annualBudgetFixture.planning_year, state: "approved" },
        currency: "USD",
        approved_snapshot: { id: 91, status: "active", planning_year: { id: 25, year_label: 2026 }, currency: "EUR", basis: "net", effective_date: "2026-08-13", recorded_at: "2026-08-13T10:30:00Z", total: { net: "120.00", vat: "26.40", gross: "146.40", official: "120.00" } },
        actions: { ...annualBudgetFixture.actions, can_approve: false },
      }}
      preview={{ ...budgetProposalFixture, can_approve: false }}
    />);

    expect(screen.getByText("Stato del Budget: Approvato")).toBeInTheDocument();
    expect(screen.getByText("Previsto approvato").parentElement).toHaveTextContent("120,00 €");
    expect(screen.getByText("Budget proposto").parentElement).toHaveTextContent("3.620,00 USD");
    expect(screen.getByText("Valutazioni informative").parentElement).toHaveTextContent("100,00 USD");
    expect(screen.getByText("Effettivi correnti").parentElement).toHaveTextContent("0,00 USD");
    expect(screen.queryByRole("button", { name: "Approva Budget proposto" })).not.toBeInTheDocument();
  });
});
