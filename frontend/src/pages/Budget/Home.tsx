import { useEffect, useState } from "react";
import { getBudget } from "../../api/budget";
import { ApiError } from "../../api/client";
import type { ReportingDataset } from "../../api/dashboard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import Alert from "../../components/ui/alert/Alert";
import BudgetView from "../../components/budget/BudgetView";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";
import PlanningYearSelector from "../../components/dashboard/PlanningYearSelector";

export default function BudgetHome() {
  const { data: applicationContext, loading: contextLoading, hasAbility } = useApplicationContext();
  const { selectedPlanningYearId, loading: planningYearLoading } = usePlanningYear();
  const [state, setState] = useState<{ tenantId: number; data: ReportingDataset | null; error: ApiError | null } | null>(null);
  const tenantId = applicationContext?.tenant?.id ?? null;
  const canView = hasAbility("budget.view");

  useEffect(() => {
    if (contextLoading || planningYearLoading || tenantId === null || selectedPlanningYearId === null || !canView) return;
    let active = true;
    void getBudget({ planning_year_id: selectedPlanningYearId })
      .then((data) => active && setState({ tenantId, data, error: null }))
      .catch((error: unknown) => active && setState({ tenantId, data: null, error: ApiError.from(error) }));
    return () => { active = false; };
  }, [canView, contextLoading, planningYearLoading, selectedPlanningYearId, tenantId]);

  const current = state?.tenantId === tenantId ? state : null;
  let content: React.ReactNode;
  if (contextLoading || planningYearLoading) content = <Alert variant="info" title="Loading application context" message="Checking the current tenant, planning year, and budget ability." />;
  else if (tenantId === null) content = <Alert variant="warning" title="Tenant required" message="Enter a tenant from the header before loading the budget." />;
  else if (!canView) content = <Alert variant="warning" title="Budget unavailable" message="The current application context does not grant the budget.view ability." />;
  else if (current === null) content = <Alert variant="info" title="Loading budget" message="Requesting the current budget for the selected tenant." />;
  else if (current.error) content = <Alert variant="error" title="Budget request failed" message={current.error.correlationId ? `${current.error.message} Correlation ID: ${current.error.correlationId}` : current.error.message} />;
  else if (current.data) content = <BudgetView dataset={current.data} />;
  else content = <Alert variant="info" title="No budget data" message="The budget endpoint returned no dataset." />;

  return (
    <>
      <PageMeta title="Budget | Master Plan IT" description="Current rolling budget" />
      <PageBreadcrumb pageTitle="Budget" />
      <PlanningYearSelector />
      {content}
    </>
  );
}
