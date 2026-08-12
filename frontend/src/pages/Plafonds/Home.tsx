import { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import { listPlafonds, type PlafondListResponse } from "../../api/plafonds";
import { ApiError } from "../../api/client";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import PlafondRegister from "../../components/plafonds/PlafondRegister";
import Alert from "../../components/ui/alert/Alert";
import Button from "../../components/ui/button/Button";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";
import { routes } from "../../navigation/routes";

export default function PlafondsHome() {
  const navigate = useNavigate(); const { hasAbility } = useApplicationContext(); const { selectedPlanningYearId } = usePlanningYear();
  const [data, setData] = useState<PlafondListResponse | null>(null); const [error, setError] = useState<ApiError | null>(null);
  useEffect(() => { if (!hasAbility("expense.view") || selectedPlanningYearId === null) return; let active = true; setData(null); setError(null); void listPlafonds({ planning_year_id: selectedPlanningYearId, per_page: 100 }).then((response) => { if (active) setData(response); }).catch((cause: unknown) => { if (active) setError(ApiError.from(cause)); }); return () => { active = false; }; }, [hasAbility, selectedPlanningYearId]);
  let body: React.ReactNode;
  if (!hasAbility("expense.view")) body = <Alert variant="warning" title="Registro non disponibile" message="Non disponi dell’autorizzazione necessaria." />;
  else if (selectedPlanningYearId === null) body = <Alert variant="warning" title="Anno richiesto" message="Seleziona un Planning Year dall’intestazione." />;
  else if (error) body = <Alert variant="error" title="Richiesta non riuscita" message={error.message} />;
  else if (!data) body = <Alert variant="info" title="Caricamento Plafond" message="Recupero delle Allocazioni in corso." />;
  else if (data.data.length === 0) body = <Alert variant="info" title="Nessun Plafond" message="Non ci sono Plafond per l’anno selezionato." />;
  else body = <PlafondRegister plafonds={data.data} />;
  return <><PageMeta title="Plafond | Master Plan IT" description="Registro Plafond" /><PageBreadcrumb pageTitle="Plafond" subtitle="Allocazione, copertura prevista, consumato e disponibile." actions={hasAbility("expense.create") ? <Button size="sm" onClick={() => navigate(routes.nuovoPlafond)}>Nuovo Plafond</Button> : null} />{body}</>;
}
