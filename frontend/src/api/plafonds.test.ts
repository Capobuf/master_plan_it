import { describe, expect, it, vi } from "vitest";
import { apiClient } from "./client";
import { addAllocationAdjustment, createPlafond, getPlafond, getPlafondReport, listPlafonds, previewAllocationAdjustment } from "./plafonds";

describe("plafonds adapter", () => {
  it("uses only dedicated Plafond routes and preserves exact decimal request strings", async () => {
    const get = vi.spyOn(apiClient, "get").mockResolvedValue({ data: { data: [], meta: {}, links: {}, currency: "EUR", basis: "net" } } as never);
    const post = vi.spyOn(apiClient, "post").mockResolvedValue({ data: { data: { id: 41 } } } as never);
    await listPlafonds({ planning_year_id: 25, cost_center_id: 9 });
    await getPlafond(41, 25);
    await createPlafond({ planning_year_id: 25, cost_center_id: 9, title: "Plafond", initial_allocation: { description: "Iniziale", entered_amount: "3000.00", amount_includes_vat: false, vat_rate: "22.00", date: "2026-08-12" } });
    await previewAllocationAdjustment(41, { lock_version: 3, adjustment: { description: "Riduzione", entered_amount: "-500.00", amount_includes_vat: false, date: "2026-08-12" } });
    await addAllocationAdjustment(41, { lock_version: 3, adjustment: { description: "Aumento", entered_amount: "1000.00", amount_includes_vat: false, date: "2026-08-12" } });
    await getPlafondReport({ planning_year_id: 25, cost_center_id: 9 });
    expect(get).toHaveBeenCalledWith("/api/v1/plafonds", { params: { planning_year_id: 25, cost_center_id: 9 } });
    expect(get).toHaveBeenCalledWith("/api/v1/plafonds/41", { params: { planning_year_id: 25 } });
    expect(get).toHaveBeenCalledWith("/api/v1/plafonds/report", { params: { planning_year_id: 25, cost_center_id: 9 } });
    expect(post.mock.calls[0][0]).toBe("/api/v1/plafonds");
    expect(post.mock.calls[0][1]).toMatchObject({ initial_allocation: { entered_amount: "3000.00" } });
    expect(post.mock.calls[1][0]).toBe("/api/v1/plafonds/41/allocation-adjustments/preview");
    expect(post.mock.calls[2][0]).toBe("/api/v1/plafonds/41/allocation-adjustments");
  });
});
