import { describe, expect, it, vi } from "vitest";
import { apiClient } from "./client";
import { approveBudgetProposal, getBudget, getBudgetApprovalPreview, type ApproveBudgetProposalInput } from "./budget";

vi.mock("./client", () => ({ apiClient: { get: vi.fn(), post: vi.fn() } }));

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

  it("posts only effective date, optional note and reviewed composition evidence", async () => {
    const input: ApproveBudgetProposalInput = {
      effective_date: "2026-08-13",
      note: "Approvazione iniziale",
      composition: {
        schema_version: "budget-proposal-composition/v1",
        fingerprint: `sha256:${"a".repeat(64)}`,
        versions: { budget_lock_version: 7, projection_version: "annual-economic-projection/v1" },
      },
    };
    const data = { approval: { id: 91, status: "active" }, budget: { planning_year_id: 25, state: "approved", lock_version: 8 }, economic_base: { basis: "net", locked_at: "2026-08-13T10:30:00Z" } };
    vi.mocked(apiClient.post).mockResolvedValue({ data: { data } });

    await expect(approveBudgetProposal(25, input)).resolves.toEqual(data);
    expect(apiClient.post).toHaveBeenCalledWith("/api/v1/budget/25/approve", input);
    const body = vi.mocked(apiClient.post).mock.calls[0][1] as Record<string, unknown>;
    expect(Object.keys(body).sort()).toEqual(["composition", "effective_date", "note"]);
    expect(body).not.toHaveProperty("items");
    expect(body).not.toHaveProperty("approved_amount");
    expect(body).not.toHaveProperty("amount");
  });
});
