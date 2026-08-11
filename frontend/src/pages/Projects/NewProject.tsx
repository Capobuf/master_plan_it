import { useState } from "react";
import { useNavigate } from "react-router";
import { ApiError } from "../../api/client";
import { createProject, type ProjectWrite } from "../../api/projects";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ProjectForm from "../../components/projects/ProjectForm";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import { routes } from "../../navigation/routes";

export default function NewProject() {
  const { data, loading, hasAbility } = useApplicationContext();
  const navigate = useNavigate();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const submit = async (input: ProjectWrite) => { setSubmitting(true); setError(null); try { const project = await createProject(input); navigate(routes.progetto(project.id), { replace: true }); } catch (requestError) { setError(ApiError.from(requestError)); } finally { setSubmitting(false); } };
  let body: React.ReactNode;
  if (loading) body = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant in corso." />;
  else if (!data?.tenant) body = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant prima di creare un progetto." />;
  else if (!hasAbility("project.create")) body = <Alert variant="warning" title="Creazione non disponibile" message="Non disponi dell'autorizzazione necessaria per questa operazione." />;
  else body = <ProjectForm submitting={submitting} error={error} onSubmit={(input) => submit(input as ProjectWrite)} onCancel={() => navigate(routes.progetti)} />;
  return <><PageMeta title="Nuovo Progetto | Master Plan IT" description="Crea un nuovo progetto" /><PageBreadcrumb pageTitle="Nuovo Progetto" subtitle="Inserisci titolo, centro di costo e stage." />{body}</>;
}
