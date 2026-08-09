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
  if (contextLoading || planningYearLoading) content = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant e dell'anno di pianificazione in corso." />;
  else if (tenantId === null) content = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant dall'intestazione per aprire il Budget." />;
  else if (!canView) content = <Alert variant="warning" title="Budget non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina." />;
  else if (current === null) content = <Alert variant="info" title="Caricamento del Budget" message="Recupero dei dati per l'anno selezionato." />;
  else if (current.error) content = <Alert variant="error" title="Caricamento non riuscito" message={current.error.correlationId ? `${current.error.message} Riferimento tecnico: ${current.error.correlationId}` : current.error.message} />;
  else if (current.data) content = <BudgetView dataset={current.data} />;
  else content = <Alert variant="info" title="Nessun dato Budget" message="Non sono disponibili dati per la selezione corrente." />;

  return (
    <>
      <PageMeta title="Budget | Master Plan IT" description="Budget corrente dell'anno selezionato" />
      <PageBreadcrumb pageTitle="Budget" actions={<PlanningYearSelector />} />
      {content}
    </>
  );
}
