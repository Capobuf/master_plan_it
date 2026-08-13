import { useEffect } from "react";
import { useSearchParams } from "react-router";
import type { PlanningYear } from "../context/PlanningYearContext";

interface QueryPlanningYearContext {
  activePlanningYears: readonly PlanningYear[];
  loading: boolean;
  selectedPlanningYearId: number | null;
  selectPlanningYear: (planningYearId: number, authorizeFollowingNavigation?: boolean) => boolean;
}

function queryPlanningYearId(value: string | null): number | null {
  if (value === null || !/^\d+$/.test(value)) return null;
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
}

/** Applies a shared-link year only when it belongs to the current accessible Tenant context. */
export function useQueryPlanningYear({
  activePlanningYears,
  loading,
  selectedPlanningYearId,
  selectPlanningYear,
}: QueryPlanningYearContext): { isApplyingQueryPlanningYear: boolean } {
  const [searchParams] = useSearchParams();
  const requestedPlanningYearId = queryPlanningYearId(searchParams.get("planning_year_id"));
  const hasRequestedPlanningYear = requestedPlanningYearId !== null && activePlanningYears.some((planningYear) => planningYear.id === requestedPlanningYearId);
  const isApplyingQueryPlanningYear = !loading && hasRequestedPlanningYear && requestedPlanningYearId !== selectedPlanningYearId;

  useEffect(() => {
    if (isApplyingQueryPlanningYear && requestedPlanningYearId !== null) {
      selectPlanningYear(requestedPlanningYearId, true);
    }
  }, [isApplyingQueryPlanningYear, requestedPlanningYearId, selectPlanningYear]);

  return { isApplyingQueryPlanningYear };
}
