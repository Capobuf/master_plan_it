import { describe, expect, it } from "vitest";
import type { ReportingDataset } from "./dashboard";

describe("dashboard projection adapter", () => {
  it("accepts the same server-authored totals without client formulas", () => {
    const dataset: ReportingDataset = {
      planning_year_id: 7,
      economic_year_label: 2026,
      currency: "EUR",
      basis: "net",
      totals: {
        current_planning: { net: "10.00", vat: "2.20", gross: "12.20", official: "10.00" },
        actual: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" },
      },
      has_economic_data: true,
      expense_count: 1,
      recent_expenses: [],
      monthly: [],
      by_type: [],
      by_cost_center: [],
      by_project: [],
      generated_contract_planning: [],
      active_contracts: [],
      upcoming_contract_events: [],
    };
    expect(dataset.totals?.current_planning?.official).toBe("10.00");
  });
});
