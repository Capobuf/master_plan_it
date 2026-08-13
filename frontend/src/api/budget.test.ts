import { describe, expect, it, vi } from "vitest";
import { apiClient } from "./client";
import { getBudget, getBudgetApprovalPreview } from "./budget";

vi.mock("./client", () => ({ apiClient: { get: vi.fn() } }));

describe("budget proposal adapters", () => {
  it("requests the strict proposal overview with the selected PlanningYear", async () => {
    const data = { planning_year: { id: 25 } };
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data } });

    await expect(getBudget({ planning_year_id: 25 })).resolves.toEqual(data);
    expect(apiClient.get).toHaveBeenCalledWith("/api/v1/budget", { params: { planning_year_id: 25 } });
  });

  it("reads the complete server-built impact from the replacement endpoint", async () => {
    const data = { planning_year: { id: 25 }, contributors: [], exclusions: [] };
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data } });

    await expect(getBudgetApprovalPreview(25)).resolves.toEqual(data);
    expect(apiClient.get).toHaveBeenCalledWith("/api/v1/budget/25/approval-preview");
  });
});
