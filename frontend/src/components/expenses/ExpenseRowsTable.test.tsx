import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import type { ExpenseRow } from "../../api/expenses";
import ExpenseRowsTable from "./ExpenseRowsTable";

const row: ExpenseRow = {
  id: 1,
  position: 1,
  vendor_id: 2,
  vendor_name: "Acme",
  type: "quote",
  is_current_planning: true,
  description: "Canone annuale",
  quantity: "1.00",
  unit_price: "100.00",
  entered_amount: "100.00",
  amount_includes_vat: false,
  vat_rate: "22.00",
  is_extra: false,
  funded_plafond_expense_id: null,
  spend_date: "2026-01-15",
  external_reference: null,
  lock_version: 1,
  is_system_managed: false,
  generated: false,
  contract_term_id: null,
  totals: { net: "100.00", vat: "22.00", gross: "122.00", currency: "EUR", official_basis: "net" },
};

describe("ExpenseRowsTable", () => {
  it("keeps row description and authoritative economic fields in read-only views", () => {
    render(<ExpenseRowsTable rows={[row]} compact />);

    expect(screen.getByRole("columnheader", { name: "Descrizione" })).toBeInTheDocument();
    expect(screen.getByText("Canone annuale")).toBeInTheDocument();
    expect(screen.getByRole("columnheader", { name: "Netto" })).toBeInTheDocument();
    expect(screen.getByRole("columnheader", { name: "Lordo" })).toBeInTheDocument();
  });
});
