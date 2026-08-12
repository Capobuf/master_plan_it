import { Fragment, useEffect, useMemo, useState } from "react";
import { Link } from "react-router";
import { ApiError } from "../../api/client";
import { listExpenseCostCenters, listExpenseVendors, type ExpenseLookupOption } from "../../api/expenses";
import { listProjectOptions, type ProjectLookupOption } from "../../api/projects";
import {
  getReports,
  type ReportGrouping,
  type ReportsQuery,
  type ReportsResponse,
} from "../../api/reports";
import { AlertHexaIcon, BoxIconLine, CheckCircleIcon, DollarLineIcon, ListIcon, PieChartIcon } from "../../icons";
import { formatMoney, formatPercentage, toChartNumber } from "../../presentation/formatters";
import { routes } from "../../navigation/routes";
import EcommerceMetrics, { type EcommerceMetric } from "../ecommerce/EcommerceMetrics";
import Label from "../form/Label";
import Select from "../form/Select";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";
import ReportEconomicChart from "./ReportEconomicChart";
import PlafondReport from "../plafonds/PlafondReport";

const groupings: Array<{ value: ReportGrouping; label: string }> = [
  { value: "cost_center", label: "Centro di Costo" },
  { value: "project", label: "Progetto" },
  { value: "contract", label: "Contratto" },
  { value: "vendor", label: "Fornitore" },
  { value: "expense", label: "Spesa" },
];

interface ReportFiltersState {
  grouping: ReportGrouping;
  costCenter: string;
  project: string;
  vendor: string;
  view: "current" | "historical";
  asOf: string;
  page: number;
}

interface ReportsViewProps {
  tenantId: number | null;
  planningYearId: number | null;
  canView: boolean;
  canLoadCostCenters: boolean;
  canLoadProjects: boolean;
  canLoadVendors: boolean;
}

