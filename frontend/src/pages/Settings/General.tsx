import Alert from "../../components/ui/alert/Alert";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import TenantGeneralSettingsForm from "../../components/settings/TenantGeneralSettingsForm";
import { useApplicationContext } from "../../context/ApplicationContext";

export default function TenantGeneralSettingsPage() {
  const { data, loading, hasAbility } = useApplicationContext();
  const canUpdate = hasAbility("tenant-settings.update");
  const canView = hasAbility("tenant-settings.view") || canUpdate;
  const tenant = data?.tenant ?? null;

  let content: React.ReactNode;

  if (loading) {
    content = (
      <Alert
        variant="info"
        title="Caricamento del contesto"
        message="Verifica del Tenant e delle autorizzazioni in corso."
      />
    );
  } else if (tenant === null) {
    content = (
      <Alert
        variant="warning"
        title="Tenant richiesto"
        message="Seleziona un Tenant prima di aprire le impostazioni generali."
      />
    );
  } else if (!canView) {
    content = (
      <Alert
        variant="warning"
        title="Impostazioni non disponibili"
        message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina."
      />
    );
  } else {
    content = (
      <TenantGeneralSettingsForm key={tenant.id} canUpdate={canUpdate} />
    );
  }

  return (
    <>
      <PageMeta
        title="Impostazioni Generali | Master Plan IT"
        description="Impostazioni operative del Tenant corrente"
      />
      <PageBreadcrumb
        pageTitle="Generali"
        subtitle="Impostazioni operative del Tenant corrente."
      />
      {content}
    </>
  );
}
