import type { ReportingDataset } from "../../api/dashboard";
import { AlertHexaIcon, CheckCircleIcon, DollarLineIcon, TimeIcon } from "../../icons";
import { compareDecimalStrings, formatMoney, isPositiveDecimal, toChartNumber } from "../../presentation/formatters";
import { domainLabel } from "../../presentation/labels";
import ComponentCard from "../common/ComponentCard";
import EcommerceMetrics from "../ecommerce/EcommerceMetrics";
import StatisticsChart from "../ecommerce/StatisticsChart";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

function EmptyState({ message }: { message: string }) {
  return <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">{message}</p>;
}

export default function BudgetView({ dataset }: { dataset: ReportingDataset }) {
  const currency = dataset.summary?.currency ?? dataset.scope?.currency ?? "EUR";
  const amounts = dataset.summary?.amounts ?? {};
  const composition = Object.entries(dataset.by_type ?? {});
  const costCenters = Object.entries(dataset.by_cost_center ?? {}).sort(([, left], [, right]) => compareDecimalStrings(right, left));
  const officialBasis = dataset.summary?.official_basis ?? dataset.scope?.official_basis;

  return <div className="space-y-6">
    {!dataset.has_economic_data ? <Alert variant="info" title="Nessun dato economico" message="Non sono ancora disponibili dati per l'anno di pianificazione selezionato." /> : null}
    {officialBasis ? <div className="flex justify-end"><Badge color="info" size="sm">Base Ufficiale: {domainLabel(officialBasis)}</Badge></div> : null}
    <EcommerceMetrics metrics={[
      { label: "Budget Corrente", value: formatMoney(amounts.official_current_position ?? "0.00", currency), icon: DollarLineIcon },
      { label: "Consuntivi Confermati", value: formatMoney(amounts.actual_confirmed ?? "0.00", currency), icon: CheckCircleIcon },
      { label: "Consuntivi da Confermare", value: formatMoney(amounts.actual_to_confirm ?? "0.00", currency), icon: TimeIcon },
      { label: "Lordo", value: formatMoney(amounts.gross ?? "0.00", currency), icon: AlertHexaIcon },
    ]} />
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
      <div className="lg:col-span-7">
        {composition.length ? <StatisticsChart title="Composizione per Stime, Preventivi e Consuntivi" categories={composition.map(([type]) => domainLabel(type))} values={composition.map(([, value]) => toChartNumber(value))} currency={currency} type="bar" seriesName="Importo" /> : <ComponentCard title="Composizione per Stime, Preventivi e Consuntivi"><EmptyState message="Nessuna composizione disponibile." /></ComponentCard>}
      </div>
      <ComponentCard title="Riepilogo Economico" className="lg:col-span-5">
        <dl className="divide-y divide-gray-100 dark:divide-gray-800">
          {([['Netto', 'net'], ['IVA', 'vat'], ['Lordo', 'gross'], ['Extra', 'extra']] as const).map(([label, key]) => <div key={key} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"><dt className="text-sm text-gray-600 dark:text-gray-300">{label}</dt><dd className="text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(amounts[key] ?? "0.00", currency)}</dd></div>)}
        </dl>
      </ComponentCard>
    </div>
    <ComponentCard title="Centri di Costo Principali">
      {costCenters.length === 0 ? <EmptyState message="Nessun centro di costo valorizzato." /> : <div className="max-w-full overflow-x-auto"><Table>
        <TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow><TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Centro di costo</TableCell><TableCell isHeader className="py-3 text-end text-theme-xs font-medium text-gray-500 dark:text-gray-400">Importo ufficiale</TableCell></TableRow></TableHeader>
        <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{costCenters.slice(0, 10).map(([name, value]) => <TableRow key={name}><TableCell className="py-3 text-sm font-medium text-gray-800 dark:text-white/90">{name}</TableCell><TableCell className="py-3 text-end text-sm text-gray-600 dark:text-gray-300">{formatMoney(value, currency)}</TableCell></TableRow>)}</TableBody>
      </Table></div>}
    </ComponentCard>
    <ComponentCard title="Stato Plafond">
      <div className="flex flex-wrap items-center justify-between gap-4"><div><p className="text-sm text-gray-600 dark:text-gray-300">Residuo disponibile</p><p className="mt-1 font-semibold text-gray-800 dark:text-white/90">{formatMoney(amounts.plafond_residual ?? "0.00", currency)}</p></div>{isPositiveDecimal(amounts.plafond_overrun) ? <Badge color="warning">Superamento: {formatMoney(amounts.plafond_overrun, currency)}</Badge> : <Badge color="success">Nessun superamento</Badge>}</div>
    </ComponentCard>
  </div>;
}
