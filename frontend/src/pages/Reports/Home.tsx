import { useApplicationContext } from "../../context/ApplicationContext";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import Alert from "../../components/ui/alert/Alert";
import ReportsView from "../../components/reports/ReportsView";
import { usePlanningYear } from "../../context/PlanningYearContext";

export default function ReportsHome() {
  const { data, loading, hasAbility } = useApplicationContext();
  const tenantId = data?.tenant?.id ?? null;
  const canView = hasAbility("report.view");
  const { selectedPlanningYearId, loading: planningYearLoading } = usePlanningYear();
  let intro: React.ReactNode = null;
  if (loading || planningYearLoading) intro = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant e dell'anno di pianificazione in corso." />;
  else if (tenantId === null) intro = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant dall'intestazione per aprire il Report." />;
  else if (!canView) intro = <Alert variant="warning" title="Report non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina." />;

  return (
    <>
      <PageMeta title="Report | Master Plan IT" description="Report economico paginato" />
      <PageBreadcrumb pageTitle="Report" />
      {intro}
      {!intro && <ReportsView tenantId={tenantId} planningYearId={selectedPlanningYearId} canView={canView} canLoadCostCenters={hasAbility("cost-center.view")} canLoadProjects={hasAbility("project.view")} canLoadVendors={hasAbility("vendor.view")} />}
    </>
  );
}
