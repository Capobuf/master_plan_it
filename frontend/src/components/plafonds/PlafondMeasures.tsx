import type { PlafondMeasures as PlafondMeasuresData } from "../../api/projection";
import { formatMoney } from "../../presentation/formatters";

const labels: Array<[keyof PlafondMeasuresData, string]> = [
  ["allocation", "Allocazione"],
  ["coverage_planned", "Copertura prevista"],
  ["consumed", "Consumato"],
  ["available", "Disponibile"],
];

/** Presents the authoritative official component only; the API remains the economic engine. */
export default function PlafondMeasures({ measures, currency, compact = false }: {
  measures: PlafondMeasuresData;
  currency: string;
  compact?: boolean;
}) {
  return <dl className={`grid gap-3 ${compact ? "grid-cols-2" : "sm:grid-cols-2 xl:grid-cols-4"}`}>
    {labels.map(([key, label]) => <div key={key} className="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
      <dt className="text-xs text-gray-500 dark:text-gray-400">{label}</dt>
      <dd className="mt-1 text-base font-semibold text-gray-900 dark:text-white">{formatMoney(measures[key].official, currency)}</dd>
    </div>)}
  </dl>;
}
