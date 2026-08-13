import { useEffect } from "react";
import { useSearchParams } from "react-router";
import { usePlanningYear } from "../context/PlanningYearContext";

function queryPlanningYearId(value: string | null): number | null {
  if (value === null || !/^\d+$/.test(value)) return null;
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
}

/** Applies a shared-link year only when it belongs to the current accessible Tenant context. */
export function useQueryPlanningYear(): void {
  const [searchParams] = useSearchParams();
  const { activePlanningYears, loading, selectedPlanningYearId, selectPlanningYear } = usePlanningYear();
  const requestedPlanningYearId = queryPlanningYearId(searchParams.get("planning_year_id"));

  useEffect(() => {
    if (loading || requestedPlanningYearId === null || requestedPlanningYearId === selectedPlanningYearId) return;
    if (activePlanningYears.some((planningYear) => planningYear.id === requestedPlanningYearId)) {
      selectPlanningYear(requestedPlanningYearId, true);
    }
  }, [activePlanningYears, loading, requestedPlanningYearId, selectPlanningYear, selectedPlanningYearId]);
}
