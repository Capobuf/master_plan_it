import { useApplicationContext } from "../../context/ApplicationContext";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import Alert from "../../components/ui/alert/Alert";
import ReportsView from "../../components/reports/ReportsView";

export default function ReportsHome() {
  const { data, loading, hasAbility } = useApplicationContext();
  const tenantId = data?.tenant?.id ?? null;
  const canView = hasAbility("report.view");
  let intro: React.ReactNode = null;
  if (loading) intro = <Alert variant="info" title="Loading application context" message="Checking the current tenant and report ability." />;
  else if (tenantId === null) intro = <Alert variant="warning" title="Tenant required" message="Enter a tenant from the header before loading reports." />;
  else if (!canView) intro = <Alert variant="warning" title="Reports unavailable" message="The current application context does not grant the report.view ability." />;

  return (
    <>
      <PageMeta title="Reports | Master Plan IT" description="Paginated economic report" />
      <PageBreadcrumb pageTitle="Reports" />
      {intro}
      {!intro && <ReportsView tenantId={tenantId} canView={canView} />}
    </>
  );
}
