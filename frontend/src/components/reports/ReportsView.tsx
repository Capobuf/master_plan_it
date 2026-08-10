import { useEffect, useMemo, useState } from "react";
import { ApiError } from "../../api/client";
import { listExpenseCostCenters, listExpenseVendors, type ExpenseLookupOption } from "../../api/expenses";
import { listProjectOptions, type ProjectLookupOption } from "../../api/projects";
import {
  getReports,
  type ReportExpenseState,
  type ReportGrouping,
  type ReportsQuery,
  type ReportsResponse,
} from "../../api/reports";
import { AlertHexaIcon, BoxIconLine, CheckCircleIcon, DollarLineIcon, ListIcon, PieChartIcon } from "../../icons";
import { formatMoney, formatPercentage, isPositiveDecimal, toChartNumber } from "../../presentation/formatters";
import ComponentCard from "../common/ComponentCard";
import BreakdownDonutChart from "../dashboard/BreakdownDonutChart";
import EcommerceMetrics, { type EcommerceMetric } from "../ecommerce/EcommerceMetrics";
import StatisticsChart from "../ecommerce/StatisticsChart";
import Label from "../form/Label";
import Select from "../form/Select";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";
import ReportEconomicChart from "./ReportEconomicChart";

const groupings: Array<{ value: ReportGrouping; label: string }> = [
  { value: "cost_center", label: "Centro di Costo" },
  { value: "project", label: "Progetto" },
  { value: "contract", label: "Contratto" },
  { value: "vendor", label: "Fornitore" },
  { value: "expense", label: "Spesa" },
];

interface DraftFilters {
  grouping: ReportGrouping;
  costCenter: string;
  project: string;
  vendor: string;
  expenseState: "" | ReportExpenseState;
  view: "current" | "historical";
  asOf: string;
}

interface ReportsViewProps {
  tenantId: number | null;
  planningYearId: number | null;
  canView: boolean;
  canLoadCostCenters: boolean;
  canLoadProjects: boolean;
  canLoadVendors: boolean;
}

const defaultDraft = (): DraftFilters => ({
  grouping: "cost_center",
  costCenter: "",
  project: "",
  vendor: "",
  expenseState: "",
  view: "current",
  asOf: "",
});

const defaultQuery = (): ReportsQuery => ({ page: 1, per_page: 15, group_by: "cost_center" });

function ReportsPagination({ meta, onPage }: { meta: ReportsResponse["meta"]; onPage: (page: number) => void }) {
  if (meta.last_page <= 1) return null;
  return (
    <nav className="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800" aria-label="Paginazione Report">
      <p className="text-sm text-gray-500 dark:text-gray-400">Pagina {meta.current_page} di {meta.last_page} · {meta.total} gruppi</p>
      <div className="flex gap-2">
        <Button type="button" onClick={() => onPage(meta.current_page - 1)} disabled={meta.current_page <= 1} size="sm" variant="outline">Precedente</Button>
        <Button type="button" onClick={() => onPage(meta.current_page + 1)} disabled={meta.current_page >= meta.last_page} size="sm" variant="outline">Successiva</Button>
      </div>
    </nav>
  );
}

