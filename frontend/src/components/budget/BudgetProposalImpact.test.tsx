import { render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it, vi } from "vitest";
import { getBudgetApprovalPreview } from "../../api/budget";
import { budgetProposalFixture } from "./__fixtures__/budgetApproval";
import BudgetProposalImpact from "./BudgetProposalImpact";

vi.mock("../../api/budget", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/budget")>();
  return { ...actual, getBudgetApprovalPreview: vi.fn() };
});

describe("BudgetProposalImpact", () => {
  it("reconciles complete proposal totals, exclusions and one-time Plafond allocation with contextual links", async () => {
    vi.mocked(getBudgetApprovalPreview).mockResolvedValue(budgetProposalFixture);
    render(<MemoryRouter><BudgetProposalImpact planningYearId={25} /></MemoryRouter>);

    await waitFor(() => expect(screen.getAllByText(/3\.620,00\s€/)).toHaveLength(2));
    expect(screen.getByText("Pianificazione alternativa")).toBeInTheDocument();
    expect(screen.getByText("Coperto dal Plafond")).toBeInTheDocument();
    expect(screen.getByText("Allocazione Plafond (conteggiata una volta)")).toBeInTheDocument();
    expect(screen.getByText(/4\.200,00\s€/)).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Preventivo scelto/ })).toHaveAttribute("href", "/spese/81?planning_year_id=25");
    expect(screen.getByRole("link", { name: "Infrastruttura condivisa" })).toHaveAttribute("href", "/plafonds/82?planning_year_id=25");
    expect(screen.queryByRole("checkbox")).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/importo|approvato/i)).not.toBeInTheDocument();
  });
});
