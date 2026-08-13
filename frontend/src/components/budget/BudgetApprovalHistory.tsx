import { useEffect, useRef, useState } from "react";
import { ApiError } from "../../api/client";
import { getBudgetApprovalDetail, getBudgetApprovalHistory, type BudgetApprovalDetail as BudgetApprovalDetailModel, type BudgetApprovalSummary } from "../../api/budget";
import { formatDate, formatDateTime, formatMoney } from "../../presentation/formatters";
import ComponentCard from "../common/ComponentCard";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";
import BudgetApprovalDetailDialog from "./BudgetApprovalDetail";

const PAGE_SIZE = 25;

function statusLabel(status: BudgetApprovalSummary["status"]): string {
  return status === "active" ? "Attiva" : "Annullata";
}

function ApprovalRow({ approval, onOpen }: { approval: BudgetApprovalSummary; onOpen: (id: number) => void }) {
  return <TableRow>
    <TableCell><Badge color={approval.status === "active" ? "success" : "warning"}>{statusLabel(approval.status)}</Badge></TableCell>
    <TableCell><span className="font-medium">{formatDate(approval.effective_date)}</span><span className="block text-xs text-gray-500 dark:text-gray-400">Registrata {formatDateTime(approval.recorded_at)}</span></TableCell>
    <TableCell>{approval.approved_by.name}{approval.note ? <span className="block text-xs text-gray-500 dark:text-gray-400">{approval.note}</span> : null}</TableCell>
    <TableCell><span className="font-medium">{formatMoney(approval.total.official, approval.currency)}</span><span className="block text-xs text-gray-500 dark:text-gray-400">Base {approval.basis === "net" ? "netta" : "lorda"}</span></TableCell>
    <TableCell>{approval.status === "annulled" ? <span className="text-sm">{approval.annulment_note ?? "Annullata"}<span className="block text-xs text-gray-500 dark:text-gray-400">{approval.annulled_at ? formatDateTime(approval.annulled_at) : ""}{approval.annulled_by ? ` · ${approval.annulled_by.name}` : ""}</span></span> : "—"}</TableCell>
    <TableCell><Button type="button" variant="outline" size="sm" onClick={() => onOpen(approval.id)}>Apri fotografia</Button></TableCell>
  </TableRow>;
}

function errorReference(error: unknown): string | null {
  return error instanceof ApiError && error.correlationId ? error.correlationId : null;
}

export default function BudgetApprovalHistory({ planningYearId, requestScope }: { planningYearId: number; requestScope: string }) {
  return <BudgetApprovalHistoryContent key={`${planningYearId}:${requestScope}`} planningYearId={planningYearId} />;
}

function BudgetApprovalHistoryContent({ planningYearId }: { planningYearId: number }) {
  const [page, setPage] = useState(1);
  const [history, setHistory] = useState<Awaited<ReturnType<typeof getBudgetApprovalHistory>> | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<{ reference: string | null; retryPage: number } | null>(null);
  const [detail, setDetail] = useState<BudgetApprovalDetailModel | null>(null);
  const [detailError, setDetailError] = useState<{ reference: string | null } | null>(null);
  const [detailLoadingId, setDetailLoadingId] = useState<number | null>(null);
  const [retryNonce, setRetryNonce] = useState(0);
  const detailRequestRef = useRef(0);
  const failedPageRef = useRef<number | null>(null);
  const displayedPageRef = useRef(1);

  useEffect(() => {
    let current = true;
    setLoading(true);
    setDetail(null);
    detailRequestRef.current += 1;
    void getBudgetApprovalHistory(planningYearId, { page, per_page: PAGE_SIZE })
      .then((next) => {
        if (!current) return;
        setHistory(next);
        displayedPageRef.current = next.meta.current_page;
        if (failedPageRef.current === page) {
          failedPageRef.current = null;
          setError(null);
        }
      })
      .catch((cause: unknown) => {
        if (!current) return;
        failedPageRef.current = page;
        setError({ reference: errorReference(cause), retryPage: page });
        setPage(displayedPageRef.current);
      })
      .finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [page, planningYearId, retryNonce]);

  const openDetail = async (approvalId: number) => {
    const requestId = detailRequestRef.current + 1;
    detailRequestRef.current = requestId;
    setDetailError(null);
    setDetailLoadingId(approvalId);
    try {
      const response = await getBudgetApprovalDetail(planningYearId, approvalId);
      if (detailRequestRef.current === requestId) setDetail(response);
    } catch (cause: unknown) {
      if (detailRequestRef.current === requestId) setDetailError({ reference: errorReference(cause) });
    } finally {
      if (detailRequestRef.current === requestId) setDetailLoadingId(null);
    }
  };
  const canPrevious = Boolean(history && history.meta.current_page > 1) && !loading;
  const canNext = Boolean(history && history.meta.current_page < history.meta.last_page) && !loading;

  return <ComponentCard title="Cronologia delle approvazioni" desc="Decisioni e fotografie registrate dal server, disponibili in sola lettura.">
    {error ? <div className="space-y-3"><Alert variant="error" title="Cronologia non disponibile" message={`Non è stato possibile aggiornare la cronologia delle approvazioni.${error.reference ? ` Riferimento: ${error.reference}.` : ""}`} /><Button type="button" variant="outline" size="sm" onClick={() => { if (page === error.retryPage) setRetryNonce((value) => value + 1); else setPage(error.retryPage); }}>Riprova</Button></div> : null}
    {detailError ? <Alert variant="error" title="Fotografia non disponibile" message={`Non è stato possibile aprire la fotografia richiesta.${detailError.reference ? ` Riferimento: ${detailError.reference}.` : ""}`} /> : null}
    {loading && history === null ? <p className="text-sm text-gray-500 dark:text-gray-400" role="status">Caricamento cronologia…</p> : null}
    {!loading && !error && history?.data.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Non sono presenti approvazioni registrate.</p> : null}
    {history && history.data.length > 0 ? <><div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Stato", "Efficacia", "Registrata da", "Previsto", "Annullamento", ""].map((heading) => <TableCell key={heading} isHeader>{heading}</TableCell>)}</TableRow></TableHeader><TableBody>{history.data.map((approval) => <ApprovalRow key={approval.id} approval={approval} onOpen={(id) => { void openDetail(id); }} />)}</TableBody></Table></div><div className="flex items-center justify-between gap-3"><p className="text-sm text-gray-500 dark:text-gray-400">Pagina {history.meta.current_page} di {history.meta.last_page} · {history.meta.total} registrazioni</p><div className="flex gap-2"><Button type="button" variant="outline" size="sm" disabled={!canPrevious} onClick={() => setPage((current) => current - 1)}>Precedente</Button><Button type="button" variant="outline" size="sm" disabled={!canNext} onClick={() => setPage((current) => current + 1)}>Successiva</Button></div></div></> : null}
    {detailLoadingId !== null ? <p className="text-sm text-gray-500 dark:text-gray-400" role="status">Apertura fotografia…</p> : null}
    {detail ? <BudgetApprovalDetailDialog approval={detail} onClose={() => setDetail(null)} /> : null}
  </ComponentCard>;
}
