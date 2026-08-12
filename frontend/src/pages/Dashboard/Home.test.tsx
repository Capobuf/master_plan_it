import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { getDashboard, type ReportingDataset } from "../../api/dashboard";
import Home from "./Home";

let selectedPlanningYearId = 7;

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    data: { tenant: { id: 1 } },
    loading: false,
    hasAbility: () => true,
  }),
}));

vi.mock("../../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({ selectedPlanningYearId, loading: false }),
}));

vi.mock("../../api/dashboard", () => ({ getDashboard: vi.fn() }));
vi.mock("../../components/dashboard/DashboardView", () => ({
  default: ({ dataset }: { dataset: ReportingDataset }) => (
    <p>Panoramica anno {dataset.planning_year_id}</p>
  ),
}));
vi.mock("../../components/common/PageBreadCrumb", () => ({ default: () => null }));
vi.mock("../../components/common/PageMeta", () => ({ default: () => null }));

describe("DashboardHome workspace responses", () => {
  beforeEach(() => {
    selectedPlanningYearId = 7;
    vi.clearAllMocks();
  });

  it("discards a late response from the prior Planning Year", async () => {
    let resolve2026!: (value: ReportingDataset) => void;
    vi.mocked(getDashboard).mockImplementation(({ planning_year_id: year }) => year === 7
      ? new Promise((resolve) => { resolve2026 = resolve; })
      : Promise.resolve({ planning_year_id: 8 } as ReportingDataset));

    const view = render(<Home />);
    await waitFor(() => expect(getDashboard).toHaveBeenCalledWith({ planning_year_id: 7 }));

    selectedPlanningYearId = 8;
    view.rerender(<Home />);
    expect(await screen.findByText("Panoramica anno 8")).toBeInTheDocument();

    resolve2026({ planning_year_id: 7 } as ReportingDataset);
    await waitFor(() => expect(screen.queryByText("Panoramica anno 7")).not.toBeInTheDocument());
    expect(screen.getByText("Panoramica anno 8")).toBeInTheDocument();
  });
});
