import { render, waitFor } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router";
import { describe, expect, it, vi } from "vitest";
import { getPlafond } from "../../api/plafonds";
import PlafondPage from "./Detail";

const selectPlanningYear = vi.fn();

vi.mock("../../context/ApplicationContext", () => ({ useApplicationContext: () => ({ hasAbility: () => true }) }));
vi.mock("../../context/PlanningYearContext", () => ({ usePlanningYear: () => ({ selectedPlanningYearId: 7, loading: false, activePlanningYears: [{ id: 7 }, { id: 25 }], selectPlanningYear }) }));
vi.mock("../../api/plafonds", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/plafonds")>();
  return { ...actual, getPlafond: vi.fn() };
});
vi.mock("../../components/common/PageBreadCrumb", () => ({ default: () => null }));
vi.mock("../../components/common/PageMeta", () => ({ default: () => null }));

describe("PlafondPage", () => {
  it("applies a valid planning_year_id from a shared Plafond link", async () => {
    vi.mocked(getPlafond).mockImplementation(() => new Promise(() => undefined));
    render(<MemoryRouter initialEntries={["/plafonds/82?planning_year_id=25"]}><Routes><Route path="/plafonds/:plafondId" element={<PlafondPage />} /></Routes></MemoryRouter>);
    await waitFor(() => expect(selectPlanningYear).toHaveBeenCalledWith(25, true));
  });
});
