import { useEffect, useState } from "react";
import { getDashboard, type ReportingDataset } from "../../api/dashboard";
import { ApiError } from "../../api/client";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import Alert from "../../components/ui/alert/Alert";
import DashboardView from "../../components/dashboard/DashboardView";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";

interface DashboardState {
  tenantId: number;
  data: ReportingDataset | null;
  error: ApiError | null;
}

export default function Home() {
  const { data: applicationContext, loading: contextLoading, hasAbility } =
    useApplicationContext();
  const { selectedPlanningYearId, loading: planningYearLoading } = usePlanningYear();
  const [dashboardState, setDashboardState] = useState<DashboardState | null>(
    null,
  );

  const tenantId = applicationContext?.tenant?.id ?? null;
  const canViewDashboard = hasAbility("dashboard.view");

  useEffect(() => {
    if (contextLoading || planningYearLoading || tenantId === null || selectedPlanningYearId === null || !canViewDashboard) {
      return;
    }

    let active = true;

    void getDashboard({ planning_year_id: selectedPlanningYearId })
      .then((dashboard) => {
        if (active) {
          setDashboardState({ tenantId, data: dashboard, error: null });
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setDashboardState({
            tenantId,
            data: null,
            error: ApiError.from(error),
          });
        }
      });

    return () => {
      active = false;
    };
  }, [canViewDashboard, contextLoading, planningYearLoading, selectedPlanningYearId, tenantId]);

  const currentDashboardState =
    dashboardState?.tenantId === tenantId ? dashboardState : null;

  let content;

  if (contextLoading || planningYearLoading) {
    content = (
      <Alert
        variant="info"
        title="Loading application context"
        message="Checking the current user, tenant, and dashboard ability."
      />
    );
  } else if (tenantId === null) {
    content = (
      <Alert
        variant="warning"
        title="Tenant required"
        message="Enter a tenant from the header before loading the dashboard."
      />
    );
  } else if (!canViewDashboard) {
    content = (
      <Alert
        variant="warning"
        title="Dashboard unavailable"
        message="The current application context does not grant the dashboard.view ability."
      />
    );
  } else if (currentDashboardState === null) {
    content = (
      <Alert
        variant="info"
        title="Loading dashboard"
        message="Requesting the dashboard dataset for the current tenant."
      />
    );
  } else if (currentDashboardState.error) {
    const { correlationId, message } = currentDashboardState.error;

    content = (
      <Alert
        variant="error"
        title="Dashboard request failed"
        message={
          correlationId
            ? `${message} Correlation ID: ${correlationId}`
            : message
        }
      />
    );
  } else {
    const dashboard = currentDashboardState.data;
    content = dashboard ? <DashboardView dataset={dashboard} /> : null;
  }

  return (
    <>
      <PageMeta
        title="Dashboard | Master Plan IT"
        description="Decision dashboard for the selected planning year"
      />
      <PageBreadcrumb pageTitle="Dashboard" />
      {content}
    </>
  );
}
