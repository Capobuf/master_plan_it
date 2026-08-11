import { useCallback, useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";
import { ApiError } from "../../api/client";
import {
  deleteProject, getProject, getProjectHistory, getProjectRevision, restoreProjectRevision,
  type Project, type ProjectRevision,
} from "../../api/projects";
import ComponentCard from "../../components/common/ComponentCard";
import AttachmentPanel from "../../components/attachments/AttachmentPanel";
import ObjectTabs from "../../components/common/ObjectTabs";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import Label from "../../components/form/Label";
import TextArea from "../../components/form/input/TextArea";
import RevisionHistoryPanel from "../../components/revisions/RevisionHistoryPanel";
import Alert from "../../components/ui/alert/Alert";
import Badge from "../../components/ui/badge/Badge";
import Button from "../../components/ui/button/Button";
import { Modal } from "../../components/ui/modal";
import { useApplicationContext } from "../../context/ApplicationContext";
import { routes } from "../../navigation/routes";
import { domainLabel } from "../../presentation/labels";

export default function ProjectDetail() {
  const { projectId: rawId } = useParams<{ projectId: string }>();
  const projectId = rawId && /^\d+$/.test(rawId) ? Number(rawId) : null;
  const { data, loading: contextLoading, hasAbility } = useApplicationContext();
  const navigate = useNavigate();
  const [project, setProject] = useState<Project | null>(null);
  const [history, setHistory] = useState<ProjectRevision[]>([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<"details" | "attachments" | "history">("details");
  const [error, setError] = useState<ApiError | null>(null);
  const [busy, setBusy] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [reason, setReason] = useState("");
  const tenantId = data?.tenant?.id ?? null;
  const canView = hasAbility("project.view");

  const load = useCallback(async () => {
    if (tenantId === null || projectId === null || !canView) return;
    setError(null);
    try {
      setProject(await getProject(projectId));
    } catch (requestError) {
      setProject(null); setError(ApiError.from(requestError));
    }
  }, [canView, projectId, tenantId]);
  useEffect(() => { if (!contextLoading) void load(); }, [contextLoading, load]);
  useEffect(() => { setActiveTab("details"); setHistory([]); setHistoryError(null); }, [projectId, tenantId]);

  const loadHistory = async () => { if (projectId === null || !hasAbility("project.view-revisions")) return; setHistoryLoading(true); setHistoryError(null); try { setHistory((await getProjectHistory(projectId)).data); } catch (requestError) { setHistoryError(ApiError.from(requestError).message); } finally { setHistoryLoading(false); } };
  const changeTab = (tab: "details" | "attachments" | "history") => { setActiveTab(tab); if (tab === "history" && history.length === 0 && !historyLoading) void loadHistory(); };
  const remove = async () => {
    if (!project) return;
    setBusy(true); setError(null);
    try { await deleteProject(project.id, { lock_version: project.lock_version, deletion_reason: reason.trim() || undefined }); navigate(routes.progetti, { replace: true }); } catch (requestError) { setError(ApiError.from(requestError)); setDeleteOpen(false); } finally { setBusy(false); }
  };

  let body: React.ReactNode;
  if (contextLoading) body = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant in corso." />;
  else if (tenantId === null) body = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant prima di aprire il progetto." />;
  else if (projectId === null) body = <Alert variant="error" title="Progetto non valido" message="L'identificativo del progetto non è valido." />;
  else if (!canView) body = <Alert variant="warning" title="Progetto non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare il progetto." />;
  else if (!project && error) body = <Alert variant="error" title="Caricamento non riuscito" message={`${error.message}${error.correlationId ? ` Riferimento tecnico: ${error.correlationId}` : ""}`} />;
  else if (!project) body = <Alert variant="info" title="Caricamento del progetto" message="Recupero dei dati in corso." />;
  else body = <div className="space-y-6">
    {error ? <Alert variant="error" title="Operazione non riuscita" message={`${error.message}${error.correlationId ? ` Riferimento tecnico: ${error.correlationId}` : ""}`} /> : null}
    <ComponentCard title={project.title}>
      <div className="flex flex-wrap items-start justify-between gap-4"><Badge color={project.stage === "approved" ? "success" : project.stage === "rejected" ? "error" : "info"}>{domainLabel(project.stage)}</Badge><div className="flex gap-2">{hasAbility("project.update") ? <Button variant="outline" onClick={() => navigate(routes.modificaProgetto(project.id))}>Modifica</Button> : null}{hasAbility("project.delete") ? <Button variant="outline" onClick={() => setDeleteOpen(true)}>Elimina</Button> : null}</div></div>
      <dl className="grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 dark:border-gray-800"><div><dt className="text-sm text-gray-500 dark:text-gray-400">Centro di costo</dt><dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{project.cost_center?.name ?? "—"}</dd></div>{project.stage === "deferred" ? <div><dt className="text-sm text-gray-500 dark:text-gray-400">Anno di destinazione</dt><dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{project.deferred_target_planning_year?.year_label ?? "—"}</dd></div> : null}</dl>
    </ComponentCard>
    <ObjectTabs tabs={[{ key: "details" as const, label: "Dettagli" }, ...(hasAbility("attachment.view") ? [{ key: "attachments" as const, label: "Allegati" }] : []), ...(hasAbility("project.view-revisions") ? [{ key: "history" as const, label: "Storico" }] : [])]} active={activeTab} onChange={changeTab} />
    {activeTab === "details" ? <ComponentCard title="Spese collegate">{project.expenses.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Nessuna spesa corrente collegata.</p> : <ul className="divide-y divide-gray-100 dark:divide-gray-800">{project.expenses.map((expense) => <li key={expense.id} className="flex items-center justify-between gap-4 py-3"><Link to={routes.spesa(expense.id)} className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{expense.title}</Link><span className="text-sm text-gray-500 dark:text-gray-400">{domainLabel(expense.kind)} · {expense.planning_year_label}</span></li>)}</ul>}</ComponentCard> : activeTab === "attachments" ? <AttachmentPanel parent={{ kind: "project", projectId: project.id }} /> : <RevisionHistoryPanel revisions={history} loading={historyLoading} loadError={historyError} onReload={loadHistory} onCompare={(revisionId) => getProjectRevision(project.id, revisionId)} onRestore={async (revisionId) => { setProject(await restoreProjectRevision(project.id, revisionId, project.lock_version)); await loadHistory(); }} />}
  </div>;

  return <><PageMeta title="Dettaglio Progetto | Master Plan IT" description="Dettaglio del progetto" /><PageBreadcrumb pageTitle="Dettaglio Progetto" />{body}
    <Modal isOpen={deleteOpen} onClose={() => setDeleteOpen(false)} className="max-w-lg p-6"><h2 className="pr-10 text-lg font-semibold text-gray-800 dark:text-white/90">Eliminare il progetto?</h2><p className="mt-3 text-sm text-gray-600 dark:text-gray-300">L'eliminazione è terminale ed è bloccata finché esistono spese correnti collegate.</p><div className="mt-5"><Label htmlFor="project-deletion-reason">Motivo dell'eliminazione</Label><TextArea id="project-deletion-reason" value={reason} onChange={setReason} placeholder="Inserisci il motivo" rows={4} /></div><div className="mt-6 flex justify-end gap-3"><Button variant="outline" onClick={() => setDeleteOpen(false)} disabled={busy}>Annulla</Button><Button onClick={() => void remove()} disabled={busy}>{busy ? "Eliminazione…" : "Elimina Progetto"}</Button></div></Modal>
  </>;
}
