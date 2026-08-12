import { useCallback, useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router";
import { ApiError } from "../../api/client";
import {
  getExpense,
  getExpenseHistory,
  getExpenseRevision,
  restoreExpenseRevision,
  type ExpenseDetail as ExpenseDetailData,
  type ExpenseRevision,
} from "../../api/expenses";
import ComponentCard from "../../components/common/ComponentCard";
import IconButton from "../../components/common/IconButton";
import ObjectTabs from "../../components/common/ObjectTabs";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ExpenseActionModal from "../../components/expenses/ExpenseActionModal";
import ExpenseAttachmentsPanel from "../../components/attachments/ExpenseAttachmentsPanel";
import ExpenseKindBadge from "../../components/expenses/ExpenseKindBadge";
import ExpenseRowsTable from "../../components/expenses/ExpenseRowsTable";
import ExpenseTotals from "../../components/expenses/ExpenseTotals";
import RevisionHistoryPanel from "../../components/revisions/RevisionHistoryPanel";
import Alert from "../../components/ui/alert/Alert";
import Button from "../../components/ui/button/Button";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";
import { PencilIcon, TrashBinIcon } from "../../icons";
import { routes } from "../../navigation/routes";

interface DetailState { tenantId: number; yearId: number; detail: ExpenseDetailData | null; error: ApiError | null; }

export default function ExpenseDetail() {
  const { data: applicationContext, loading: contextLoading, hasAbility } = useApplicationContext();
  const { selectedPlanningYearId, loading: yearLoading } = usePlanningYear();
  const { expenseId: expenseIdParam } = useParams<{ expenseId: string }>();
  const navigate = useNavigate();
  const [detailState, setDetailState] = useState<DetailState | null>(null);
  const [loading, setLoading] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [activeTab, setActiveTab] = useState<"details" | "attachments" | "history">("details");
  const [history, setHistory] = useState<ExpenseRevision[]>([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState<string | null>(null);
  const tenantId = applicationContext?.tenant?.id ?? null;
  const expenseId = expenseIdParam && /^\d+$/.test(expenseIdParam) ? Number(expenseIdParam) : null;
  const canView = hasAbility("expense.view");
  const detail = detailState?.tenantId === tenantId && detailState.yearId === selectedPlanningYearId ? detailState.detail : null;

  const loadDetail = useCallback(async () => {
    if (tenantId === null || expenseId === null || selectedPlanningYearId === null || !canView) return;
    setLoading(true);
    try { setDetailState({ tenantId, yearId: selectedPlanningYearId, detail: await getExpense(expenseId, selectedPlanningYearId), error: null }); }
    catch (error: unknown) { setDetailState({ tenantId, yearId: selectedPlanningYearId, detail: null, error: ApiError.from(error) }); }
    finally { setLoading(false); }
  }, [canView, expenseId, selectedPlanningYearId, tenantId]);

  useEffect(() => { if (!contextLoading && !yearLoading) void loadDetail(); }, [contextLoading, loadDetail, yearLoading]);
  useEffect(() => { setActiveTab("details"); setHistory([]); setHistoryError(null); }, [expenseId, selectedPlanningYearId, tenantId]);

  const loadHistory = useCallback(async () => {
    if (expenseId === null || selectedPlanningYearId === null || !hasAbility("expense.view-revisions")) return;
    setHistoryLoading(true); setHistoryError(null);
    try { setHistory((await getExpenseHistory(expenseId, selectedPlanningYearId)).data); }
    catch (error: unknown) { setHistoryError(ApiError.from(error).message); }
    finally { setHistoryLoading(false); }
  }, [expenseId, hasAbility, selectedPlanningYearId]);

  const changeTab = (tab: "details" | "attachments" | "history") => {
    setActiveTab(tab);
    if (tab === "history" && history.length === 0 && !historyLoading) void loadHistory();
  };

  let body;
  if (contextLoading || yearLoading) body = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant e del Planning Year in corso." />;
  else if (tenantId === null) body = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant dall'intestazione." />;
  else if (!canView) body = <Alert variant="warning" title="Dettaglio non disponibile" message="Non disponi dell'autorizzazione necessaria." />;
  else if (selectedPlanningYearId === null) body = <Alert variant="warning" title="Anno richiesto" message="Seleziona il Planning Year della Spesa." />;
  else if (expenseId === null) body = <Alert variant="error" title="Identificativo non valido" message="L'identificativo della Spesa non è valido." />;
  else if (detailState?.error && detailState.yearId === selectedPlanningYearId) body = <div className="space-y-3"><Alert variant="error" title="Spesa non disponibile nell'anno selezionato" message={detailState.error.message} /><Button variant="outline" onClick={() => void loadDetail()} disabled={loading}>Riprova</Button></div>;
  else if (!detail) body = <Alert variant="info" title="Caricamento Spesa" message="Richiesta del dettaglio corrente." />;
  else {
    const canEdit = hasAbility("expense.update");
    const canDelete = hasAbility("expense.delete");
    body = <div className="space-y-4">
      {detail.warnings.includes("BUDGET_CLOSED") ? <Alert variant="warning" title="Budget chiuso" message="La modifica verrà registrata come variazione successiva alla chiusura." /> : null}
      <section className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div><h1 className="text-xl font-semibold text-gray-900 dark:text-white">{detail.title}</h1><div className="mt-2 flex flex-wrap items-center gap-2"><ExpenseKindBadge kind={detail.kind} /><span className="rounded-full bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{detail.economic_year_label}</span></div></div>
          <div className="flex flex-wrap gap-2">
            {canEdit ? <IconButton icon={PencilIcon} label="Modifica Spesa" onClick={() => navigate(routes.modificaSpesa(detail.id))} disabled={loading} /> : null}
            {canDelete ? <IconButton icon={TrashBinIcon} label="Elimina Spesa" onClick={() => setDeleteOpen(true)} disabled={loading} destructive /> : null}
          </div>
        </div>
      </section>
      <ObjectTabs tabs={[{ key: "details" as const, label: "Dettagli" }, ...(hasAbility("attachment.view") ? [{ key: "attachments" as const, label: "Allegati" }] : []), ...(hasAbility("expense.view-revisions") ? [{ key: "history" as const, label: "Storico" }] : [])]} active={activeTab} onChange={changeTab} />
      {activeTab === "details" ? <div className="space-y-4">
      <div className="grid gap-4 xl:grid-cols-5">
        <ComponentCard title="Classificazione" compact className="xl:col-span-2"><dl className="grid gap-3 sm:grid-cols-2">
          {[['Natura', detail.kind === 'ordinary' ? 'Ordinaria' : 'Plafond'], ['Centro di Costo', detail.cost_center_name ?? '—'], ['Progetto', detail.project_title ?? '—'], ['Contratto', detail.contract_title ?? '—'], ['Anno Economico', String(detail.economic_year_label)]].map(([label, value]) => <div key={label}><dt className="text-xs text-gray-500">{label}</dt><dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{value}</dd></div>)}
        </dl></ComponentCard>
        <ComponentCard title="Sintesi economica" compact className="xl:col-span-3"><p className="text-sm text-gray-600 dark:text-gray-300">La pianificazione corrente e gli Effettivi sono presentati nei totali autorevoli qui sotto.</p></ComponentCard>
      </div>
      <ComponentCard title="Righe della Spesa" compact><ExpenseRowsTable rows={detail.rows} /></ComponentCard>
      <ExpenseTotals totals={detail.totals} title="Totali della Spesa" />
      {detail.notes ? <ComponentCard title="Note" compact><p className="whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">{detail.notes}</p></ComponentCard> : null}
      </div> : activeTab === "attachments" ? <ExpenseAttachmentsPanel expense={detail} /> : <RevisionHistoryPanel revisions={history} loading={historyLoading} loadError={historyError} onReload={loadHistory} onCompare={(revisionId) => getExpenseRevision(detail.id, revisionId, detail.planning_year_id)} onRestore={async (revisionId) => { const restored = await restoreExpenseRevision(detail.id, revisionId, detail.lock_version); setDetailState({ tenantId: tenantId as number, yearId: selectedPlanningYearId as number, detail: restored, error: null }); await loadHistory(); }} />}
      {canDelete ? <ExpenseActionModal expenseId={detail.id} lockVersion={detail.lock_version} generated={detail.rows.some((row) => row.generated)} contractId={detail.contract_id} isOpen={deleteOpen} onClose={() => setDeleteOpen(false)} onDeleted={() => navigate(routes.spese)} /> : null}
    </div>;
  }

  return <><PageMeta title="Dettaglio Spesa | Master Plan IT" description="Dettaglio della Spesa corrente del Tenant" /><PageBreadcrumb pageTitle="Dettaglio Spesa" />{body}</>;
}
