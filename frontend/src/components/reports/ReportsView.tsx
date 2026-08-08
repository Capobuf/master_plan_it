import { useEffect, useState } from "react";
import { getReports, type ReportsQuery, type ReportsResponse } from "../../api/reports";
import { ApiError } from "../../api/client";
import {
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
} from "../ui/table";
import Badge from "../ui/badge/Badge";

const money = (value: string, currency: string): string => {
  const numeric = Number(value);
  return Number.isFinite(numeric)
    ? new Intl.NumberFormat(undefined, { style: "currency", currency, minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(numeric)
    : value;
};

function Summary({ response }: { response: ReportsResponse }) {
  const currency = response.summary?.currency ?? response.scope?.currency ?? "EUR";
  const amounts = response.summary?.amounts ?? {};
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
      {[
        ["Current position", amounts.official_current_position],
        ["Confirmed actual", amounts.actual_confirmed],
        ["Gross", amounts.gross],
      ].map(([label, value]) => (
        <div key={label} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
          <p className="text-sm text-gray-500 dark:text-gray-400">{label}</p>
          <p className="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">{money(value ?? "0.00", currency)}</p>
        </div>
      ))}
    </div>
  );
}

function ReportsPagination({ meta, onPage }: { meta: ReportsResponse["meta"]; onPage: (page: number) => void }) {
  if (meta.last_page <= 1) return null;
  const pages: number[] = [];
  const first = Math.max(1, meta.current_page - 2);
  const last = Math.min(meta.last_page, meta.current_page + 2);
  for (let page = first; page <= last; page += 1) pages.push(page);
  return (
    <nav className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800" aria-label="Reports pagination">
      <p className="text-sm text-gray-500 dark:text-gray-400">Page {meta.current_page} of {meta.last_page} · {meta.total} lines</p>
      <div className="flex items-center gap-1">
        <button type="button" onClick={() => onPage(meta.current_page - 1)} disabled={meta.current_page <= 1} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-300">Previous</button>
        {pages.map((page) => (
          <button key={page} type="button" onClick={() => onPage(page)} aria-current={page === meta.current_page ? "page" : undefined} className={`rounded-lg px-3 py-2 text-sm ${page === meta.current_page ? "bg-brand-500 text-white" : "border border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300"}`}>{page}</button>
        ))}
        <button type="button" onClick={() => onPage(meta.current_page + 1)} disabled={meta.current_page >= meta.last_page} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-300">Next</button>
      </div>
    </nav>
  );
}

export default function ReportsView({ tenantId, canView }: { tenantId: number | null; canView: boolean }) {
  const [year, setYear] = useState(String(new Date().getFullYear()));
  const [costCenter, setCostCenter] = useState("");
  const [query, setQuery] = useState<ReportsQuery>({ year: new Date().getFullYear(), page: 1, per_page: 15 });
  const [state, setState] = useState<{ tenantId: number; response: ReportsResponse | null; error: ApiError | null } | null>(null);

  useEffect(() => {
    if (tenantId === null || !canView || query.year === undefined) return;
    let active = true;
    void getReports(query)
      .then((response) => active && setState({ tenantId, response, error: null }))
      .catch((error: unknown) => active && setState({ tenantId, response: null, error: ApiError.from(error) }));
    return () => { active = false; };
  }, [canView, query, tenantId]);

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const next: ReportsQuery = { page: 1, per_page: query.per_page };
    const numericYear = Number(year);
    const numericCostCenter = Number(costCenter);
    if (Number.isInteger(numericYear) && numericYear > 0) next.year = numericYear;
    if (costCenter !== "" && Number.isInteger(numericCostCenter) && numericCostCenter > 0) next.cost_center_id = numericCostCenter;
    setQuery(next);
  };

  const response = state?.tenantId === tenantId ? state.response : null;
  const error = state?.tenantId === tenantId ? state.error : null;

  return (
    <div className="space-y-6">
      <form onSubmit={submit} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div className="flex flex-col gap-4 md:flex-row md:items-end">
          <label className="flex-1 text-sm text-gray-600 dark:text-gray-300">Planning year
            <input type="number" min="1" value={year} onChange={(event) => setYear(event.target.value)} className="mt-2 h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
          </label>
          <label className="flex-1 text-sm text-gray-600 dark:text-gray-300">Cost center ID (optional)
            <input type="number" min="1" value={costCenter} onChange={(event) => setCostCenter(event.target.value)} className="mt-2 h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
          </label>
          <label className="text-sm text-gray-600 dark:text-gray-300">Rows
            <select value={query.per_page ?? 15} onChange={(event) => setQuery((current) => ({ ...current, page: 1, per_page: Number(event.target.value) }))} className="mt-2 h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
              {[15, 25, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
            </select>
          </label>
          <button type="submit" className="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">Apply filters</button>
        </div>
        <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">Filters use the documented year, cost_center_id, page, and per_page parameters.</p>
      </form>

      {response && <Summary response={response} />}
      {error && <div className="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">{error.correlationId ? `${error.message} Correlation ID: ${error.correlationId}` : error.message}</div>}
      {!error && !response && tenantId !== null && canView && <div className="rounded-xl border border-gray-200 bg-white px-5 py-6 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03]">Loading report lines…</div>}
      {response && response.data.length === 0 && <div className="rounded-xl border border-gray-200 bg-white px-5 py-6 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03]">No report lines match these filters.</div>}
      {response && response.data.length > 0 && (
        <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="mb-4 flex items-center justify-between gap-3"><h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">Economic report</h3><Badge color="info" size="sm">Authoritative API lines</Badge></div>
          <div className="max-w-full overflow-x-auto">
            <Table>
              <TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow>
                {(["Date", "Cost center", "Kind / type", "Confirmation", "Net", "VAT", "Gross"] as const).map((header) => <TableCell key={header} isHeader className="whitespace-nowrap py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">{header}</TableCell>)}
              </TableRow></TableHeader>
              <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
                {response.data.map((line) => <TableRow key={line.id}>
                  <TableCell className="whitespace-nowrap py-3 text-sm text-gray-600 dark:text-gray-300">{line.spend_date ?? line.period_start ?? "—"}</TableCell>
                  <TableCell className="py-3 text-sm text-gray-800 dark:text-white/90">{line.cost_center_name ?? `#${line.cost_center_id}`}</TableCell>
                  <TableCell className="py-3 text-sm capitalize text-gray-600 dark:text-gray-300">{line.expense_kind} / {line.type}{line.is_extra ? " · extra" : ""}</TableCell>
                  <TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{line.confirmation_state ?? "—"}</TableCell>
                  <TableCell className="whitespace-nowrap py-3 text-end text-sm text-gray-600 dark:text-gray-300">{money(line.net, line.currency)}</TableCell>
                  <TableCell className="whitespace-nowrap py-3 text-end text-sm text-gray-600 dark:text-gray-300">{money(line.vat, line.currency)}</TableCell>
                  <TableCell className="whitespace-nowrap py-3 text-end text-sm font-medium text-gray-800 dark:text-white/90">{money(line.gross, line.currency)}</TableCell>
                </TableRow>)}
              </TableBody>
            </Table>
          </div>
          <div className="mt-5"><ReportsPagination meta={response.meta} onPage={(page) => setQuery((current) => ({ ...current, page }))} /></div>
        </section>
      )}
    </div>
  );
}
