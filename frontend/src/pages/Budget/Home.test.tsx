import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { getBudget, type AnnualBudget } from "../../api/budget";
import BudgetHome from "./Home";

let selectedPlanningYearId = 7;

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    data: { tenant: { id: 1, timezone: "Europe/Rome" } },
    loading: false,
    hasAbility: () => true,
  }),
}));

vi.mock("../../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({ selectedPlanningYearId, loading: false }),
}));

vi.mock("../../api/budget", () => ({ getBudget: vi.fn() }));
vi.mock("../../components/budget/BudgetView", () => ({
  default: ({ dataset }: { dataset: AnnualBudget }) => (
    <p>Budget anno {dataset.planning_year.id}</p>
  ),
}));
vi.mock("../../components/common/PageBreadCrumb", () => ({ default: () => null }));
vi.mock("../../components/common/PageMeta", () => ({ default: () => null }));

describe("BudgetHome workspace responses", () => {
  beforeEach(() => {
    selectedPlanningYearId = 7;
    vi.clearAllMocks();
  });

  it("discards a late response from the prior Planning Year", async () => {
    let resolve2026!: (value: AnnualBudget) => void;
    vi.mocked(getBudget).mockImplementation((query) => query?.planning_year_id === 7
      ? new Promise((resolve) => { resolve2026 = resolve; })
      : Promise.resolve({ planning_year: { id: 8 } } as AnnualBudget));

    const view = render(<BudgetHome />);
    await waitFor(() => expect(getBudget).toHaveBeenCalledWith({ planning_year_id: 7 }));

    selectedPlanningYearId = 8;
    view.rerender(<BudgetHome />);
    expect(await screen.findByText("Budget anno 8")).toBeInTheDocument();

    resolve2026({ planning_year: { id: 7 } } as AnnualBudget);
    await waitFor(() => expect(screen.queryByText("Budget anno 7")).not.toBeInTheDocument());
    expect(screen.getByText("Budget anno 8")).toBeInTheDocument();
  });
});
