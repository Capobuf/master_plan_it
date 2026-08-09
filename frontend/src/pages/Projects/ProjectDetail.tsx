import { useCallback, useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";
import { ApiError } from "../../api/client";
import {
  deleteProject, getProject, getProjectHistory, getProjectRevision, restoreProjectRevision,
  type Project, type ProjectRevision, type ProjectRevisionComparison,
} from "../../api/projects";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import Label from "../../components/form/Label";
import TextArea from "../../components/form/input/TextArea";
import Alert from "../../components/ui/alert/Alert";
import Badge from "../../components/ui/badge/Badge";
import Button from "../../components/ui/button/Button";
import { Modal } from "../../components/ui/modal";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../../components/ui/table";
import { useApplicationContext } from "../../context/ApplicationContext";
import { routes } from "../../navigation/routes";
import { formatDateTime } from "../../presentation/formatters";
import { domainLabel } from "../../presentation/labels";

export default function ProjectDetail() {
  const { projectId: rawId } = useParams<{ projectId: string }>();
  const projectId = rawId && /^\d+$/.test(rawId) ? Number(rawId) : null;
  const { data, loading: contextLoading, hasAbility } = useApplicationContext();
  const navigate = useNavigate();
  const [project, setProject] = useState<Project | null>(null);
  const [history, setHistory] = useState<ProjectRevision[]>([]);
  const [comparison, setComparison] = useState<ProjectRevisionComparison | null>(null);
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
      const [next, revisions] = await Promise.all([
        getProject(projectId),
        hasAbility("project.view-revisions")
          ? getProjectHistory(projectId).then((response) => response.data)
          : Promise.resolve([]),
      ]);
      setProject(next);
      setHistory(revisions);
    } catch (requestError) {
      setProject(null); setError(ApiError.from(requestError));
    }
  }, [canView, hasAbility, projectId, tenantId]);
  useEffect(() => { if (!contextLoading) void load(); }, [contextLoading, load]);

  const compare = async (revisionId: number) => {
    if (projectId === null) return;
    setBusy(true); setError(null);
    try { setComparison(await getProjectRevision(projectId, revisionId)); } catch (requestError) { setError(ApiError.from(requestError)); } finally { setBusy(false); }
  };
  const restore = async () => {
    if (!project || !comparison) return;
    setBusy(true); setError(null);
    try { await restoreProjectRevision(project.id, comparison.revision.id, project.lock_version); setComparison(null); await load(); } catch (requestError) { setError(ApiError.from(requestError)); } finally { setBusy(false); }
  };
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
    <ComponentCard title="Spese collegate">{project.expenses.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Nessuna spesa corrente collegata.</p> : <ul className="divide-y divide-gray-100 dark:divide-gray-800">{project.expenses.map((expense) => <li key={expense.id} className="flex items-center justify-between gap-4 py-3"><Link to={routes.spesa(expense.id)} className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{expense.title}</Link><span className="text-sm text-gray-500 dark:text-gray-400">{domainLabel(expense.kind)} · {expense.planning_year_label}</span></li>)}</ul>}</ComponentCard>
    {hasAbility("project.view-revisions") ? <ComponentCard title="Storico Revisioni">{history.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Nessuna revisione disponibile.</p> : <><div className="divide-y divide-gray-100 md:hidden dark:divide-gray-800">{history.map((revision) => <article key={revision.id} className="py-4 first:pt-0 last:pb-0"><div className="flex items-start justify-between gap-3"><div><p className="font-medium text-gray-800 dark:text-white/90">{domainLabel(revision.operation)}</p><p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{revision.actor ?? "Sistema"}</p><p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{formatDateTime(revision.timestamp)}</p></div><Button size="sm" variant="outline" onClick={() => void compare(revision.id)} disabled={busy}>Confronta</Button></div></article>)}</div><div className="hidden max-w-full overflow-x-auto md:block"><Table><TableHeader className="border-b border-gray-100 dark:border-gray-800"><TableRow>{["Operazione", "Actor", "Data", "Azioni"].map((heading) => <TableCell key={heading} isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">{heading}</TableCell>)}</TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{history.map((revision) => <TableRow key={revision.id}><TableCell className="py-3 text-sm text-gray-700 dark:text-gray-300">{domainLabel(revision.operation)}</TableCell><TableCell className="py-3 text-sm text-gray-700 dark:text-gray-300">{revision.actor ?? "Sistema"}</TableCell><TableCell className="whitespace-nowrap py-3 text-sm text-gray-700 dark:text-gray-300">{formatDateTime(revision.timestamp)}</TableCell><TableCell className="py-3"><Button size="sm" variant="outline" onClick={() => void compare(revision.id)} disabled={busy}>Confronta</Button></TableCell></TableRow>)}</TableBody></Table></div></>}</ComponentCard> : null}
  </div>;

  return <><PageMeta title="Dettaglio Progetto | Master Plan IT" description="Dettaglio del progetto" /><PageBreadcrumb pageTitle="Dettaglio Progetto" />{body}
    <Modal isOpen={deleteOpen} onClose={() => setDeleteOpen(false)} className="max-w-lg p-6"><h2 className="pr-10 text-lg font-semibold text-gray-800 dark:text-white/90">Eliminare il progetto?</h2><p className="mt-3 text-sm text-gray-600 dark:text-gray-300">L'eliminazione è terminale ed è bloccata finché esistono spese correnti collegate.</p><div className="mt-5"><Label htmlFor="project-deletion-reason">Motivo dell'eliminazione</Label><TextArea id="project-deletion-reason" value={reason} onChange={setReason} placeholder="Inserisci il motivo" rows={4} /></div><div className="mt-6 flex justify-end gap-3"><Button variant="outline" onClick={() => setDeleteOpen(false)} disabled={busy}>Annulla</Button><Button onClick={() => void remove()} disabled={busy}>{busy ? "Eliminazione…" : "Elimina Progetto"}</Button></div></Modal>
    <Modal isOpen={comparison !== null} onClose={() => setComparison(null)} className="max-w-2xl p-6">{comparison ? <><h2 className="pr-10 text-lg font-semibold text-gray-800 dark:text-white/90">Confronto Revisione</h2><div className="mt-5 grid gap-4 md:grid-cols-2"><RevisionState title="Revisione" value={comparison.snapshot} /><RevisionState title="Stato corrente" value={comparison.current} /></div><div className="mt-6 flex justify-end gap-3"><Button variant="outline" onClick={() => setComparison(null)} disabled={busy}>Chiudi</Button>{hasAbility("project.restore-revision") ? <Button onClick={() => void restore()} disabled={busy}>{busy ? "Ripristino…" : "Ripristina revisione"}</Button> : null}</div></> : null}</Modal>
  </>;
}

function RevisionState({ title, value }: { title: string; value: { title: string; stage: string; cost_center_id: number; deferred_target_planning_year_id: number | null } }) {
  return <section className="rounded-xl border border-gray-200 p-4 dark:border-gray-800"><h3 className="font-semibold text-gray-800 dark:text-white/90">{title}</h3><dl className="mt-3 space-y-2 text-sm"><div><dt className="text-gray-500 dark:text-gray-400">Titolo</dt><dd className="text-gray-800 dark:text-white/90">{value.title}</dd></div><div><dt className="text-gray-500 dark:text-gray-400">Stage</dt><dd className="text-gray-800 dark:text-white/90">{domainLabel(value.stage)}</dd></div><div><dt className="text-gray-500 dark:text-gray-400">Centro di costo</dt><dd className="text-gray-800 dark:text-white/90">#{value.cost_center_id}</dd></div><div><dt className="text-gray-500 dark:text-gray-400">Anno di destinazione</dt><dd className="text-gray-800 dark:text-white/90">{value.deferred_target_planning_year_id ? `#${value.deferred_target_planning_year_id}` : "—"}</dd></div></dl></section>;
}
