import type { ReportingDataset } from "../../api/dashboard";
import { CheckCircleIcon, DollarLineIcon, ListIcon, PieChartIcon } from "../../icons";
import { compareDecimalStrings, formatMoney, isPositiveDecimal, toChartNumber } from "../../presentation/formatters";
import ComponentCard from "../common/ComponentCard";
import EcommerceMetrics, { type EcommerceMetric } from "../ecommerce/EcommerceMetrics";
import RecentOrders from "../ecommerce/RecentOrders";
import StatisticsChart from "../ecommerce/StatisticsChart";
import Alert from "../ui/alert/Alert";
import BreakdownDonutChart from "./BreakdownDonutChart";
import RenewalTimeline from "./RenewalTimeline";

const monthLabels: Record<string, string> = {
  "01": "Gen", "02": "Feb", "03": "Mar", "04": "Apr", "05": "Mag", "06": "Giu",
  "07": "Lug", "08": "Ago", "09": "Set", "10": "Ott", "11": "Nov", "12": "Dic",
};

const amount = (dataset: ReportingDataset, key: string): string => dataset.summary?.amounts?.[key] ?? "0.00";

function EmptyState({ message }: { message: string }) {
  return <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">{message}</p>;
}

export default function DashboardView({ dataset }: { dataset: ReportingDataset }) {
  const currency = dataset.summary?.currency ?? dataset.scope?.currency ?? "EUR";
  const amounts = dataset.summary?.amounts ?? {};
  const ancillary = dataset.ancillary ?? {};
  const expenseCounts = ancillary.expenseCounts ?? { total: 0, open: 0, closed: 0 };
  const monthly = Object.entries(dataset.monthly ?? {}).sort(([left], [right]) => left.localeCompare(right));
  const costCenters = Object.entries(dataset.by_cost_center ?? {})
    .sort(([, left], [, right]) => compareDecimalStrings(right, left))
    .slice(0, 6);
  const projects = Object.entries(dataset.by_project ?? {})
    .sort(([, left], [, right]) => compareDecimalStrings(right, left))
    .slice(0, 5);
  const projectChartHeight = Math.min(220, Math.max(105, projects.length * 34 + 66));
  const projectMobileHeight = Math.min(200, Math.max(105, projects.length * 32 + 68));
  const metrics: EcommerceMetric[] = [
    { label: "Posizione Economica", value: formatMoney(amount(dataset, "official_current_position"), currency), icon: DollarLineIcon },
    { label: "Pianificato", value: formatMoney(amount(dataset, "planned"), currency), icon: PieChartIcon },
    { label: "Actual", value: formatMoney(amount(dataset, "actual"), currency), icon: CheckCircleIcon },
    { label: "Spese Aperte", value: String(expenseCounts.open), icon: ListIcon },
  ];

  return (
    <div className="space-y-3 overflow-x-clip sm:space-y-4 xl:space-y-6">
      {!dataset.has_economic_data ? <Alert variant="info" title="Nessun dato economico" message="Non sono ancora disponibili dati per l'anno di pianificazione selezionato." /> : null}
      <EcommerceMetrics metrics={metrics} />
      {isPositiveDecimal(amounts.plafond_overrun) ? <Alert variant="warning" title="Superamento Plafond" message={`Il Plafond risulta superato di ${formatMoney(amounts.plafond_overrun, currency)}.`} /> : null}

      <div className="grid grid-cols-1 items-start gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-12 xl:gap-6">
        <div className="xl:col-span-3">
          <BreakdownDonutChart
            title="Spese per Centro di Costo"
            entries={costCenters.map(([label, value]) => ({ label, value: toChartNumber(value), displayValue: formatMoney(value, currency) }))}
            emptyMessage="Nessun centro di costo valorizzato."
            chartClassName="h-[165px]"
            totalLabel="Totale"
            totalValue={formatMoney(amount(dataset, "official_current_position"), currency)}
          />
        </div>
        <div className="md:col-span-2 xl:col-span-6">
          {monthly.length > 0 ? (
            <StatisticsChart
              title="Andamento Mensile"
              categories={monthly.map(([key]) => monthLabels[key.slice(-2)] ?? key)}
              values={monthly.map(([, value]) => toChartNumber(value))}
              currency={currency}
              seriesName="Posizione mensile"
              height={340}
              mobileHeight={230}
            />
          ) : (
            <ComponentCard title="Andamento Mensile" compact><EmptyState message="Nessun andamento mensile disponibile." /></ComponentCard>
          )}
        </div>
        <div className="grid gap-3 sm:gap-4 md:col-span-2 md:grid-cols-2 xl:col-span-3 xl:grid-cols-1">
          {projects.length > 0 ? (
            <StatisticsChart
              title="Spese per Progetto"
              categories={projects.map(([label]) => label)}
              values={projects.map(([, value]) => toChartNumber(value))}
              currency={currency}
              type="bar"
              horizontal
              seriesName="Importo"
              height={projectChartHeight}
              mobileHeight={projectMobileHeight}
            />
          ) : (
            <ComponentCard title="Spese per Progetto" compact><EmptyState message="Nessun progetto valorizzato." /></ComponentCard>
          )}
          <BreakdownDonutChart
            title="Stato Spese"
            entries={[
              { label: "Aperte", value: expenseCounts.open, displayValue: String(expenseCounts.open), color: "#0BA5EC" },
              { label: "Chiuse", value: expenseCounts.closed, displayValue: String(expenseCounts.closed), color: "#12B76A" },
            ]}
            emptyMessage="Nessuna Spesa presente nell'anno selezionato."
            centerLabel="Totale"
            centerValue={String(expenseCounts.total)}
            chartClassName="h-[150px] sm:h-[120px]"
          />
        </div>
      </div>

      <div className="grid grid-cols-1 items-start gap-3 sm:gap-4 xl:grid-cols-12 xl:gap-6">
        <div className="xl:col-span-9">
          {(ancillary.recentExpenses ?? []).length > 0
            ? <RecentOrders items={ancillary.recentExpenses ?? []} currency={currency} />
            : <ComponentCard title="Ultime Spese" compact><EmptyState message="Nessuna spesa recente." /></ComponentCard>}
        </div>
        <div className="xl:col-span-3">
          <RenewalTimeline items={ancillary.upcomingContractEvents ?? []} />
        </div>
      </div>
    </div>
  );
}
