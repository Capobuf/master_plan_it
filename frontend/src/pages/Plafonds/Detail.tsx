import { useEffect, useState } from "react";
import { useParams } from "react-router";
import { getPlafond, type PlafondDetail as PlafondDetailData } from "../../api/plafonds";
import { ApiError } from "../../api/client";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import PlafondDetail from "../../components/plafonds/PlafondDetail";
import PlafondEditor from "../../components/plafonds/PlafondEditor";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";

export default function PlafondPage() {
  const { plafondId } = useParams<{ plafondId: string }>(); const id = plafondId && /^\d+$/.test(plafondId) ? Number(plafondId) : null;
  const { hasAbility } = useApplicationContext(); const { selectedPlanningYearId } = usePlanningYear(); const [detail, setDetail] = useState<PlafondDetailData | null>(null); const [error, setError] = useState<ApiError | null>(null);
  useEffect(() => {
    if (id === null || selectedPlanningYearId === null || !hasAbility("expense.view")) return;
    let active = true;
    setDetail(null); setError(null);
    void getPlafond(id, selectedPlanningYearId)
      .then((result) => { if (active) setDetail(result); })
      .catch((cause: unknown) => { if (active) setError(ApiError.from(cause)); });

    return () => { active = false; };
  }, [hasAbility, id, selectedPlanningYearId]);
  let body: React.ReactNode;
  if (!hasAbility("expense.view")) body = <Alert variant="warning" title="Dettaglio non disponibile" message="Non disponi dell’autorizzazione necessaria." />;
  else if (id === null) body = <Alert variant="error" title="Identificativo non valido" message="L’identificativo del Plafond non è valido." />;
  else if (selectedPlanningYearId === null) body = <Alert variant="warning" title="Anno richiesto" message="Seleziona un Planning Year dall’intestazione." />;
  else if (error) body = <Alert variant="error" title="Plafond non disponibile" message={error.message} />;
  else if (!detail) body = <Alert variant="info" title="Caricamento Plafond" message="Recupero del dettaglio in corso." />;
  else body = <div className="space-y-4"><section className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><h1 className="text-xl font-semibold text-gray-900 dark:text-white">{detail.title}</h1><p className="mt-1 text-sm text-gray-500">Centro di Costo: {detail.cost_center.name} · {detail.economic_year_label}</p></section><PlafondDetail plafond={detail} />{hasAbility("expense.update") && !detail.budget_context.read_only ? <PlafondEditor plafond={detail} onSaved={setDetail} /> : null}</div>;
  return <><PageMeta title="Dettaglio Plafond | Master Plan IT" description="Allocazione Plafond" /><PageBreadcrumb pageTitle="Plafond" />{body}</>;
}
