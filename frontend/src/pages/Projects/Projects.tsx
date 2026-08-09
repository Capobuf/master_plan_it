import { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import { ApiError } from "../../api/client";
import { listProjects, type Project } from "../../api/projects";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ProjectTable from "../../components/projects/ProjectTable";
import Alert from "../../components/ui/alert/Alert";
import Button from "../../components/ui/button/Button";
import { useApplicationContext } from "../../context/ApplicationContext";
import { routes } from "../../navigation/routes";

export default function Projects() {
  const { data, loading: contextLoading, hasAbility } = useApplicationContext();
  const navigate = useNavigate();
  const [projects, setProjects] = useState<Project[] | null>(null);
  const [error, setError] = useState<ApiError | null>(null);
  const tenantId = data?.tenant?.id ?? null;
  const canView = hasAbility("project.view");

  useEffect(() => {
    if (contextLoading || tenantId === null || !canView) return;
    let active = true;
    setProjects(null); setError(null);
    void listProjects({ per_page: 100 }).then((response) => { if (active) setProjects(response.data); }).catch((requestError: unknown) => { if (active) setError(ApiError.from(requestError)); });
    return () => { active = false; };
  }, [canView, contextLoading, tenantId]);

  let content: React.ReactNode;
  if (contextLoading) content = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant in corso." />;
  else if (tenantId === null) content = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant prima di aprire i progetti." />;
  else if (!canView) content = <Alert variant="warning" title="Progetti non disponibili" message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina." />;
  else if (error) content = <Alert variant="error" title="Caricamento non riuscito" message={`${error.message}${error.correlationId ? ` Riferimento tecnico: ${error.correlationId}` : ""}`} />;
  else if (projects === null) content = <Alert variant="info" title="Caricamento dei progetti" message="Recupero dei dati in corso." />;
  else if (projects.length === 0) content = <Alert variant="info" title="Nessun progetto" message="Non ci sono progetti correnti per il Tenant selezionato." />;
  else content = <ProjectTable projects={projects} canEdit={hasAbility("project.update")} />;

  return <><PageMeta title="Progetti | Master Plan IT" description="Progetti correnti del Tenant" /><PageBreadcrumb pageTitle="Progetti" subtitle="Organizza le spese per progetto e stage." actions={hasAbility("project.create") ? <Button size="sm" onClick={() => navigate(routes.nuovoProgetto)}>Nuovo Progetto</Button> : null} /><ComponentCard title="Elenco Progetti">{content}</ComponentCard></>;
}
