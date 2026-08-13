import { describe, expect, it, vi } from "vitest";
import { AxiosError, type AxiosResponse } from "axios";
import { apiClient } from "./client";
import {
  approveBudgetProposal,
  getBudget,
  getBudgetApprovalDetail,
  getBudgetApprovalHistory,
  getBudgetApprovalPreview,
  type ApproveBudgetProposalInput,
} from "./budget";

vi.mock("./client", async (importOriginal) => ({
  ...(await importOriginal<typeof import("./client")>()),
  apiClient: { get: vi.fn(), post: vi.fn() },
}));

const measure = { net: "10.00", vat: "2.00", gross: "12.00", official: "10.00" };
const contributor = {
  source_identity: "expense-row:501", kind: "ordinary_current_planning", expense: { id: 81, title: "Licenze" },
  row: { id: 501, type: "quote", description: "Preventivo" }, plafond: null,
  dimensions: { cost_center: { id: 9, name: "IT" }, vendor: null, project: null, contract: null }, amount: measure,
  source_lock_version: 4, drill_down: { authorized: true, href: "/api/v1/expenses/81" },
};
const validSummary = {
  id: 91, status: "active", planning_year_id: 25, currency: "EUR", basis: "net", total: measure,
  effective_date: "2026-08-13", recorded_at: "2026-08-13T10:30:00Z", approved_by: { id: 5, name: "Mario Rossi" }, note: null, annulled_at: null, annulled_by: null, annulment_note: null,
};
const validDetail = {
  id: 91, status: "active", planning_year: { id: 25, year_label: 2026 }, currency: "EUR", basis: "net", total: measure,
  effective_date: "2026-08-13", recorded_at: "2026-08-13T10:30:00Z", approved_by: { id: 5, name: "Mario Rossi" }, note: null,
  composition: { schema_version: "budget-proposal-composition/v1", fingerprint: `sha256:${"a".repeat(64)}`, contributor_count: 1 }, contributors: [contributor], annulment: null,
};

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

  it("reads the paginated immutable approval history without inventing filter parameters", async () => {
    const page = {
      data: [validSummary],
      links: { first: "?page=1", last: "?page=1", prev: null, next: null },
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
    };
    vi.mocked(apiClient.get).mockResolvedValue({ data: page });

    await expect(getBudgetApprovalHistory(25, { page: 1, per_page: 25 })).resolves.toEqual(page);
    expect(apiClient.get).toHaveBeenCalledWith("/api/v1/budget/25/approvals", { params: { page: 1, per_page: 25 } });
  });

  it("reads one stored approval snapshot from its authorized detail endpoint", async () => {
    const approval = validDetail;
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data: approval } });

    await expect(getBudgetApprovalDetail(25, 91)).resolves.toEqual(approval);
    expect(apiClient.get).toHaveBeenCalledWith("/api/v1/budget/25/approvals/91");
  });

  it("fails closed when a history response contains a legacy status", async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { data: [{ id: 91, status: "draft" }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } },
    });

    await expect(getBudgetApprovalHistory(25)).rejects.toThrow(/non rispetta il contratto/i);
  });

  it("fails closed for cross-year, unsafe meta and corrupt immutable detail data", async () => {
    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: { data: [{ ...validSummary, planning_year_id: 26 }], links: {}, meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } },
    });
    await expect(getBudgetApprovalHistory(25, { page: 1 })).rejects.toThrow(/non rispetta il contratto/i);

    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: { data: [validSummary], links: {}, meta: { current_page: Number.NaN, last_page: 1, per_page: 25, total: 1 } },
    });
    await expect(getBudgetApprovalHistory(25, { page: 1 })).rejects.toThrow(/non rispetta il contratto/i);

    vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: { ...validDetail, id: 92, total: { ...measure, net: "10.001" } } } });
    await expect(getBudgetApprovalDetail(25, 91)).rejects.toThrow(/non rispetta il contratto/i);

    vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: { ...validDetail, total: { ...measure, net: "-0.00" } } } });
    await expect(getBudgetApprovalDetail(25, 91)).rejects.toThrow(/non rispetta il contratto/i);

    vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: { ...validDetail, total: { ...measure, official: "12.00" } } } });
    await expect(getBudgetApprovalDetail(25, 91)).rejects.toThrow(/non rispetta il contratto/i);

    vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: { ...validDetail, total: { ...measure, vat: "1.99" } } } });
    await expect(getBudgetApprovalDetail(25, 91)).rejects.toThrow(/non rispetta il contratto/i);

    vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: { ...validDetail, contributors: [{ ...contributor, row: null }] } } });
    await expect(getBudgetApprovalDetail(25, 91)).rejects.toThrow(/non rispetta il contratto/i);

    vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: { ...validDetail, status: "annulled", annulment: { annulled_at: "2026-08-13T11:00:00Z", annulled_by: { id: 5, name: "Mario Rossi" }, note: "" } } } });
    await expect(getBudgetApprovalDetail(25, 91)).rejects.toThrow(/non rispetta il contratto/i);
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

  it("converts a real 422 response envelope so the approval modal can read the effective-date field message", async () => {
    const response = {
      status: 422,
      statusText: "Unprocessable Content",
      headers: { "x-correlation-id": "tenant-date-reference" },
      config: { headers: {} },
      data: { error: { code: "VALIDATION_FAILED", message: "Validation failed.", fields: { effective_date: ["La data di efficacia non può essere successiva a oggi nel fuso del Tenant."] } } },
    } as unknown as AxiosResponse;
    vi.mocked(apiClient.post).mockRejectedValue(new AxiosError("Request failed", "ERR_BAD_REQUEST", undefined, undefined, response));

    await expect(approveBudgetProposal(25, {
      effective_date: "2026-08-14",
      note: null,
      composition: { schema_version: "budget-proposal-composition/v1", fingerprint: `sha256:${"a".repeat(64)}`, versions: { budget_lock_version: 7, projection_version: "annual-economic-projection/v1" } },
    })).rejects.toMatchObject({
      code: "VALIDATION_FAILED",
      status: 422,
      correlationId: "tenant-date-reference",
      fields: { effective_date: ["La data di efficacia non può essere successiva a oggi nel fuso del Tenant."] },
    });
  });
});
