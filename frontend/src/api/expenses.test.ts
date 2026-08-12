import { describe, expect, it } from "vitest";
import type { ExpenseRowInput, ExpenseListParams } from "./expenses";

describe("expense adapter contracts", () => {
  it("uses planning_year_id and retains decimal strings for direct input", () => {
    const query: ExpenseListParams = { planning_year_id: 7, q: "hosting" };
    const row: ExpenseRowInput = { position: 1, type: "actual", description: "Rimborso", entered_amount: "-5.00", amount_includes_vat: false, notes: "Storno" };
    expect(query.planning_year_id).toBe(7);
    expect(row.entered_amount).toBe("-5.00");
  });

  it("models calculated input without coercing it to a local amount", () => {
    const row: ExpenseRowInput = { position: 1, type: "quote", description: "Licenze", quantity: "2.00", unit_price: "12.50", amount_includes_vat: false };
    expect(row.entered_amount).toBeUndefined();
    expect(row.quantity).toBe("2.00");
  });
});
