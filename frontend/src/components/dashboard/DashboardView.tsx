import { Link } from "react-router";
import type { DashboardListItem, ReportingDataset } from "../../api/dashboard";
import { AlertHexaIcon, CheckCircleIcon, DollarLineIcon, TimeIcon } from "../../icons";
import { routes } from "../../navigation/routes";
import { compareDecimalStrings, formatDate, formatMoney, isPositiveDecimal, toChartNumber } from "../../presentation/formatters";
import { domainLabel } from "../../presentation/labels";
import ComponentCard from "../common/ComponentCard";
import EcommerceMetrics, { type EcommerceMetric } from "../ecommerce/EcommerceMetrics";
import RecentOrders from "../ecommerce/RecentOrders";
import StatisticsChart from "../ecommerce/StatisticsChart";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";

const monthLabels: Record<string, string> = {
  "01": "Gen", "02": "Feb", "03": "Mar", "04": "Apr", "05": "Mag", "06": "Giu",
  "07": "Lug", "08": "Ago", "09": "Set", "10": "Ott", "11": "Nov", "12": "Dic",
};

const amount = (dataset: ReportingDataset, key: string): string => dataset.summary?.amounts?.[key] ?? "0.00";

function EmptyState({ message }: { message: string }) {
  return <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">{message}</p>;
}

function ListPanel({ title, items, emptyMessage, href }: { title: string; items: DashboardListItem[]; emptyMessage: string; href?: (id: number) => string }) {
  return <ComponentCard title={title} className="h-full">
    {items.length === 0 ? <EmptyState message={emptyMessage} /> : <ul className="divide-y divide-gray-100 dark:divide-gray-800">
      {items.map((item, index) => <li key={`${item.id}-${index}`} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
        <div className="min-w-0">
          {href ? <Link to={href(item.id)} className="truncate text-sm font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">{item.label}</Link> : <p className="truncate text-sm font-medium text-gray-800 dark:text-white/90">{item.label}</p>}
          {item.date ? <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{formatDate(item.date)}</p> : null}
        </div>
        {item.event_type || item.state ? <Badge color={item.state === "active" ? "success" : "info"} size="sm">{domainLabel(item.event_type ?? item.state)}</Badge> : null}
      </li>)}
    </ul>}
  </ComponentCard>;
}

export default function DashboardView({ dataset }: { dataset: ReportingDataset }) {
  const currency = dataset.summary?.currency ?? dataset.scope?.currency ?? "EUR";
  const amounts = dataset.summary?.amounts ?? {};
  const ancillary = dataset.ancillary ?? {};
  const monthly = Object.entries(dataset.monthly ?? {}).sort(([left], [right]) => left.localeCompare(right));
  const composition = Object.entries(dataset.by_type ?? {});
  const costCenters = Object.entries(dataset.by_cost_center ?? {}).sort(([, left], [, right]) => compareDecimalStrings(right, left)).slice(0, 8);
  const metrics: EcommerceMetric[] = [
    { label: "Budget Corrente", value: formatMoney(amount(dataset, "official_current_position"), currency), icon: DollarLineIcon },
    { label: "Consuntivi Confermati", value: formatMoney(amount(dataset, "actual_confirmed"), currency), icon: CheckCircleIcon },
    { label: "Consuntivi da Confermare", value: formatMoney(amount(dataset, "actual_to_confirm"), currency), icon: TimeIcon, tone: "warning" },
  ];
  if (amounts.extra !== undefined) metrics.push({ label: "Spese Extra", value: formatMoney(amounts.extra, currency), icon: AlertHexaIcon });

  return <div className="space-y-6">
    {!dataset.has_economic_data ? <Alert variant="info" title="Nessun dato economico" message="Non sono ancora disponibili dati per l'anno di pianificazione selezionato." /> : null}
    <EcommerceMetrics metrics={metrics} />
    {isPositiveDecimal(amounts.plafond_overrun) ? <Alert variant="warning" title="Superamento Plafond" message={`Il Plafond risulta superato di ${formatMoney(amounts.plafond_overrun, currency)}.`} /> : null}
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
      <div className="lg:col-span-8">
        {monthly.length ? <StatisticsChart title="Andamento Mensile" description="Posizione economica mensile dell'anno selezionato." categories={monthly.map(([key]) => monthLabels[key.slice(-2)] ?? key)} values={monthly.map(([, value]) => toChartNumber(value))} currency={currency} seriesName="Posizione mensile" /> : <ComponentCard title="Andamento Mensile"><EmptyState message="Nessun andamento mensile disponibile." /></ComponentCard>}
      </div>
      <div className="lg:col-span-4">
        {composition.length ? <StatisticsChart title="Composizione per Stime, Preventivi e Consuntivi" categories={composition.map(([type]) => domainLabel(type))} values={composition.map(([, value]) => toChartNumber(value))} currency={currency} type="bar" seriesName="Composizione" /> : <ComponentCard title="Composizione per Stime, Preventivi e Consuntivi"><EmptyState message="Nessuna composizione disponibile." /></ComponentCard>}
      </div>
    </div>
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <ListPanel title="Rinnovi e Scadenze" items={ancillary.upcomingContractEvents ?? []} emptyMessage="Nessun rinnovo o scadenza nei prossimi dodici mesi." href={routes.contratto} />
      <ListPanel title="Contratti Attivi" items={ancillary.activeContracts ?? []} emptyMessage="Nessun contratto attivo." href={routes.contratto} />
      <ComponentCard title="Centri di Costo Principali" className="h-full">
        {costCenters.length === 0 ? <EmptyState message="Nessun centro di costo valorizzato." /> : <ul className="space-y-1">{costCenters.map(([name, value]) => <li key={name} className="flex items-center justify-between gap-4 rounded-lg px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-white/[0.03]"><span className="truncate text-sm text-gray-600 dark:text-gray-300">{name}</span><span className="whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(value, currency)}</span></li>)}</ul>}
      </ComponentCard>
      {(ancillary.generatedExpensesToConfirm ?? []).length > 0 ? <ListPanel title="Consuntivi da Confermare" items={ancillary.generatedExpensesToConfirm ?? []} emptyMessage="" href={routes.spesa} /> : <ListPanel title="Consuntivi da Confermare" items={[]} emptyMessage="Nessun consuntivo richiede conferma." />}
    </div>
    {(ancillary.recentExpenses ?? []).length ? <RecentOrders items={(ancillary.recentExpenses ?? []).map((item) => ({ id: item.id, label: item.label, date: item.date, href: routes.spesa(item.id) }))} /> : <ComponentCard title="Spese Recenti"><EmptyState message="Nessuna spesa recente." /></ComponentCard>}
  </div>;
}