export default function ReportsView({
  tenantId,
  planningYearId,
  canView,
  canLoadCostCenters,
  canLoadProjects,
  canLoadVendors,
}: ReportsViewProps) {
  const [draft, setDraft] = useState<DraftFilters>(defaultDraft);
  const [appliedQuery, setAppliedQuery] = useState<ReportsQuery>(defaultQuery);
  const [costCenters, setCostCenters] = useState<ExpenseLookupOption[]>([]);
  const [projects, setProjects] = useState<ProjectLookupOption[]>([]);
  const [vendors, setVendors] = useState<ExpenseLookupOption[]>([]);
  const [lookupErrors, setLookupErrors] = useState<Partial<Record<"costCenter" | "project" | "vendor", string>>>({});
  const [reportState, setReportState] = useState<{
    scope: string;
    response: ReportsResponse | null;
    error: ApiError | null;
    loading: boolean;
  } | null>(null);

  useEffect(() => {
    setCostCenters([]);
    setProjects([]);
    setVendors([]);
    setLookupErrors({});
    if (tenantId === null) return;
    let active = true;
    if (canLoadCostCenters) {
      void listExpenseCostCenters()
        .then((items) => { if (active) setCostCenters(items); })
        .catch((error) => { if (active) setLookupErrors((current) => ({ ...current, costCenter: ApiError.from(error).message })); });
    }
    if (canLoadProjects) {
      void listProjectOptions()
        .then((items) => { if (active) setProjects(items); })
        .catch((error) => { if (active) setLookupErrors((current) => ({ ...current, project: ApiError.from(error).message })); });
    }
    if (canLoadVendors) {
      void listExpenseVendors()
        .then((items) => { if (active) setVendors(items); })
        .catch((error) => { if (active) setLookupErrors((current) => ({ ...current, vendor: ApiError.from(error).message })); });
    }
    return () => { active = false; };
  }, [canLoadCostCenters, canLoadProjects, canLoadVendors, tenantId]);

  useEffect(() => {
    if (tenantId === null || planningYearId === null || !canView) {
      setReportState(null);
      return;
    }
    const scope = `${tenantId}:${planningYearId}`;
    let active = true;
    setReportState({ scope, response: null, error: null, loading: true });
    void getReports({ ...appliedQuery, planning_year_id: planningYearId })
      .then((response) => { if (active) setReportState({ scope, response, error: null, loading: false }); })
      .catch((error) => { if (active) setReportState({ scope, response: null, error: ApiError.from(error), loading: false }); });
    return () => { active = false; };
  }, [appliedQuery, canView, planningYearId, tenantId]);

  const currentScope = tenantId !== null && planningYearId !== null ? `${tenantId}:${planningYearId}` : null;
  const response = reportState?.scope === currentScope ? reportState.response : null;
  const error = reportState?.scope === currentScope ? reportState.error : null;
  const loading = reportState?.scope === currentScope && reportState.loading;
  const currency = response?.summary.currency ?? "EUR";
  const dimension = groupings.find((item) => item.value === response?.filters.group_by)?.label ?? "Centro di Costo";
  const topGroups = response?.visualization.groups ?? [];

  const metrics = useMemo<EcommerceMetric[]>(() => response ? [
    { label: "Proposto", value: formatMoney(response.summary.proposed, currency), icon: PieChartIcon },
    { label: "Approvato", value: formatMoney(response.summary.approved_current, currency), icon: CheckCircleIcon },
    { label: "Actual", value: formatMoney(response.summary.actual, currency), icon: DollarLineIcon },
    { label: "Residuo", value: formatMoney(response.summary.residual, currency), icon: BoxIconLine },
    { label: "Scostamento", value: formatMoney(response.summary.variance, currency), icon: AlertHexaIcon },
    { label: "Utilizzo", value: formatPercentage(response.summary.utilization_percentage), icon: ListIcon },
  ] : [], [currency, response]);

  const applyFilters = () => {
    setAppliedQuery({
      page: 1,
      per_page: 15,
      group_by: draft.grouping,
      ...(draft.costCenter ? { cost_center_id: Number.parseInt(draft.costCenter, 10) } : {}),
      ...(draft.project ? { project_id: Number.parseInt(draft.project, 10) } : {}),
      ...(draft.vendor ? { vendor_id: Number.parseInt(draft.vendor, 10) } : {}),
      ...(draft.expenseState ? { state: draft.expenseState } : {}),
      ...(draft.view === "historical" && draft.asOf ? { as_of: draft.asOf } : {}),
    });
  };

  const resetFilters = () => {
    setDraft(defaultDraft());
    setAppliedQuery(defaultQuery());
  };

  if (planningYearId === null) {
    return <Alert variant="info" title="Planning Year richiesto" message="Seleziona il Planning Year globale dall'intestazione per aprire il Report." />;
  }

  const showAttentions = Boolean(response && (response.summary.unapproved_actual_expenses > 0 || isPositiveDecimal(response.global_plafond_overrun)));
  const varianceHeight = Math.min(340, Math.max(180, topGroups.length * 30 + 90));

  return (
    <div className="space-y-5 sm:space-y-6">
      <form
        onSubmit={(event) => { event.preventDefault(); applyFilters(); }}
        className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] sm:p-5"
      >
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
          <div><Label htmlFor="report-grouping">Raggruppa per</Label><Select id="report-grouping" options={groupings} value={draft.grouping} onChange={(value) => setDraft((current) => ({ ...current, grouping: value as ReportGrouping }))} /></div>
          {canLoadCostCenters ? <div><Label htmlFor="report-cost-center">Centro di Costo</Label><Select id="report-cost-center" options={costCenters.map((item) => ({ value: String(item.id), label: item.name }))} value={draft.costCenter} placeholder="Tutti" allowEmpty onChange={(value) => setDraft((current) => ({ ...current, costCenter: value }))} /></div> : null}
          {canLoadProjects ? <div><Label htmlFor="report-project">Progetto</Label><Select id="report-project" options={projects.map((item) => ({ value: String(item.id), label: item.title }))} value={draft.project} placeholder="Tutti" allowEmpty onChange={(value) => setDraft((current) => ({ ...current, project: value }))} /></div> : null}
          {canLoadVendors ? <div><Label htmlFor="report-vendor">Fornitore</Label><Select id="report-vendor" options={vendors.map((item) => ({ value: String(item.id), label: item.name }))} value={draft.vendor} placeholder="Tutti" allowEmpty onChange={(value) => setDraft((current) => ({ ...current, vendor: value }))} /></div> : null}
          <div><Label htmlFor="report-state">Stato Spesa</Label><Select id="report-state" options={[{ value: "open", label: "Aperte" }, { value: "closed", label: "Chiuse" }]} value={draft.expenseState} placeholder="Tutte" allowEmpty onChange={(value) => setDraft((current) => ({ ...current, expenseState: value as "" | ReportExpenseState }))} /></div>
          <div><Label htmlFor="report-view">Vista</Label><Select id="report-view" options={[{ value: "current", label: "Corrente" }, { value: "historical", label: "Storica" }]} value={draft.view} onChange={(value) => setDraft((current) => ({ ...current, view: value as DraftFilters["view"], ...(value === "current" ? { asOf: "" } : {}) }))} /></div>
          {draft.view === "historical" ? <div className="sm:col-span-2 xl:col-span-2"><Label htmlFor="report-as-of">Cutoff</Label><input id="report-as-of" type="datetime-local" required value={draft.asOf} onChange={(event) => setDraft((current) => ({ ...current, asOf: event.target.value }))} className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" /></div> : null}
        </div>
        <div className="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" onClick={resetFilters}>Azzera filtri</Button>
          <Button type="submit">Applica filtri</Button>
        </div>
        {Object.entries(lookupErrors).map(([key, message]) => <p key={key} className="mt-2 text-sm text-error-600 dark:text-error-500">Filtro non disponibile: {message}</p>)}
      </form>

      {response?.read_only ? <Alert variant="info" title="Report storico in sola lettura" message={`Cutoff: ${response.cutoff_utc ?? response.requested_as_of ?? "richiesto"}.`} /> : null}
      {response?.budget.warning ? <Alert variant="warning" title="Budget chiuso" message="Il Report include le modifiche economiche registrate dopo la chiusura." /> : null}
      {error ? <Alert variant="error" title="Caricamento del Report non riuscito" message={error.message} /> : null}
      {loading || (!error && !response) ? <Alert variant="info" title="Caricamento del Report" message="Recupero dei dati annuali." /> : null}

      {response ? <EcommerceMetrics metrics={metrics} columns={6} /> : null}
      {response && response.data.length === 0 ? <Alert variant="info" title="Nessun risultato" message="Nessun gruppo corrisponde ai filtri applicati." /> : null}

      {response ? (
        <>
          <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-3">
            <div className="xl:col-span-2">
              <ReportEconomicChart
                title={`Proposto vs Approvato vs Actual per ${dimension}`}
                categories={topGroups.map((group) => group.label)}
                proposed={topGroups.map((group) => toChartNumber(group.proposed))}
                approved={topGroups.map((group) => toChartNumber(group.approved))}
                actual={topGroups.map((group) => toChartNumber(group.actual))}
                currency={currency}
              />
            </div>
            <BreakdownDonutChart
              title={`Ripartizione del Proposto per ${dimension}`}
              entries={response.visualization.proposed_breakdown.map((entry) => ({ label: entry.label, value: toChartNumber(entry.proposed), displayValue: formatMoney(entry.proposed, currency) }))}
              emptyMessage="Nessun importo proposto da ripartire."
              centerLabel="Proposto"
              centerValue={formatMoney(response.summary.proposed, currency)}
              totalLabel="Totale Proposto"
              totalValue={formatMoney(response.summary.proposed, currency)}
            />
          </div>

          <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-3">
            <div className="xl:col-span-2">
              {topGroups.length > 0 ? (
                <StatisticsChart
                  title={`Scostamento per ${dimension}`}
                  categories={topGroups.map((group) => group.label)}
                  values={topGroups.map((group) => toChartNumber(group.variance))}
                  currency={currency}
                  type="bar"
                  seriesName="Scostamento"
                  horizontal
                  height={varianceHeight}
                  mobileHeight={varianceHeight}
                  distributed
                  colors={topGroups.map((group) => toChartNumber(group.variance) > 0 ? "#F04438" : toChartNumber(group.variance) < 0 ? "#12B76A" : "#98A2B3")}
                />
              ) : (
                <ComponentCard title={`Scostamento per ${dimension}`} compact><p className="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nessun gruppo da rappresentare.</p></ComponentCard>
              )}
            </div>
            <div className="space-y-4">
              <BreakdownDonutChart
                title="Stato Spese"
                entries={[
                  { label: "Aperte", value: response.visualization.expense_states.open, displayValue: String(response.visualization.expense_states.open), color: "#465FFF" },
                  { label: "Chiuse", value: response.visualization.expense_states.closed, displayValue: String(response.visualization.expense_states.closed), color: "#12B76A" },
                ]}
                emptyMessage="Nessuna Spesa nel dataset filtrato."
                centerLabel="Spese"
                centerValue={String(response.visualization.expense_states.total)}
              />
              {showAttentions ? (
                <ComponentCard title="Attenzioni" compact>
                  <div className="flex flex-wrap gap-2">
                    {response.summary.unapproved_actual_expenses > 0 ? <Badge color="warning">Actual senza approvato: {response.summary.unapproved_actual_expenses}</Badge> : null}
                    {isPositiveDecimal(response.global_plafond_overrun) ? <Badge color="error">Sforamento Plafond complessivo: {formatMoney(response.global_plafond_overrun, currency)}</Badge> : null}
                  </div>
                </ComponentCard>
              ) : null}
            </div>
          </div>

          <section className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] sm:p-5">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h2 className="text-base font-semibold text-gray-800 dark:text-white/90 sm:text-lg">Dettaglio per {dimension}</h2>
              <Badge color="info">{response.summary.official_basis}</Badge>
            </div>
            {response.data.length === 0 ? (
              <p className="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nessun dettaglio disponibile per i filtri applicati.</p>
            ) : (
              <div className="max-w-full overflow-x-auto">
                <Table>
                  <TableHeader className="border-y border-gray-100 dark:border-gray-800">
                    <TableRow>
                      <TableCell isHeader className="whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Gruppo</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 md:table-cell dark:text-gray-400">Proposto</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 xl:table-cell dark:text-gray-400">Approvato</TableCell>
                      <TableCell isHeader className="whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Actual</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 xl:table-cell dark:text-gray-400">Residuo</TableCell>
                      <TableCell isHeader className="whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Scostamento</TableCell>
                      <TableCell isHeader className="whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Utilizzo</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 lg:table-cell dark:text-gray-400">Aperte / Chiuse</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 xl:table-cell dark:text-gray-400">Actual senza approvato</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 text-start text-theme-xs font-medium text-gray-500 xl:table-cell dark:text-gray-400">Plafond</TableCell>
                    </TableRow>
                  </TableHeader>
                  <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {response.data.map((group) => (
                      <TableRow key={group.key}>
                        <TableCell className="max-w-40 py-3 pr-4 text-sm font-medium text-gray-800 dark:text-white/90"><span className="block truncate" title={group.label}>{group.label}</span></TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 pr-4 text-sm text-gray-600 md:table-cell dark:text-gray-300">{formatMoney(group.proposed, currency)}</TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 pr-4 text-sm text-gray-600 xl:table-cell dark:text-gray-300">{formatMoney(group.approved, currency)}</TableCell>
                        <TableCell className="whitespace-nowrap py-3 pr-4 text-sm text-gray-800 dark:text-white/90">{formatMoney(group.actual, currency)}</TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 pr-4 text-sm text-gray-600 xl:table-cell dark:text-gray-300">{formatMoney(group.residual, currency)}</TableCell>
                        <TableCell className="whitespace-nowrap py-3 pr-4 text-sm text-gray-800 dark:text-white/90">{formatMoney(group.variance, currency)}</TableCell>
                        <TableCell className="whitespace-nowrap py-3 pr-4 text-sm text-gray-800 dark:text-white/90">{formatPercentage(group.utilization_percentage)}</TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 pr-4 text-sm text-gray-600 lg:table-cell dark:text-gray-300">{group.open_expenses} / {group.closed_expenses}</TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 pr-4 text-sm text-gray-600 xl:table-cell dark:text-gray-300">{group.unapproved_actual_expenses}</TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 text-sm text-gray-600 xl:table-cell dark:text-gray-300">{group.plafond_expenses}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
            <div className="mt-5"><ReportsPagination meta={response.meta} onPage={(page) => setAppliedQuery((current) => ({ ...current, page }))} /></div>
          </section>
        </>
      ) : null}
    </div>
  );
}
