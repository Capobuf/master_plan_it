import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it } from "vitest";
import PlafondImpactPanel from "./PlafondImpactPanel";

const impact = {
  plafond: { id: 41, title: "Plafond Infrastruttura" }, currency: "EUR", basis: "net" as const, requested: "2700.00", shortage: "200.00", can_confirm: false,
  current: { allocation: { net: "1", vat: "2", gross: "3", official: "3000.00" }, coverage_planned: { net: "1", vat: "2", gross: "3", official: "0.00" }, consumed: { net: "1", vat: "2", gross: "3", official: "500.00" }, available: { net: "1", vat: "2", gross: "3", official: "2500.00" } },
  proposed: { allocation: { net: "1", vat: "2", gross: "3", official: "3000.00" }, coverage_planned: { net: "1", vat: "2", gross: "3", official: "0.00" }, consumed: { net: "1", vat: "2", gross: "3", official: "3200.00" }, available: { net: "1", vat: "2", gross: "3", official: "-200.00" } },
  blocking_rows: [{ expense_id: 81, expense_title: "Licenze annuali", row_id: 502, description: "Canone", type: "actual" as const, contributes_to_coverage_planned: false, contributes_to_consumed: true, date: "2026-08-12", expense_cost_center: { id: 12, name: "Applicazioni" }, plafond_cost_center: { id: 9, name: "Infrastruttura" }, amount: { net: "1", vat: "2", gross: "3", official: "2500.00" } }],
};

describe("PlafondImpactPanel", () => {
  it("renders authoritative impact amounts and the four recovery choices", () => {
    render(<MemoryRouter><PlafondImpactPanel impact={impact} /></MemoryRouter>);
    expect(screen.getAllByText("3.000,00 €")).toHaveLength(2);
    expect(screen.getAllByText("2.500,00 €")).toHaveLength(2);
    expect(screen.getByText("2.700,00 €")).toBeInTheDocument();
    expect(screen.getByText("200,00 €")).toBeInTheDocument();
    expect(screen.getByText("Riduci l’importo, aumenta l’Allocazione, dividi la Spesa o rimuovi la copertura.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Licenze annuali: Canone/ })).toHaveAttribute("href", "/spese/81");
  });

  it("keeps blocking identities visible but gates drill-down links by ability", () => {
    render(<MemoryRouter><PlafondImpactPanel impact={impact} canOpenRows={false} /></MemoryRouter>);
    expect(screen.getByText(/Licenze annuali: Canone/)).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: /Licenze annuali/ })).not.toBeInTheDocument();
    expect(screen.getByText(/Centro riga: Applicazioni/)).toBeInTheDocument();
  });
});
