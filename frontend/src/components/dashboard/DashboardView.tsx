import type { DashboardListItem, ReportingDataset } from "../../api/dashboard";
import Badge from "../ui/badge/Badge";
import ComponentCard from "../common/ComponentCard";
import EcommerceMetrics from "../ecommerce/EcommerceMetrics";
import StatisticsChart from "../ecommerce/StatisticsChart";
import RecentOrders, { type RecentOrderItem } from "../ecommerce/RecentOrders";

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

function MonthlyTrend({ dataset }: { dataset: ReportingDataset }) {
  const months = Object.entries(dataset.monthly ?? {});
  if (months.length === 0) return <ComponentCard title="Monthly trend"><EmptyState /></ComponentCard>;

  return (
    <StatisticsChart
      title="Monthly trend"
      categories={months.map(([key]) => key.slice(5))}
      values={months.map(([, value]) => Number(value))}
      currency={dataset.summary?.currency ?? "EUR"}
    />
  );
}

function ListPanel({ title, items, emptyMessage }: { title: string; items: DashboardListItem[]; emptyMessage: string }) {
  return (
    <ComponentCard title={title}>
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
    </ComponentCard>
  );
}

function CostCenters({ dataset }: { dataset: ReportingDataset }) {
  const currency = dataset.summary?.currency ?? "EUR";
  const rows = Object.entries(dataset.by_cost_center ?? {})
    .sort(([, left], [, right]) => Number(right) - Number(left))
    .slice(0, 8);
  return (
    <ComponentCard title="Top cost centers">
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
    </ComponentCard>
  );
}

function RecentExpenses({ items }: { items: DashboardListItem[] }) {
  const recentItems: RecentOrderItem[] = items.map((item) => ({ id: item.id, label: item.label, date: item.date }));
  return items.length === 0
    ? <ComponentCard title="Recent expenses"><EmptyState message="No recent expenses." /></ComponentCard>
    : <RecentOrders title="Recent expenses" items={recentItems} />;
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
      <EcommerceMetrics metrics={[
        { label: "Current budget", value: money(amount(dataset, "official_current_position"), currency) },
        { label: "Confirmed actual", value: money(amount(dataset, "actual_confirmed"), currency) },
        { label: "Actuals to confirm", value: money(amount(dataset, "actual_to_confirm"), currency), tone: "warning", badge: "Review" },
      ]} />
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
