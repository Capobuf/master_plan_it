import { describe, expect, it } from "vitest";
import type { ProjectionTotals } from "./projection";

describe("budget projection adapter", () => {
  it("uses the shared two-measure totals shape", () => {
    const totals: ProjectionTotals = { current_planning: { net: "10.00", vat: "2.20", gross: "12.20", official: "10.00" }, actual: { net: "5.00", vat: "1.10", gross: "6.10", official: "5.00" } };
    expect(Object.keys(totals)).toEqual(["current_planning", "actual"]);
  });
});
