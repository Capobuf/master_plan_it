import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router";
import { ApiError } from "../../api/client";
import {
  confirmActual,
  getExpense,
  type ExpenseDetail as ExpenseDetailData,
  type ExpenseRow,
} from "../../api/expenses";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ExpenseActionModal from "../../components/expenses/ExpenseActionModal";
import ExpenseKindBadge from "../../components/expenses/ExpenseKindBadge";
import ExpenseRowsTable from "../../components/expenses/ExpenseRowsTable";
import ExpenseTotals from "../../components/expenses/ExpenseTotals";
import Alert from "../../components/ui/alert/Alert";
import Button from "../../components/ui/button/Button";
import { useApplicationContext } from "../../context/ApplicationContext";
import { routes } from "../../navigation/routes";

interface DetailState {
  tenantId: number;
  detail: ExpenseDetailData | null;
  error: ApiError | null;
}

export default function ExpenseDetail() {
  const { data: applicationContext, loading: contextLoading, hasAbility } =
    useApplicationContext();
  const { expenseId: expenseIdParam } = useParams<{ expenseId: string }>();
  const navigate = useNavigate();
  const [detailState, setDetailState] = useState<DetailState | null>(null);
  const [loading, setLoading] = useState(false);
  const [confirmingRowId, setConfirmingRowId] = useState<number | null>(null);
  const [actionError, setActionError] = useState<ApiError | null>(null);
  const [deleteOpen, setDeleteOpen] = useState(false);

  const tenantId = applicationContext?.tenant?.id ?? null;
  const canView = hasAbility("expense.view");
  const expenseId = expenseIdParam && /^\d+$/.test(expenseIdParam) ? Number(expenseIdParam) : null;
  const detail = detailState?.tenantId === tenantId ? detailState.detail : null;

  async function loadDetail() {
    if (tenantId === null || expenseId === null || !canView) return;
    setLoading(true);
    setActionError(null);
    try {
      const next = await getExpense(expenseId);
      setDetailState({ tenantId, detail: next, error: null });
    } catch (error: unknown) {
      setDetailState({ tenantId, detail: null, error: ApiError.from(error) });
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (contextLoading || tenantId === null || expenseId === null || !canView) return;
    void loadDetail();
    // The route ID and current tenant are the request identity. The action is intentionally stable.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canView, contextLoading, expenseId, tenantId]);

  async function handleConfirm(row: ExpenseRow) {
    if (detail === null) return;
    setConfirmingRowId(row.id);
    setActionError(null);
    try {
      await confirmActual(detail.id, row.id, { lock_version: row.lock_version });
      await loadDetail();
    } catch (error: unknown) {
      setActionError(ApiError.from(error));
    } finally {
      setConfirmingRowId(null);
    }
  }

  let body;
  if (contextLoading) {
    body = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant in corso." />;
  } else if (tenantId === null) {
    body = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant dall'intestazione prima di aprire la spesa." />;
  } else if (!canView) {
    body = <Alert variant="warning" title="Dettaglio non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare questa spesa." />;
  } else if (expenseId === null) {
    body = <Alert variant="error" title="Identificativo non valido" message="L'identificativo della spesa non è valido." />;
  } else if (detailState?.error) {
    body = (
      <div className="space-y-3">
        <Alert
          variant="error"
          title="Richiesta dettaglio non riuscita"
          message={`${detailState.error.message}${detailState.error.correlationId ? ` Riferimento tecnico: ${detailState.error.correlationId}` : ""}`}
        />
        <Button variant="outline" onClick={() => void loadDetail()} disabled={loading}>
          Riprova
        </Button>
      </div>
    );
  } else if (detail === null) {
    body = <Alert variant="info" title="Caricamento spesa" message="Richiesta del dettaglio corrente." />;
  } else {
    const canEdit = hasAbility("expense.update");
    const canConfirm = hasAbility("expense.confirm-actual");
    const canDelete = hasAbility("expense.delete");
    const hasGeneratedRows = detail.rows.some((row) => row.generated);

    body = (
      <div className="space-y-6">
        {actionError && (
          <Alert
            variant="error"
            title="Operazione non riuscita"
            message={`${actionError.message}${actionError.correlationId ? ` Riferimento tecnico: ${actionError.correlationId}` : ""}`}
          />
        )}
        <ComponentCard title={detail.title}>
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex items-center gap-3">
              <ExpenseKindBadge kind={detail.kind} />
              {detail.rows.some((row) => row.is_system_managed) && (
                <span className="text-sm text-gray-500 dark:text-gray-400">Contiene dati gestiti dal sistema</span>
              )}
            </div>
            <div className="flex flex-wrap gap-2">
              {canEdit && (
                <Button variant="outline" onClick={() => navigate(routes.modificaSpesa(detail.id))} disabled={loading}>
                  Modifica
                </Button>
              )}
              {canDelete && (
                <Button variant="outline" onClick={() => setDeleteOpen(true)} disabled={loading}>
                  Elimina
                </Button>
              )}
            </div>
          </div>
          <dl className="grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-4 dark:border-gray-800">
            <div>
              <dt className="text-sm text-gray-500 dark:text-gray-400">Centro di costo</dt>
              <dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{detail.cost_center_name ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-sm text-gray-500 dark:text-gray-400">Anno</dt>
              <dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{detail.planning_year_label}</dd>
            </div>
            <div>
              <dt className="text-sm text-gray-500 dark:text-gray-400">Contratto</dt>
              <dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{detail.contract_id ? "Contratto associato" : "Nessun contratto"}</dd>
            </div>
            <div>
              <dt className="text-sm text-gray-500 dark:text-gray-400">Progetto</dt>
              <dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{detail.project_id && hasAbility("project.view") ? <button type="button" className="text-brand-500 hover:text-brand-600" onClick={() => navigate(routes.progetto(detail.project_id as number))}>{detail.project_title ?? "Progetto associato"}</button> : detail.project_title ?? "Nessun progetto"}</dd>
            </div>
            <div>
              <dt className="text-sm text-gray-500 dark:text-gray-400">Righe correnti</dt>
              <dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{detail.rows.length}</dd>
            </div>
          </dl>
          {detail.notes && (
            <div className="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-300">
              {detail.notes}
            </div>
          )}
        </ComponentCard>
        <ExpenseTotals totals={detail.totals} title="Totali della Spesa" />
        <ComponentCard title="Righe della Spesa">
          <ExpenseRowsTable
            rows={detail.rows}
            canConfirm={canConfirm}
            confirmingRowId={confirmingRowId}
            onConfirm={(row) => void handleConfirm(row)}
          />
        </ComponentCard>
        {canDelete && (
          <ExpenseActionModal
            expenseId={detail.id}
            lockVersion={detail.lock_version}
            generated={hasGeneratedRows}
            contractId={detail.contract_id}
            isOpen={deleteOpen}
            onClose={() => setDeleteOpen(false)}
            onDeleted={() => navigate(routes.spese)}
          />
        )}
      </div>
    );
  }

  return (
    <>
      <PageMeta title="Dettaglio Spesa | Master Plan IT" description="Dettaglio della spesa corrente del Tenant" />
      <PageBreadcrumb pageTitle="Dettaglio Spesa" />
      {body}
    </>
  );
}
