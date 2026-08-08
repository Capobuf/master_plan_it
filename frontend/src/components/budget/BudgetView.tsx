import type { ReportingDataset } from "../../api/dashboard";
import Badge from "../ui/badge/Badge";
import ComponentCard from "../common/ComponentCard";
import EcommerceMetrics from "../ecommerce/EcommerceMetrics";
import {
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
} from "../ui/table";

const money = (value: string, currency: string): string => {
  const numeric = Number(value);
  return Number.isFinite(numeric)
    ? new Intl.NumberFormat(undefined, { style: "currency", currency, minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(numeric)
    : value;
};

function EmptyState() {
  return <p className="py-5 text-sm text-gray-500 dark:text-gray-400">No data for this planning year.</p>;
}

export default function BudgetView({ dataset }: { dataset: ReportingDataset }) {
  const currency = dataset.summary?.currency ?? dataset.scope?.currency ?? "EUR";
  const amounts = dataset.summary?.amounts ?? {};
  const composition = Object.entries(dataset.by_type ?? {});
  const costCenters = Object.entries(dataset.by_cost_center ?? {}).sort(([, left], [, right]) => Number(right) - Number(left));

  return (
    <div className="space-y-6">
      {!dataset.has_economic_data && (
        <div className="rounded-xl border border-blue-light-200 bg-blue-light-50 px-4 py-3 text-sm text-blue-light-700 dark:border-blue-light-500/30 dark:bg-blue-light-500/10 dark:text-blue-light-300">
          No economic data is available for the selected planning year yet.
        </div>
      )}
      <EcommerceMetrics metrics={[
        { label: "Current budget", value: money(amounts.official_current_position ?? "0.00", currency) },
        { label: "Confirmed actual", value: money(amounts.actual_confirmed ?? "0.00", currency) },
        { label: "Net", value: money(amounts.net ?? "0.00", currency) },
        { label: "Gross", value: money(amounts.gross ?? "0.00", currency) },
      ]} />
      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <ComponentCard title="Estimate / Quote / Actual composition">
          {composition.length === 0 ? <EmptyState /> : (
            <div className="space-y-3">
              {composition.map(([type, value]) => (
                <div key={type} className="flex items-center justify-between gap-4 rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-800/50">
                  <span className="capitalize text-sm text-gray-600 dark:text-gray-300">{type}</span>
                  <span className="font-medium text-gray-800 dark:text-white/90">{money(value, currency)}</span>
                </div>
              ))}
            </div>
          )}
        </ComponentCard>
        <ComponentCard title="Breakdown">
          <div className="max-w-full overflow-x-auto">
            <Table>
              <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
                {(["net", "vat", "gross", "extra"] as const).map((key) => (
                  <TableRow key={key}>
                    <TableCell className="py-3 text-sm capitalize text-gray-600 dark:text-gray-300">{key}</TableCell>
                    <TableCell className="py-3 text-end text-sm font-medium text-gray-800 dark:text-white/90">{money(amounts[key] ?? "0.00", currency)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </ComponentCard>
      </div>
      <ComponentCard title="Top cost centers">
        {costCenters.length === 0 ? <EmptyState /> : (
          <div className="max-w-full overflow-x-auto">
            <Table>
              <TableHeader className="border-y border-gray-100 dark:border-gray-800">
                <TableRow>
                  <TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Cost center</TableCell>
                  <TableCell isHeader className="py-3 text-end text-theme-xs font-medium text-gray-500 dark:text-gray-400">Official amount</TableCell>
                </TableRow>
              </TableHeader>
              <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
                {costCenters.slice(0, 10).map(([name, value]) => (
                  <TableRow key={name}>
                    <TableCell className="py-3 text-sm text-gray-800 dark:text-white/90">{name}</TableCell>
                    <TableCell className="py-3 text-end text-sm text-gray-500 dark:text-gray-400">{money(value, currency)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </ComponentCard>
      <div className="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/50">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-sm font-medium text-gray-800 dark:text-white/90">Plafond status</p>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Secondary context reported by the economic engine.</p>
          </div>
          <div className="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
            <span>Residual: {money(amounts.plafond_residual ?? "0.00", currency)}</span>
            {(Number(amounts.plafond_overrun ?? "0") > 0) && <Badge color="warning" size="sm">Overrun reported</Badge>}
          </div>
        </div>
      </div>
    </div>
  );
}
