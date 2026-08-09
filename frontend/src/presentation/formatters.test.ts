import { describe, expect, it } from "vitest";
import { applyCalculatedAmount, calculateEnteredAmount } from "./formatters";

describe("calculated amounts", () => {
  it("multiplies quantity and unit price without floating-point loss", () => {
    expect(calculateEnteredAmount("2", "12.50")).toBe("25.000000");
    expect(calculateEnteredAmount("0.333333", "0.333333")).toBe("0.111111");
    expect(calculateEnteredAmount("3", "0")).toBeNull();
  });

  it("populates and clears the calculated amount with its source fields", () => {
    const current: { quantity: string | null; unit_price: string | null; entered_amount: string } = {
      quantity: "2",
      unit_price: null,
      entered_amount: "10.00",
    };
    expect(applyCalculatedAmount(current, { unit_price: "12.50" })).toEqual({
      unit_price: "12.50",
      entered_amount: "25.000000",
    });
    expect(applyCalculatedAmount({ ...current, unit_price: "12.50" }, { quantity: null })).toEqual({
      quantity: null,
      entered_amount: "",
    });
  });
});
