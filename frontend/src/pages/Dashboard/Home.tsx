import { useEffect, useState } from "react";
import { getDashboard, type ReportingDataset } from "../../api/dashboard";
import { ApiError } from "../../api/client";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";

interface DashboardState {
  tenantId: number;
  data: ReportingDataset | null;
  error: ApiError | null;
}

export default function Home() {
  const { data: applicationContext, loading: contextLoading, hasAbility } =
    useApplicationContext();
  const [dashboardState, setDashboardState] = useState<DashboardState | null>(
    null,
  );

  const tenantId = applicationContext?.tenant?.id ?? null;
  const canViewDashboard = hasAbility("dashboard.view");

  useEffect(() => {
    if (contextLoading || tenantId === null || !canViewDashboard) {
      return;
    }

    let active = true;

    void getDashboard()
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
  }, [canViewDashboard, contextLoading, tenantId]);

  const currentDashboardState =
    dashboardState?.tenantId === tenantId ? dashboardState : null;

  let content;

  if (contextLoading) {
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
    const year = dashboard?.scope?.year ?? "not provided";
    const currency = dashboard?.scope?.currency ?? "not provided";
    const selectedYearId = dashboard?.selected_year_id ?? "not provided";
    const hasEconomicData =
      dashboard?.has_economic_data === undefined
        ? "not provided"
        : String(dashboard.has_economic_data);

    content = (
      <Alert
        variant="success"
        title="Dashboard API connected"
        message={`Year: ${year}. Currency: ${currency}. Selected year ID: ${selectedYearId}. Economic data present: ${hasEconomicData}.`}
      />
    );
  }

  return (
    <>
      <PageMeta
        title="Dashboard | Master Plan IT"
        description="Protected Master Plan IT dashboard API connectivity surface"
      />
      <PageBreadcrumb pageTitle="Dashboard" />
      <ComponentCard
        title="Dashboard connectivity"
        desc="Live data from the protected dashboard API for the current tenant."
      >
        {content}
      </ComponentCard>
    </>
  );
}
