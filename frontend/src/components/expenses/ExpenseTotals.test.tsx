import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import ExpenseTotals from "./ExpenseTotals";

describe("ExpenseTotals", () => {
  it("shows Net, VAT, and Gross without the official basis copy", () => {
    render(
      <ExpenseTotals
        title="Totali del Registro"
        description="Somma delle spese che corrispondono ai filtri applicati."
        totals={{
          current_planning: { net: "4216.00", vat: "927.52", gross: "5143.52", official: "4216.00" },
          actual: { net: "100.00", vat: "22.00", gross: "122.00", official: "100.00" },
        }}
      />,
    );

    expect(screen.getByText("Totali del Registro")).toBeInTheDocument();
    expect(screen.getByText("Somma delle spese che corrispondono ai filtri applicati.")).toBeInTheDocument();
    expect(screen.getByText("Pianificazione corrente")).toBeInTheDocument();
    expect(screen.getByText("Effettivi")).toBeInTheDocument();
    expect(screen.queryByText(/Base Ufficiale/i)).not.toBeInTheDocument();
  });
});
