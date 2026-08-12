import { act, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { PlanningYearProvider, usePlanningYear } from "./PlanningYearContext";

const get = vi.hoisted(() => vi.fn());
vi.mock("../api/client", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../api/client")>();
  return { ...actual, apiClient: { get } };
});

function Probe() {
  const { selectedPlanningYearId, selectPlanningYear, loading } = usePlanningYear();
  return <><span>{loading ? "loading" : String(selectedPlanningYearId)}</span><button onClick={() => selectPlanningYear(2)}>Anno 2</button></>;
}

const years = (ids: number[]) => ({ data: { data: ids.map((id) => ({ id, year_label: 2024 + id, start_date: "2025-01-01", end_date: "2025-12-31", active: true, lock_version: 1 })), meta: { current_page: 1, last_page: 1, per_page: 100, total: ids.length } } });

describe("PlanningYearContext", () => {
  it("ignores a late prior-tenant response after the context has changed", async () => {
    let resolveFirst!: (value: ReturnType<typeof years>) => void;
    get.mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve; })).mockResolvedValueOnce(years([2]));
    const view = render(<PlanningYearProvider tenantId={1} canRead><Probe /></PlanningYearProvider>);
    view.rerender(<PlanningYearProvider tenantId={2} canRead><Probe /></PlanningYearProvider>);
    await waitFor(() => expect(screen.getByText("2")).toBeInTheDocument());
    await act(async () => resolveFirst(years([1])));
    expect(screen.getByText("2")).toBeInTheDocument();
  });
});
