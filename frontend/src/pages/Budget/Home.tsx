import { useEffect, useState } from "react";
import { getBudget, getBudgetApprovalPreview, type AnnualBudget, type BudgetApprovalPreview } from "../../api/budget";
import { ApiError } from "../../api/client";
import BudgetView from "../../components/budget/BudgetView";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";

function hasCoherentProposal(overview: AnnualBudget, preview: BudgetApprovalPreview): boolean {
  const overviewComposition = overview.proposal.composition;
  const previewComposition = preview.composition;
  return typeof overview.surface_fingerprint === "string"
    && overview.surface_fingerprint.length > 0
    && typeof preview.surface_fingerprint === "string"
    && preview.surface_fingerprint.length > 0
    && overview.surface_fingerprint === preview.surface_fingerprint
    && overviewComposition.fingerprint === previewComposition.fingerprint
    && overviewComposition.versions.budget_lock_version === previewComposition.versions.budget_lock_version;
}

export default function BudgetHome() {
  const { data: applicationContext, loading: contextLoading, hasAbility } = useApplicationContext();
  const { selectedPlanningYearId, loading: planningYearLoading } = usePlanningYear();
  const [state, setState] = useState<{ tenantId: number; planningYearId: number; data: AnnualBudget | null; preview: BudgetApprovalPreview | null; error: ApiError | null } | null>(null);
  const tenantId = applicationContext?.tenant?.id ?? null;
  const canView = hasAbility("budget.view");

  useEffect(() => {
    if (contextLoading || planningYearLoading || tenantId === null || selectedPlanningYearId === null || !canView) return;
    let active = true;
    void Promise.all([getBudget({ planning_year_id: selectedPlanningYearId }), getBudgetApprovalPreview(selectedPlanningYearId)])
      .then(([data, preview]) => {
        if (!active) return;
        if (!hasCoherentProposal(data, preview)) {
          setState({ tenantId, planningYearId: selectedPlanningYearId, data: null, preview: null, error: new ApiError({ message: "La proposta è cambiata durante l’aggiornamento. Riprova per visualizzare una composizione coerente.", code: "BUDGET_COMPOSITION_STALE" }) });
          return;
        }
        setState({ tenantId, planningYearId: selectedPlanningYearId, data, preview, error: null });
      })
      .catch((error: unknown) => { if (active) setState({ tenantId, planningYearId: selectedPlanningYearId, data: null, preview: null, error: ApiError.from(error) }); });
    return () => { active = false; };
  }, [canView, contextLoading, planningYearLoading, selectedPlanningYearId, tenantId]);

  async function refreshBudget(): Promise<void> {
    if (tenantId === null || selectedPlanningYearId === null || !canView) return;
    const [data, preview] = await Promise.all([
      getBudget({ planning_year_id: selectedPlanningYearId }),
      getBudgetApprovalPreview(selectedPlanningYearId),
    ]);
    if (!hasCoherentProposal(data, preview)) {
      throw new ApiError({
        message: "La proposta è cambiata durante l’aggiornamento. Riprova per visualizzare una composizione coerente.",
        code: "BUDGET_COMPOSITION_STALE",
      });
    }
    setState({ tenantId, planningYearId: selectedPlanningYearId, data, preview, error: null });
  }

  const current = state?.tenantId === tenantId && state.planningYearId === selectedPlanningYearId ? state : null;
  let content: React.ReactNode;
  if (contextLoading || planningYearLoading) content = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant e dell'anno di pianificazione in corso." />;
  else if (tenantId === null) content = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant dall'intestazione per aprire il Budget." />;
  else if (!canView) content = <Alert variant="warning" title="Budget non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina." />;
  else if (current === null) content = <Alert variant="info" title="Caricamento del Budget" message="Recupero dei dati per l'anno selezionato." />;
  else if (current.error) content = <Alert variant="error" title="Caricamento non riuscito" message={current.error.correlationId ? `${current.error.message} Riferimento tecnico: ${current.error.correlationId}` : current.error.message} />;
  else if (current.data && current.preview) content = <BudgetView dataset={current.data} preview={current.preview} onRefresh={refreshBudget} />;
  else content = <Alert variant="info" title="Nessun dato Budget" message="Non sono disponibili dati per la selezione corrente." />;

  return <><PageMeta title="Budget | Master Plan IT" description="Budget annuale e ciclo di approvazione" /><PageBreadcrumb pageTitle="Budget" />{content}</>;
}
