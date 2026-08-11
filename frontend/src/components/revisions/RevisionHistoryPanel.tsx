import { useState } from "react";
import type { OperationalRevision, RevisionComparison } from "../../api/revisions";
import { ApiError } from "../../api/client";
import { formatDateTime } from "../../presentation/formatters";
import { domainLabel } from "../../presentation/labels";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import RevisionCompareModal from "./RevisionCompareModal";

interface RevisionHistoryPanelProps {
  revisions: OperationalRevision[];
  loading: boolean;
  loadError: string | null;
  onReload: () => Promise<void>;
  onCompare: (revisionId: number) => Promise<RevisionComparison>;
  onRestore: (revisionId: number) => Promise<void>;
}

export default function RevisionHistoryPanel({ revisions, loading, loadError, onReload, onCompare, onRestore }: RevisionHistoryPanelProps) {
  const [comparison, setComparison] = useState<RevisionComparison | null>(null);
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const compare = async (revisionId: number) => {
    setBusy(true); setActionError(null);
    try { setComparison(await onCompare(revisionId)); }
    catch (error: unknown) { setActionError(ApiError.from(error).message); }
    finally { setBusy(false); }
  };
  const restore = async () => {
    if (!comparison) return;
    setBusy(true); setActionError(null);
    try { await onRestore(comparison.revision.id); setComparison(null); }
    catch (error: unknown) { setActionError(ApiError.from(error).message); }
    finally { setBusy(false); }
  };

  return <>
    <section className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] sm:p-5" aria-label="Storico revisioni">
      <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-semibold text-gray-800 dark:text-white/90">Storico operativo</h2><p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Sono disponibili le 10 revisioni logiche più recenti.</p></div><Button type="button" size="sm" variant="outline" onClick={() => { void onReload(); }} disabled={loading}>{loading ? "Caricamento…" : "Aggiorna"}</Button></div>
      {loadError ? <div className="mt-4"><Alert variant="error" title="Storico non disponibile" message={loadError} /></div> : null}
      {!loading && revisions.length === 0 ? <p className="mt-5 rounded-xl bg-gray-50 p-4 text-sm text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">Nessuna revisione disponibile.</p> : null}
      <ol className="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
        {revisions.map((revision) => <li key={revision.id} className="py-4 first:pt-0 last:pb-0"><div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><p className="font-medium text-gray-800 dark:text-white/90">{domainLabel(revision.operation)}</p>{revision.actor.kind === "system" ? <Badge size="sm" color="info">Sistema</Badge> : null}</div><p className="mt-1 text-sm text-gray-600 dark:text-gray-300">{revision.summary}</p><p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{formatDateTime(revision.timestamp)} · {revision.actor.label} · {revision.changed_count} {revision.changed_count === 1 ? "elemento modificato" : "elementi modificati"}</p>{revision.changed_fields.length > 0 ? <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{revision.changed_fields.join(" · ")}</p> : null}</div><div className="flex shrink-0 flex-wrap gap-2">{revision.can_compare ? <Button type="button" size="sm" variant="outline" onClick={() => { void compare(revision.id); }} disabled={busy}>Confronta con attuale</Button> : null}</div></div></li>)}
      </ol>
    </section>
    <RevisionCompareModal comparison={comparison} busy={busy} error={actionError} onClose={() => { setComparison(null); setActionError(null); }} onRestore={comparison?.revision.can_restore ? restore : null} />
  </>;
}
