import { useEffect, useState } from "react";
import { ApiError } from "../../api/client";
import { listExpenseCostCenters, type ExpenseLookupOption } from "../../api/expenses";
import { getReports, type ReportGrouping, type ReportsQuery, type ReportsResponse } from "../../api/reports";
import { formatMoney } from "../../presentation/formatters";
import Label from "../form/Label";
import Select from "../form/Select";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

const groupings: Array<{ value: ReportGrouping; label: string }> = [
  { value: "cost_center", label: "Centro di costo" },
  { value: "project", label: "Progetto" },
  { value: "contract", label: "Contratto" },
  { value: "vendor", label: "Fornitore" },
  { value: "expense", label: "Spesa" },
];

function ReportsPagination({ meta, onPage }: { meta: ReportsResponse["meta"]; onPage: (page: number) => void }) {
  if (meta.last_page <= 1) return null;
  return <nav className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800" aria-label="Paginazione Report"><p className="text-sm text-gray-500">Pagina {meta.current_page} di {meta.last_page} · {meta.total} gruppi</p><div className="flex gap-2"><Button onClick={() => onPage(meta.current_page - 1)} disabled={meta.current_page <= 1} size="sm" variant="outline">Precedente</Button><Button onClick={() => onPage(meta.current_page + 1)} disabled={meta.current_page >= meta.last_page} size="sm" variant="outline">Successiva</Button></div></nav>;
}

export default function ReportsView({ tenantId, planningYearId, canView, canLoadCostCenters }: { tenantId: number | null; planningYearId: number | null; canView: boolean; canLoadCostCenters: boolean }) {
  const [costCenter, setCostCenter] = useState("");
  const [costCenters, setCostCenters] = useState<ExpenseLookupOption[]>([]);
  const [asOf, setAsOf] = useState("");
  const [lookupError, setLookupError] = useState<string | null>(null);
  const [query, setQuery] = useState<ReportsQuery>({ page: 1, per_page: 15, group_by: "cost_center" });
  const [state, setState] = useState<{ tenantId: number; response: ReportsResponse | null; error: ApiError | null } | null>(null);

  useEffect(() => {
    if (tenantId === null || !canLoadCostCenters) return;
    let active = true;
    void listExpenseCostCenters().then((items) => { if (active) setCostCenters(items); }).catch((error) => { if (active) setLookupError(ApiError.from(error).message); });
    return () => { active = false; };
  }, [canLoadCostCenters, tenantId]);

  useEffect(() => {
    if (tenantId === null || planningYearId === null || !canView) return;
    let active = true;
    void getReports({ ...query, planning_year_id: planningYearId, ...(asOf ? { as_of: asOf } : {}) }).then((response) => { if (active) setState({ tenantId, response, error: null }); }).catch((error) => { if (active) setState({ tenantId, response: null, error: ApiError.from(error) }); });
    return () => { active = false; };
  }, [asOf, canView, planningYearId, query, tenantId]);

  const response = state?.tenantId === tenantId ? state.response : null;
  const error = state?.tenantId === tenantId ? state.error : null;
  const currency = response?.summary.currency ?? "EUR";

  return <div className="space-y-6">
    <form onSubmit={(event) => { event.preventDefault(); setQuery((current) => ({ ...current, page: 1, ...(costCenter ? { cost_center_id: Number.parseInt(costCenter, 10) } : { cost_center_id: undefined }) })); }} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><div className="grid gap-4 md:grid-cols-4">
      {canLoadCostCenters ? <div><Label htmlFor="report-cost-center">Centro di costo</Label><Select id="report-cost-center" options={costCenters.map((item) => ({ value: String(item.id), label: item.name }))} value={costCenter} placeholder="Tutti" allowEmpty onChange={setCostCenter} /></div> : null}
      <div><Label htmlFor="report-grouping">Raggruppa per</Label><Select id="report-grouping" options={groupings} value={query.group_by ?? "cost_center"} onChange={(value) => setQuery((current) => ({ ...current, page: 1, group_by: value as ReportGrouping }))} /></div>
      <div><Label htmlFor="report-as-of">Vista al timestamp</Label><input id="report-as-of" type="datetime-local" value={asOf} onChange={(event) => setAsOf(event.target.value)} className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700" /></div>
      <div className="flex items-end"><Button>Applica filtri</Button></div>
    </div>{lookupError ? <p className="mt-3 text-sm text-error-600">{lookupError}</p> : null}</form>
    {response?.read_only ? <Alert variant="info" title="Report storico in sola lettura" message={`Cutoff: ${response.cutoff_utc ?? response.requested_as_of ?? "richiesto"}.`} /> : null}
    {response?.budget.warning ? <Alert variant="warning" title="Budget chiuso" message="Il report include le modifiche economiche registrate dopo la chiusura." /> : null}
    {error ? <Alert variant="error" title="Caricamento del Report non riuscito" message={error.message} /> : null}
    {!error && !response ? <Alert variant="info" title="Caricamento del Report" message="Recupero dei dati annuali." /> : null}
    {response ? <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">{[["Proposto", response.summary.proposed], ["Approvato", response.summary.approved_current], ["Actual", response.summary.actual], ["Residuo", response.summary.residual], ["Scostamento", response.summary.variance]].map(([label, value]) => <article key={label} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><p className="text-sm text-gray-500">{label}</p><p className="mt-2 text-xl font-bold">{formatMoney(value, currency)}</p></article>)}</div> : null}
    {response && response.data.length === 0 ? <Alert variant="info" title="Nessun risultato" message="Nessun gruppo corrisponde ai filtri." /> : null}
    {response && response.data.length > 0 ? <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><div className="mb-4 flex items-center justify-between"><h2 className="text-lg font-semibold">Dettaglio per {groupings.find((item) => item.value === response.filters.group_by)?.label}</h2><Badge color="info">{response.summary.official_basis}</Badge></div><div className="max-w-full overflow-x-auto"><Table><TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow>{["Gruppo", "Proposto", "Approvato", "Actual", "Residuo", "Scostamento", "Aperte/Chiuse", "Plafond"].map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap py-3 text-start text-theme-xs font-medium text-gray-500">{heading}</TableCell>)}</TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{response.data.map((group) => <TableRow key={group.key}><TableCell className="py-3 text-sm font-medium">{group.label}</TableCell><TableCell className="py-3 text-sm">{formatMoney(group.proposed, currency)}</TableCell><TableCell className="py-3 text-sm">{formatMoney(group.approved, currency)}</TableCell><TableCell className="py-3 text-sm">{formatMoney(group.actual, currency)}</TableCell><TableCell className="py-3 text-sm">{formatMoney(group.residual, currency)}</TableCell><TableCell className="py-3 text-sm">{formatMoney(group.variance, currency)}</TableCell><TableCell className="py-3 text-sm">{group.open_expenses}/{group.closed_expenses}</TableCell><TableCell className="py-3 text-sm">{group.plafond_expenses}</TableCell></TableRow>)}</TableBody></Table></div><div className="mt-5"><ReportsPagination meta={response.meta} onPage={(page) => setQuery((current) => ({ ...current, page }))} /></div></section> : null}
  </div>;
}
