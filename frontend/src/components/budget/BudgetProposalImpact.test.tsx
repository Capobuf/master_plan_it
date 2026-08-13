import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it } from "vitest";
import { budgetProposalFixture } from "./__fixtures__/budgetApproval";
import BudgetProposalImpact from "./BudgetProposalImpact";

describe("BudgetProposalImpact", () => {
  it("reconciles complete proposal totals, frozen dimensions and one-time Plafond allocation with contract-authoritative contextual links", () => {
    render(<MemoryRouter><BudgetProposalImpact preview={budgetProposalFixture} /></MemoryRouter>);

    expect(screen.getAllByText(/3\.620,00\s€/)).toHaveLength(2);
    expect(screen.getByText("Pianificazione alternativa")).toBeInTheDocument();
    expect(screen.getByText("Coperto dal Plafond")).toBeInTheDocument();
    expect(screen.getByText("Allocazione Plafond (conteggiata una volta)")).toBeInTheDocument();
    expect(screen.getAllByText(/4\.200,00\s€/)).toHaveLength(2);
    for (const label of ["Netto", "IVA", "Lordo", "Valore ufficiale"]) expect(screen.getAllByText(label).length).toBeGreaterThan(0);
    for (const label of ["Centro di costo: Infrastruttura", "Fornitore: Fornitore Demo", "Progetto: Programma cloud", "Contratto: Contratto cloud"]) expect(screen.getAllByText(label).length).toBeGreaterThan(0);
    expect(screen.getByRole("link", { name: /Preventivo scelto/ })).toHaveAttribute("href", "/spese/81?planning_year_id=25");
    expect(screen.getByRole("link", { name: "Infrastruttura condivisa" })).toHaveAttribute("href", "/plafonds/82?planning_year_id=25");
    expect(screen.queryByRole("checkbox")).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/importo|approvato/i)).not.toBeInTheDocument();
  });

  it("does not make a navigable link for either unauthorized or absent contract hrefs", () => {
    const preview = {
      ...budgetProposalFixture,
      contributors: [
        { ...budgetProposalFixture.contributors[0], drill_down: { authorized: false, href: "/api/v1/expenses/81" } },
        { ...budgetProposalFixture.contributors[1], drill_down: { authorized: true, href: null } },
      ],
    };
    render(<MemoryRouter><BudgetProposalImpact preview={preview} /></MemoryRouter>);
    expect(screen.queryByRole("link", { name: /Preventivo scelto|Infrastruttura condivisa/ })).not.toBeInTheDocument();
    expect(screen.getAllByText(/dettaglio non autorizzato/)).toHaveLength(2);
  });

  it("rejects malformed and foreign evidence paths instead of constructing a client route", () => {
    const preview = {
      ...budgetProposalFixture,
      contributors: [
        { ...budgetProposalFixture.contributors[0], drill_down: { authorized: true, href: "/api/v1/expenses/81?include=rows" } },
        { ...budgetProposalFixture.contributors[1], drill_down: { authorized: true, href: "/api/v1/tenants/82" } },
      ],
    };
    render(<MemoryRouter><BudgetProposalImpact preview={preview} /></MemoryRouter>);
    expect(screen.queryByRole("link", { name: /Preventivo scelto|Infrastruttura condivisa/ })).not.toBeInTheDocument();
    expect(screen.getAllByText(/dettaglio non autorizzato/)).toHaveLength(2);
  });
});
