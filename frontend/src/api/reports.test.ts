import { describe, expect, it } from "vitest";
import type { ReportsQuery } from "./reports";

describe("reports adapter", () => {
  it("keeps the annual technical context in planning_year_id", () => {
    const query: ReportsQuery = { planning_year_id: 12, group_by: "expense" };
    expect(query.planning_year_id).toBe(12);
  });
});
