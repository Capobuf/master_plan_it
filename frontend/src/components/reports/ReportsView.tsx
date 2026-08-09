import { useEffect, useState } from "react";
import { ApiError } from "../../api/client";
import { listExpenseCostCenters, type ExpenseLookupOption } from "../../api/expenses";
import { getReports, type ReportsQuery, type ReportsResponse } from "../../api/reports";
import { formatDate, formatMoney } from "../../presentation/formatters";
import { domainLabel } from "../../presentation/labels";
import Label from "../form/Label";
import Select from "../form/Select";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

function Summary({ response }: { response: ReportsResponse }) {
  const currency = response.summary?.currency ?? response.scope?.currency ?? "EUR";
  const amounts = response.summary?.amounts ?? {};
  return <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    {([['Primary (ufficiale)', 'primary'], ['Proposed', 'proposed'], ['Idea', 'idea'], ['Excluded', 'excluded'], ['Potential (non ufficiale)', 'potential']] as const).map(([label, key]) => <article key={key} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><p className="text-sm text-gray-500 dark:text-gray-400">{label}</p><p className="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{formatMoney(amounts[key] ?? "0.00", currency)}</p></article>)}
  </div>;
}

function ReportsPagination({ meta, onPage }: { meta: ReportsResponse["meta"]; onPage: (page: number) => void }) {
  if (meta.last_page <= 1) return null;
  return <nav className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800" aria-label="Paginazione Report">
    <p className="text-sm text-gray-500 dark:text-gray-400">Pagina {meta.current_page} di {meta.last_page} · {meta.total} righe</p>
    <div className="flex items-center gap-2"><Button onClick={() => onPage(meta.current_page - 1)} disabled={meta.current_page <= 1} size="sm" variant="outline">Precedente</Button><Button onClick={() => onPage(meta.current_page + 1)} disabled={meta.current_page >= meta.last_page} size="sm" variant="outline">Successiva</Button></div>
  </nav>;
}

export default function ReportsView({ tenantId, planningYearId, canView, canLoadCostCenters }: { tenantId: number | null; planningYearId: number | null; canView: boolean; canLoadCostCenters: boolean }) {
  const [costCenter, setCostCenter] = useState("");
  const [costCenters, setCostCenters] = useState<ExpenseLookupOption[]>([]);
  const [lookupError, setLookupError] = useState<string | null>(null);
  const [query, setQuery] = useState<ReportsQuery>({ page: 1, per_page: 15 });
  const [state, setState] = useState<{ tenantId: number; response: ReportsResponse | null; error: ApiError | null } | null>(null);

  useEffect(() => {
    if (tenantId === null || !canLoadCostCenters) return;
    let active = true;
    setLookupError(null);
    void listExpenseCostCenters().then((items) => { if (active) setCostCenters(items); }).catch((error) => { if (active) setLookupError(ApiError.from(error).message); });
    return () => { active = false; };
  }, [canLoadCostCenters, tenantId]);

  useEffect(() => {
    if (tenantId === null || planningYearId === null || !canView) return;
    let active = true;
    setState((current) => current?.tenantId === tenantId ? { ...current, response: null, error: null } : current);
    void getReports({ ...query, planning_year_id: planningYearId }).then((response) => { if (active) setState({ tenantId, response, error: null }); }).catch((error) => { if (active) setState({ tenantId, response: null, error: ApiError.from(error) }); });
    return () => { active = false; };
  }, [canView, planningYearId, query, tenantId]);

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setQuery((current) => ({ page: 1, per_page: current.per_page, ...(costCenter ? { cost_center_id: Number.parseInt(costCenter, 10) } : {}) }));
  };
  const response = state?.tenantId === tenantId ? state.response : null;
  const error = state?.tenantId === tenantId ? state.error : null;

  return <div className="space-y-6">
    <form onSubmit={submit} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="flex flex-col gap-4 md:flex-row md:items-end">
        {canLoadCostCenters ? <div className="flex-1"><Label htmlFor="report-cost-center">Centro di costo</Label><Select id="report-cost-center" options={costCenters.map((item) => ({ value: String(item.id), label: item.name }))} value={costCenter} placeholder="Tutti i centri di costo" allowEmpty onChange={setCostCenter} /></div> : null}
        <div className="w-full md:w-40"><Label htmlFor="report-page-size">Righe per pagina</Label><Select id="report-page-size" options={[15, 25, 50, 100].map((size) => ({ value: String(size), label: String(size) }))} value={String(query.per_page ?? 15)} onChange={(value) => setQuery((current) => ({ ...current, page: 1, per_page: Number.parseInt(value, 10) }))} /></div>
        <Button>Applica Filtri</Button>
      </div>
      {lookupError ? <p className="mt-3 text-sm text-error-600 dark:text-error-400">Impossibile caricare i centri di costo: {lookupError}</p> : null}
    </form>
    {response ? <Summary response={response} /> : null}
    {error ? <Alert variant="error" title="Caricamento del Report non riuscito" message={error.correlationId ? `${error.message} Riferimento tecnico: ${error.correlationId}` : error.message} /> : null}
    {!error && !response ? <Alert variant="info" title="Caricamento del Report" message="Recupero delle righe economiche in corso." /> : null}
    {response && response.data.length === 0 ? <Alert variant="info" title="Nessun risultato" message="Nessuna riga corrisponde ai filtri applicati." /> : null}
    {response && response.data.length > 0 ? <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Dettaglio Economico</h2>
      <div className="max-w-full overflow-x-auto"><Table>
        <TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow>{['Data', 'Centro di costo', 'Natura e tipo', 'Progetto', 'Bucket', 'Conferma', 'Netto', 'IVA', 'Lordo'].map((header) => <TableCell key={header} isHeader className={`whitespace-nowrap py-3 text-theme-xs font-medium text-gray-500 dark:text-gray-400 ${['Netto','IVA','Lordo'].includes(header) ? 'text-end' : 'text-start'}`}>{header}</TableCell>)}</TableRow></TableHeader>
        <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{response.data.map((line) => <TableRow key={line.id}>
          <TableCell className="whitespace-nowrap py-3 text-sm text-gray-600 dark:text-gray-300">{formatDate(line.spend_date ?? line.period_start)}</TableCell>
          <TableCell className="py-3 text-sm font-medium text-gray-800 dark:text-white/90">{line.cost_center_name ?? "—"}</TableCell>
          <TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{domainLabel(line.expense_kind)} · {domainLabel(line.type)}{line.is_extra ? " · Extra" : ""}</TableCell>
          <TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{line.project_id ? `#${line.project_id} · ${domainLabel(line.project_stage)}` : "—"}</TableCell>
          <TableCell className="py-3"><Badge color={line.bucket === 'primary' ? 'success' : line.bucket === 'excluded' ? 'light' : 'info'} size="sm">{domainLabel(line.bucket)}</Badge></TableCell>
          <TableCell className="py-3">{line.confirmation_state ? <Badge color={line.confirmation_state === 'confirmed' ? 'success' : 'warning'} size="sm">{domainLabel(line.confirmation_state)}</Badge> : <span className="text-sm text-gray-500 dark:text-gray-400">—</span>}</TableCell>
          <TableCell className="whitespace-nowrap py-3 text-end text-sm text-gray-600 dark:text-gray-300">{formatMoney(line.net, line.currency)}</TableCell><TableCell className="whitespace-nowrap py-3 text-end text-sm text-gray-600 dark:text-gray-300">{formatMoney(line.vat, line.currency)}</TableCell><TableCell className="whitespace-nowrap py-3 text-end text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(line.gross, line.currency)}</TableCell>
        </TableRow>)}</TableBody>
      </Table></div>
      <div className="mt-5"><ReportsPagination meta={response.meta} onPage={(page) => setQuery((current) => ({ ...current, page }))} /></div>
    </section> : null}
  </div>;
}
