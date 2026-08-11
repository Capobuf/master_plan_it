import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router";
import { ApiError } from "../../api/client";
import { getProject, updateProject, type Project, type ProjectUpdate } from "../../api/projects";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ProjectForm from "../../components/projects/ProjectForm";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import { routes } from "../../navigation/routes";

export default function EditProject() {
  const { projectId: rawId } = useParams<{ projectId: string }>();
  const projectId = rawId && /^\d+$/.test(rawId) ? Number(rawId) : null;
  const { data, loading: contextLoading, hasAbility } = useApplicationContext();
  const navigate = useNavigate();
  const [project, setProject] = useState<Project | null>(null);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const tenantId = data?.tenant?.id ?? null;
  useEffect(() => { if (contextLoading || tenantId === null || projectId === null || !hasAbility("project.view")) return; let active = true; setLoading(true); void getProject(projectId).then((next) => { if (active) setProject(next); }).catch((requestError) => { if (active) setError(ApiError.from(requestError)); }).finally(() => { if (active) setLoading(false); }); return () => { active = false; }; }, [contextLoading, hasAbility, projectId, tenantId]);
  const submit = async (input: ProjectUpdate) => { if (projectId === null) return; setSubmitting(true); setError(null); try { const updated = await updateProject(projectId, input); navigate(routes.progetto(updated.id), { replace: true }); } catch (requestError) { setError(ApiError.from(requestError)); } finally { setSubmitting(false); } };
  let body: React.ReactNode;
  if (contextLoading || loading) body = <Alert variant="info" title="Caricamento del progetto" message="Recupero dei dati in corso." />;
  else if (tenantId === null) body = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant prima di modificare il progetto." />;
  else if (projectId === null) body = <Alert variant="error" title="Progetto non valido" message="L'identificativo del progetto non è valido." />;
  else if (!hasAbility("project.update")) body = <Alert variant="warning" title="Modifica non disponibile" message="Non disponi dell'autorizzazione necessaria per questa operazione." />;
  else if (!project) body = <Alert variant="error" title="Caricamento non riuscito" message={error?.message ?? "Progetto non trovato."} />;
  else body = <ProjectForm project={project} submitting={submitting} error={error} onSubmit={(input) => submit(input as ProjectUpdate)} onCancel={() => navigate(routes.progetto(project.id))} />;
  return <><PageMeta title="Modifica Progetto | Master Plan IT" description="Modifica un progetto" /><PageBreadcrumb pageTitle="Modifica Progetto" subtitle="Aggiorna i dati mantenendo il controllo di versione corrente." />{body}</>;
}
