import { useEffect, useState } from "react";
import { getBudget, type AnnualBudget } from "../../api/budget";
import { ApiError } from "../../api/client";
import BudgetView from "../../components/budget/BudgetView";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";

export default function BudgetHome() {
  const { data: applicationContext, loading: contextLoading, hasAbility } = useApplicationContext();
  const { selectedPlanningYearId, loading: planningYearLoading } = usePlanningYear();
  const [asOf, setAsOf] = useState("");
  const [state, setState] = useState<{ tenantId: number; planningYearId: number; data: AnnualBudget | null; error: ApiError | null } | null>(null);
  const tenantId = applicationContext?.tenant?.id ?? null;
  const canView = hasAbility("budget.view");

  useEffect(() => {
    if (contextLoading || planningYearLoading || tenantId === null || selectedPlanningYearId === null || !canView) return;
    let active = true;
    void getBudget({ planning_year_id: selectedPlanningYearId, ...(asOf ? { as_of: asOf } : {}) })
      .then((data) => { if (active) setState({ tenantId, planningYearId: selectedPlanningYearId, data, error: null }); })
      .catch((error: unknown) => { if (active) setState({ tenantId, planningYearId: selectedPlanningYearId, data: null, error: ApiError.from(error) }); });
    return () => { active = false; };
  }, [asOf, canView, contextLoading, planningYearLoading, selectedPlanningYearId, tenantId]);

  const current = state?.tenantId === tenantId && state.planningYearId === selectedPlanningYearId ? state : null;
  let content: React.ReactNode;
  if (contextLoading || planningYearLoading) content = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant e dell'anno di pianificazione in corso." />;
  else if (tenantId === null) content = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant dall'intestazione per aprire il Budget." />;
  else if (!canView) content = <Alert variant="warning" title="Budget non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina." />;
  else if (current === null) content = <Alert variant="info" title="Caricamento del Budget" message="Recupero dei dati per l'anno selezionato." />;
  else if (current.error) content = <Alert variant="error" title="Caricamento non riuscito" message={current.error.correlationId ? `${current.error.message} Riferimento tecnico: ${current.error.correlationId}` : current.error.message} />;
  else if (current.data) content = <BudgetView key={`${current.data.budget.lock_version}:${current.data.mode}`} dataset={current.data} asOf={asOf} tenantTimezone={applicationContext?.tenant?.timezone ?? "UTC"} canApprove={hasAbility("expense.update")} canClose={hasAbility("planning-year.update")} onAsOfChange={setAsOf} onChange={(data) => setState({ tenantId, planningYearId: data.budget.planning_year_id, data, error: null })} />;
  else content = <Alert variant="info" title="Nessun dato Budget" message="Non sono disponibili dati per la selezione corrente." />;

  return <><PageMeta title="Budget | Master Plan IT" description="Budget annuale e ciclo di approvazione" /><PageBreadcrumb pageTitle="Budget" />{content}</>;
}
