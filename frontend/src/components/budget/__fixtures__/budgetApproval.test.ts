import { describe, expect, it } from "vitest";
import {
  activeApprovalFixture,
  annulledApprovalFixture,
  blockerOverlapFixtures,
  budgetProposalFixture,
  type ApprovalConfirmationFixture,
} from "./budgetApproval";

describe("budget approval foundation fixtures", () => {
  it("retains active and annulled approval history fixtures", () => {
    expect(activeApprovalFixture.status).toBe("active");
    expect(annulledApprovalFixture.status).toBe("annulled");
    expect(annulledApprovalFixture.total).toEqual(activeApprovalFixture.total);
  });

  it("retains raw and redacted Actual plus Extra Budget overlaps with a shared identity per view", () => {
    for (const overlap of Object.values(blockerOverlapFixtures)) {
      expect(Object.keys(overlap)).toEqual(["actuals", "extra_budget", "rectifications", "closures"]);
      expect(overlap.actuals[0].source_identity).toBe(overlap.extra_budget[0].source_identity);
      expect(overlap.actuals[0].category).toBe("actuals");
      expect(overlap.extra_budget[0].category).toBe("extra_budget");
    }
  });

  it("models confirmation as composition evidence only, never caller-selected items", () => {
    const confirmation: ApprovalConfirmationFixture = {
      effective_date: "2026-08-13",
      note: null,
      composition: budgetProposalFixture.composition,
    };
    expect("items" in confirmation).toBe(false);
    expect("approved_amount" in confirmation).toBe(false);
  });
});