const defaultFilters = (): ReportFiltersState => ({
  grouping: "cost_center",
  costCenter: "",
  project: "",
  vendor: "",
  view: "current",
  asOf: "",
  page: 1,
});

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
  const [filters, setFilters] = useState<ReportFiltersState>(defaultFilters);
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
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());

  const reportQuery = useMemo<ReportsQuery>(() => ({
    page: filters.page,
    per_page: 15,
    group_by: filters.grouping,
    ...(filters.costCenter ? { cost_center_id: Number.parseInt(filters.costCenter, 10) } : {}),
    ...(filters.project ? { project_id: Number.parseInt(filters.project, 10) } : {}),
    ...(filters.vendor ? { vendor_id: Number.parseInt(filters.vendor, 10) } : {}),
    ...(filters.view === "historical" && filters.asOf ? { as_of: filters.asOf } : {}),
  }), [filters]);

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
    if (filters.view === "historical" && !filters.asOf) {
      setReportState((current) => current?.scope === scope ? { ...current, error: null, loading: false } : current);
      return;
    }
    let active = true;
    setReportState((current) => ({
      scope,
      response: current?.scope === scope ? current.response : null,
      error: null,
      loading: true,
    }));
    void getReports({ ...reportQuery, planning_year_id: planningYearId })
      .then((response) => { if (active) setReportState({ scope, response, error: null, loading: false }); })
      .catch((error) => {
        if (active) {
          setReportState((current) => ({
            scope,
            response: current?.scope === scope ? current.response : null,
            error: ApiError.from(error),
            loading: false,
          }));
        }
      });
    return () => { active = false; };
  }, [canView, filters.asOf, filters.view, planningYearId, reportQuery, tenantId]);

  const currentScope = tenantId !== null && planningYearId !== null ? `${tenantId}:${planningYearId}` : null;
  const response = reportState?.scope === currentScope ? reportState.response : null;
  const error = reportState?.scope === currentScope ? reportState.error : null;
  const loading = Boolean(reportState?.scope === currentScope && reportState.loading);
  const currency = response?.currency ?? "EUR";
  const dimension = groupings.find((item) => item.value === response?.filters.group_by)?.label ?? "Centro di Costo";
  const topGroups = response?.data ?? [];

  const metrics = useMemo<EcommerceMetric[]>(() => response ? [
    { label: "Pianificazione corrente", value: formatMoney(response.totals.current_planning.official, currency), icon: PieChartIcon },
    { label: "Approvato", value: formatMoney(response.summary.approved_current, currency), icon: CheckCircleIcon },
    { label: "Effettivi", value: formatMoney(response.totals.actual.official, currency), icon: DollarLineIcon },
    { label: "Residuo", value: formatMoney(response.summary.residual, currency), icon: BoxIconLine },
    { label: "Scostamento", value: formatMoney(response.summary.variance, currency), icon: AlertHexaIcon },
    { label: "Utilizzo", value: formatPercentage(response.summary.utilization_percentage), icon: ListIcon },
  ] : [], [currency, response]);

  const resetFilters = () => {
    setFilters(defaultFilters());
  };

  if (planningYearId === null) {
    return <Alert variant="info" title="Planning Year richiesto" message="Seleziona il Planning Year globale dall'intestazione per aprire il Report." />;
  }

  return (
    <div className="space-y-5 sm:space-y-6">
      <section
        aria-busy={loading}
        className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] sm:p-5"
      >
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <div><Label htmlFor="report-grouping">Raggruppa per</Label><Select id="report-grouping" options={groupings} value={filters.grouping} onChange={(value) => setFilters((current) => ({ ...current, grouping: value as ReportGrouping, page: 1 }))} /></div>
          {canLoadCostCenters ? <div><Label htmlFor="report-cost-center">Centro di Costo</Label><Select id="report-cost-center" options={costCenters.map((item) => ({ value: String(item.id), label: item.name }))} value={filters.costCenter} placeholder="Tutti" allowEmpty onChange={(value) => setFilters((current) => ({ ...current, costCenter: value, page: 1 }))} /></div> : null}
          {canLoadProjects ? <div><Label htmlFor="report-project">Progetto</Label><Select id="report-project" options={projects.map((item) => ({ value: String(item.id), label: item.title }))} value={filters.project} placeholder="Tutti" allowEmpty onChange={(value) => setFilters((current) => ({ ...current, project: value, page: 1 }))} /></div> : null}
          {canLoadVendors ? <div><Label htmlFor="report-vendor">Fornitore</Label><Select id="report-vendor" options={vendors.map((item) => ({ value: String(item.id), label: item.name }))} value={filters.vendor} placeholder="Tutti" allowEmpty onChange={(value) => setFilters((current) => ({ ...current, vendor: value, page: 1 }))} /></div> : null}
          <div><Label htmlFor="report-view">Vista</Label><Select id="report-view" options={[{ value: "current", label: "Corrente" }, { value: "historical", label: "Storica" }]} value={filters.view} onChange={(value) => setFilters((current) => ({ ...current, view: value as ReportFiltersState["view"], asOf: value === "current" ? "" : current.asOf, page: 1 }))} /></div>
          {filters.view === "historical" ? <div className="sm:col-span-2 xl:col-span-2"><Label htmlFor="report-as-of">Cutoff</Label><input id="report-as-of" type="datetime-local" required value={filters.asOf} onChange={(event) => setFilters((current) => ({ ...current, asOf: event.target.value, page: 1 }))} className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" /></div> : null}
        </div>
        <div className="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
          {loading && response ? <p className="text-sm text-gray-500 dark:text-gray-400" aria-live="polite">Aggiornamento…</p> : null}
          <Button type="button" variant="outline" onClick={resetFilters}>Azzera filtri</Button>
        </div>
        {Object.entries(lookupErrors).map(([key, message]) => <p key={key} className="mt-2 text-sm text-error-600 dark:text-error-500">Filtro non disponibile: {message}</p>)}
      </section>

      {response?.read_only ? <Alert variant="info" title="Report storico in sola lettura" message={`Cutoff: ${response.cutoff_utc ?? response.requested_as_of ?? "richiesto"}.`} /> : null}
      {response?.budget.warning ? <Alert variant="warning" title="Budget chiuso" message="Il Report include le modifiche economiche registrate dopo la chiusura." /> : null}
      {error ? <Alert variant="error" title="Caricamento del Report non riuscito" message={error.message} /> : null}
      {!response && loading ? <Alert variant="info" title="Caricamento del Report" message="Recupero dei dati annuali." /> : null}

      {response ? <EcommerceMetrics metrics={metrics} columns={6} /> : null}
      {response && filters.view === "current" ? <PlafondReport planningYearId={planningYearId} {...(filters.costCenter ? { costCenterId: Number.parseInt(filters.costCenter, 10) } : {})} /> : null}
      {response && response.data.length === 0 ? <Alert variant="info" title="Nessun risultato" message="Nessun gruppo corrisponde ai filtri applicati." /> : null}

      {response ? (
        <>
          {topGroups.length > 0 ? <ReportEconomicChart
            title={`Pianificazione, approvato ed Effettivi per ${dimension}`}
            categories={topGroups.map((group) => group.label)}
            proposed={topGroups.map((group) => toChartNumber(group.totals.current_planning.official))}
            approved={topGroups.map((group) => toChartNumber(group.approved))}
            actual={topGroups.map((group) => toChartNumber(group.totals.actual.official))}
            currency={currency}
          /> : null}

          <section className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] sm:p-5">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h2 className="text-base font-semibold text-gray-800 dark:text-white/90 sm:text-lg">Dettaglio per {dimension}</h2>
              <Badge color="info">{response.basis}</Badge>
            </div>
            {response.data.length === 0 ? (
              <p className="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nessun dettaglio disponibile per i filtri applicati.</p>
            ) : (
              <div className="max-w-full overflow-x-auto" role="region" tabIndex={0} aria-label={`Dettaglio Report per ${dimension}`}>
                <Table>
                  <TableHeader className="border-y border-gray-100 dark:border-gray-800">
                    <TableRow>
                      <TableCell isHeader className="whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Gruppo</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 md:table-cell dark:text-gray-400">Pianificazione corrente</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 xl:table-cell dark:text-gray-400">Approvato</TableCell>
                      <TableCell isHeader className="whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Effettivi</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 xl:table-cell dark:text-gray-400">Residuo</TableCell>
                      <TableCell isHeader className="whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Scostamento</TableCell>
                      <TableCell isHeader className="whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Utilizzo</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 pr-4 text-start text-theme-xs font-medium text-gray-500 xl:table-cell dark:text-gray-400">Effettivi senza approvato</TableCell>
                      <TableCell isHeader className="hidden whitespace-nowrap py-3 text-start text-theme-xs font-medium text-gray-500 xl:table-cell dark:text-gray-400">Plafond</TableCell>
                    </TableRow>
                  </TableHeader>
                  <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {response.data.map((group) => {
                      const expanded = expandedGroups.has(group.key);
                      const detailId = `report-group-${group.key.replace(/[^a-zA-Z0-9_-]/g, "-")}`;

                      return <Fragment key={group.key}>
                      <TableRow>
                        <TableCell className="max-w-48 py-3 pr-4 text-sm font-medium text-gray-800 dark:text-white/90">
                          <button
                            type="button"
                            className="flex w-full items-center gap-2 rounded text-start hover:text-brand-600 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:hover:text-brand-400"
                            aria-expanded={expanded}
                            aria-controls={detailId}
                            onClick={() => setExpandedGroups((current) => {
                              const next = new Set(current);
                              if (next.has(group.key)) next.delete(group.key); else next.add(group.key);
                              return next;
                            })}
                          >
                            <span aria-hidden="true">{expanded ? "−" : "+"}</span>
                            <span className="block truncate" title={group.label}>{group.label}</span>
                          </button>
                        </TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 pr-4 text-sm text-gray-600 md:table-cell dark:text-gray-300">{formatMoney(group.totals.current_planning.official, currency)}</TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 pr-4 text-sm text-gray-600 xl:table-cell dark:text-gray-300">{formatMoney(group.approved, currency)}</TableCell>
                        <TableCell className="whitespace-nowrap py-3 pr-4 text-sm text-gray-800 dark:text-white/90">{formatMoney(group.totals.actual.official, currency)}</TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 pr-4 text-sm text-gray-600 xl:table-cell dark:text-gray-300">{formatMoney(group.residual, currency)}</TableCell>
                        <TableCell className="whitespace-nowrap py-3 pr-4 text-sm text-gray-800 dark:text-white/90">{formatMoney(group.variance, currency)}</TableCell>
                        <TableCell className="whitespace-nowrap py-3 pr-4 text-sm text-gray-800 dark:text-white/90">{formatPercentage(group.utilization_percentage)}</TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 pr-4 text-sm text-gray-600 xl:table-cell dark:text-gray-300">{group.unapproved_actual_expenses}</TableCell>
                        <TableCell className="hidden whitespace-nowrap py-3 text-sm text-gray-600 xl:table-cell dark:text-gray-300">{group.plafond_expenses}</TableCell>
                      </TableRow>
                      {expanded ? <tr id={detailId}>
                        <td colSpan={9} className="bg-gray-50 px-4 py-3 dark:bg-white/[0.02]">
                          {group.lines.length === 0 ? (
                            <p className="text-sm text-gray-500 dark:text-gray-400">Nessuna riga economica nel gruppo.</p>
                          ) : (
                            <ul className="space-y-2" aria-label={`Righe economiche di ${group.label}`}>
                              {group.lines.map((line) => (
                                <li key={`${line.expense_id}:${line.row_id}`} className="rounded-lg border border-gray-200 bg-white p-3 text-sm dark:border-gray-700 dark:bg-gray-900">
                                  <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Link
                                      className="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                      to={line.type === "allocation_adjustment" ? routes.plafond(line.expense_id) : `/spese/${line.expense_id}`}
                                    >
                                      {line.description}
                                    </Link>
                                    <span className="font-medium text-gray-800 dark:text-white/90">{formatMoney(line.amount.official, currency)}</span>
                                  </div>
                                  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {line.type === "allocation_adjustment" ? "Variazione Allocazione" : line.type === "actual" ? "Effettivo" : line.type === "quote" ? "Preventivo" : "Stima"}
                                    {line.is_current_planning ? " · Pianificazione corrente" : ""}
                                    {line.spend_date ? ` · Data Effettiva ${line.spend_date}` : ""}
                                  </p>
                                  {line.notes ? <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">{line.notes}</p> : null}
                                </li>
                              ))}
                            </ul>
                          )}
                        </td>
                      </tr> : null}
                      </Fragment>;
                    })}
                  </TableBody>
                </Table>
              </div>
            )}
            <div className="mt-5"><ReportsPagination meta={response.meta} onPage={(page) => setFilters((current) => ({ ...current, page }))} /></div>
          </section>
        </>
      ) : null}
    </div>
  );
}
