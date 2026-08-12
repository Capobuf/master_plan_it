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
  planningYearId: number;
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
          setDashboardState({ tenantId, planningYearId: selectedPlanningYearId, data: dashboard, error: null });
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setDashboardState({
            tenantId,
            planningYearId: selectedPlanningYearId,
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
    dashboardState?.tenantId === tenantId
      && dashboardState.planningYearId === selectedPlanningYearId
      ? dashboardState
      : null;

  let content;

  if (contextLoading || planningYearLoading) {
    content = (
      <Alert
        variant="info"
        title="Caricamento del contesto"
        message="Verifica del Tenant e dell'anno di pianificazione in corso."
      />
    );
  } else if (tenantId === null) {
    content = (
      <Alert
        variant="warning"
        title="Tenant richiesto"
        message="Seleziona un Tenant dall'intestazione per aprire la Panoramica."
      />
    );
  } else if (!canViewDashboard) {
    content = (
      <Alert
        variant="warning"
        title="Panoramica non disponibile"
        message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina."
      />
    );
  } else if (currentDashboardState === null) {
    content = (
      <Alert
        variant="info"
        title="Caricamento della Panoramica"
        message="Recupero dei dati per l'anno selezionato."
      />
    );
  } else if (currentDashboardState.error) {
    const { correlationId, message } = currentDashboardState.error;

    content = (
      <Alert
        variant="error"
        title="Caricamento non riuscito"
        message={
          correlationId
            ? `${message} Riferimento tecnico: ${correlationId}`
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
        title="Panoramica | Master Plan IT"
        description="Panoramica economica dell'anno di pianificazione selezionato"
      />
      <PageBreadcrumb pageTitle="Panoramica" />
      {content}
    </>
  );
}
