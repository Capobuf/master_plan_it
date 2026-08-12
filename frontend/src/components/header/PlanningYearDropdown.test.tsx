import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { MemoryRouter, useLocation } from "react-router";

import { usePlanningYear } from "../../context/PlanningYearContext";
import PlanningYearDropdown from "./PlanningYearDropdown";

vi.mock("../../context/PlanningYearContext", () => ({
  usePlanningYear: vi.fn(),
}));

const selectPlanningYear = vi.fn<(planningYearId: number, authorizeFollowingNavigation?: boolean) => boolean>();
const planningYears = [
  { id: 1, year_label: 2026, start_date: "2026-01-01", end_date: "2026-12-31", active: true, lock_version: 1 },
  { id: 2, year_label: 2027, start_date: "2027-01-01", end_date: "2027-12-31", active: true, lock_version: 1 },
];

function LocationProbe() {
  const location = useLocation();
  const notice = (location.state as { workspaceNotice?: string } | null)?.workspaceNotice ?? "";
  return <output>{`${location.pathname}|${notice}`}</output>;
}

function renderDropdown() {
  render(
    <MemoryRouter initialEntries={["/spese/81/modifica"]}>
      <PlanningYearDropdown />
      <LocationProbe />
    </MemoryRouter>,
  );
}

describe("PlanningYearDropdown", () => {
  beforeEach(() => {
    selectPlanningYear.mockReset();
    vi.mocked(usePlanningYear).mockReturnValue({
      planningYears,
      activePlanningYears: planningYears,
      selectedPlanningYear: planningYears[0],
      selectedPlanningYearId: 1,
      loading: false,
      error: null,
      hasDirtySources: false,
      refreshPlanningYears: vi.fn(),
      selectPlanningYear,
      registerDirtySource: vi.fn(),
      confirmDiscardChanges: vi.fn(),
      consumeAuthorizedNavigation: vi.fn(() => false),
    });
  });

  it("keeps the scoped expense editor when discarding dirty changes is refused", () => {
    selectPlanningYear.mockReturnValue(false);
    renderDropdown();

    fireEvent.click(screen.getByRole("button", { name: /Anno di pianificazione/ }));
    fireEvent.click(screen.getByRole("button", { name: "2027" }));

    expect(screen.getByText("/spese/81/modifica|")).toBeInTheDocument();
    expect(selectPlanningYear).toHaveBeenCalledWith(2, true);
  });

  it("returns scoped expense routes to the register after an accepted year change", () => {
    selectPlanningYear.mockReturnValue(true);
    renderDropdown();

    fireEvent.click(screen.getByRole("button", { name: /Anno di pianificazione/ }));
    fireEvent.click(screen.getByRole("button", { name: "2027" }));

    expect(screen.getByText("/spese|Ora stai consultando il Registro Spese 2027.")).toBeInTheDocument();
    expect(selectPlanningYear).toHaveBeenCalledWith(2, true);
  });
});
