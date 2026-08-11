import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import ExpenseTotals from "./ExpenseTotals";

describe("ExpenseTotals", () => {
  it("shows Net, VAT, and Gross without the official basis copy", () => {
    render(
      <ExpenseTotals
        title="Totali del Registro"
        description="Somma delle spese che corrispondono ai filtri applicati."
        totals={{ net: "4216.00", vat: "927.52", gross: "5143.52", currency: "EUR", official_basis: "net" }}
      />,
    );

    expect(screen.getByText("Totali del Registro")).toBeInTheDocument();
    expect(screen.getByText("Somma delle spese che corrispondono ai filtri applicati.")).toBeInTheDocument();
    expect(screen.getByText("Netto")).toBeInTheDocument();
    expect(screen.getByText("IVA")).toBeInTheDocument();
    expect(screen.getByText("Lordo")).toBeInTheDocument();
    expect(screen.queryByText(/Base Ufficiale/i)).not.toBeInTheDocument();
  });
});
