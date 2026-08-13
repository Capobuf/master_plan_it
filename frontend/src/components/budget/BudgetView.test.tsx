import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { annualBudgetFixture } from "./__fixtures__/budgetApproval";
import BudgetView from "./BudgetView";

vi.mock("./BudgetProposalImpact", () => ({ default: () => <p>Impatto caricato</p> }));

describe("BudgetView", () => {
  it("shows the server-authored Preparation overview without legacy approval controls", () => {
    render(<BudgetView dataset={annualBudgetFixture} />);

    expect(screen.getByText("Stato del Budget: Preparazione")).toBeInTheDocument();
    expect(screen.getByText("Budget proposto")).toBeInTheDocument();
    expect(screen.getByText("Valutazioni informative")).toBeInTheDocument();
    expect(screen.getByText("Effettivi correnti")).toBeInTheDocument();
    expect(screen.getByText("Impatto caricato")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /registra|chiudi/i })).not.toBeInTheDocument();
    expect(screen.queryByRole("checkbox")).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/nuovo approvato/i)).not.toBeInTheDocument();
  });
});
