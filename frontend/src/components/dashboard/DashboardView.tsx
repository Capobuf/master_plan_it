import Chart from "react-apexcharts";
import type { ApexOptions } from "apexcharts";
import type { DashboardListItem, ReportingDataset } from "../../api/dashboard";
import Badge from "../ui/badge/Badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
} from "../ui/table";

const amount = (dataset: ReportingDataset, key: string): string =>
  dataset.summary?.amounts?.[key] ?? "0.00";

const money = (value: string, currency = "EUR"): string => {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return value;
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numeric);
};

function EmptyState({ message = "No data for this view." }: { message?: string }) {
  return <p className="py-6 text-sm text-gray-500 dark:text-gray-400">{message}</p>;
}

function Panel({
  title,
  children,
  className = "",
}: {
  title: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <section
      className={`rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] ${className}`}
    >
      <h3 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">
        {title}
      </h3>
      {children}
    </section>
  );
}

function SummaryCard({
  label,
  value,
  currency,
  tone = "default",
}: {
  label: string;
  value: string;
  currency: string;
  tone?: "default" | "warning";
}) {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <p className="text-sm text-gray-500 dark:text-gray-400">{label}</p>
      <p
        className={`mt-2 text-title-sm font-bold ${tone === "warning" ? "text-warning-600 dark:text-warning-500" : "text-gray-800 dark:text-white/90"}`}
      >
        {money(value, currency)}
      </p>
    </div>
  );
}

function MonthlyTrend({ dataset }: { dataset: ReportingDataset }) {
  const months = Object.entries(dataset.monthly ?? {});
  if (months.length === 0) return <Panel title="Monthly trend"><EmptyState /></Panel>;

  const options: ApexOptions = {
    chart: { toolbar: { show: false }, fontFamily: "Outfit, sans-serif" },
    colors: ["#465FFF"],
    dataLabels: { enabled: false },
    stroke: { curve: "smooth", width: 2 },
    xaxis: { categories: months.map(([key]) => key.slice(5)) },
    yaxis: { labels: { formatter: (value) => money(String(value), dataset.summary?.currency ?? "EUR") } },
    tooltip: { y: { formatter: (value) => money(String(value), dataset.summary?.currency ?? "EUR") } },
    grid: { yaxis: { lines: { show: true } } },
  };

  return (
    <Panel title="Monthly trend">
      <div className="max-w-full overflow-x-auto">
        <div className="min-w-[650px]">
          <Chart
            options={options}
            series={[{ name: "Official position", data: months.map(([, value]) => Number(value)) }]}
            type="area"
            height={280}
          />
        </div>
      </div>
    </Panel>
  );
}

function ListPanel({ title, items, emptyMessage }: { title: string; items: DashboardListItem[]; emptyMessage: string }) {
  return (
    <Panel title={title}>
      {items.length === 0 ? (
        <EmptyState message={emptyMessage} />
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {items.map((item) => (
            <li key={`${item.id}-${item.date ?? item.state ?? "item"}`} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
              <div className="min-w-0">
                <p className="truncate text-sm font-medium text-gray-800 dark:text-white/90">{item.label}</p>
                {item.date && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{item.date}</p>}
              </div>
              {item.event_type && <Badge color="info" size="sm">{item.event_type.replace("_", " ")}</Badge>}
              {item.state && <Badge color="warning" size="sm">{item.state.replace("_", " ")}</Badge>}
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

function CostCenters({ dataset }: { dataset: ReportingDataset }) {
  const currency = dataset.summary?.currency ?? "EUR";
  const rows = Object.entries(dataset.by_cost_center ?? {})
    .sort(([, left], [, right]) => Number(right) - Number(left))
    .slice(0, 8);
  return (
    <Panel title="Top cost centers">
      {rows.length === 0 ? <EmptyState /> : (
        <ul className="space-y-3">
          {rows.map(([name, value]) => (
            <li key={name} className="flex items-center justify-between gap-4 text-sm">
              <span className="truncate text-gray-600 dark:text-gray-300">{name}</span>
              <span className="font-medium text-gray-800 dark:text-white/90">{money(value, currency)}</span>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

function RecentExpenses({ items }: { items: DashboardListItem[] }) {
  return (
    <Panel title="Recent expenses">
      {items.length === 0 ? <EmptyState message="No recent expenses." /> : (
        <div className="max-w-full overflow-x-auto">
          <Table>
            <TableHeader className="border-y border-gray-100 dark:border-gray-800">
              <TableRow>
                <TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Expense</TableCell>
                <TableCell isHeader className="py-3 text-end text-theme-xs font-medium text-gray-500 dark:text-gray-400">Updated</TableCell>
              </TableRow>
            </TableHeader>
            <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
              {items.map((item) => (
                <TableRow key={`${item.id}-${item.date}`}>
                  <TableCell className="py-3 text-sm text-gray-800 dark:text-white/90">{item.label}</TableCell>
                  <TableCell className="py-3 text-end text-sm text-gray-500 dark:text-gray-400">{item.date ?? "—"}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </Panel>
  );
}

export default function DashboardView({ dataset }: { dataset: ReportingDataset }) {
  const currency = dataset.summary?.currency ?? dataset.scope?.currency ?? "EUR";
  const ancillary = dataset.ancillary ?? {};
  const overrun = amount(dataset, "plafond_overrun");

  return (
    <div className="space-y-6">
      {!dataset.has_economic_data && (
        <div className="rounded-xl border border-blue-light-200 bg-blue-light-50 px-4 py-3 text-sm text-blue-light-700 dark:border-blue-light-500/30 dark:bg-blue-light-500/10 dark:text-blue-light-300">
          No economic data is available for the selected planning year yet.
        </div>
      )}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard label="Current budget" value={amount(dataset, "official_current_position")} currency={currency} />
        <SummaryCard label="Confirmed actual" value={amount(dataset, "actual_confirmed")} currency={currency} />
        <SummaryCard label="Actuals to confirm" value={amount(dataset, "actual_to_confirm")} currency={currency} tone="warning" />
        <SummaryCard label="Plafond residual" value={amount(dataset, "plafond_residual")} currency={currency} />
      </div>
      {Number(overrun) > 0 && (
        <div className="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
          The API reports a Plafond overrun of {money(overrun, currency)}. Review the source expenses.
        </div>
      )}
      <MonthlyTrend dataset={dataset} />
      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <ListPanel title="Actuals to confirm" items={ancillary.generatedExpensesToConfirm ?? []} emptyMessage="No generated actuals need confirmation." />
        <ListPanel title="Renewals and deadlines" items={ancillary.upcomingContractEvents ?? []} emptyMessage="No renewals or contract deadlines in the next year." />
        <CostCenters dataset={dataset} />
        <ListPanel title="Active contracts" items={ancillary.activeContracts ?? []} emptyMessage="No active contracts." />
      </div>
      <RecentExpenses items={ancillary.recentExpenses ?? []} />
    </div>
  );
}
