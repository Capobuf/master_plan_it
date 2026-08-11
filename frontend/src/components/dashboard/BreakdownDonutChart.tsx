import type { ApexOptions } from "apexcharts";
import { useMemo } from "react";
import Chart from "react-apexcharts";
import ComponentCard from "../common/ComponentCard";

export interface BreakdownDonutEntry {
  label: string;
  value: number;
  displayValue: string;
  color?: string;
}

interface BreakdownDonutChartProps {
  title: string;
  entries: BreakdownDonutEntry[];
  emptyMessage: string;
  centerLabel?: string;
  centerValue?: string;
  chartClassName?: string;
  totalLabel?: string;
  totalValue?: string;
}

const palette = ["#465FFF", "#0BA5EC", "#12B76A", "#7A5AF8", "#FDB022", "#EE46BC"];

export default function BreakdownDonutChart({
  title,
  entries,
  emptyMessage,
  centerLabel,
  centerValue,
  chartClassName = "h-[170px] sm:h-[190px]",
  totalLabel,
  totalValue,
}: BreakdownDonutChartProps) {
  const series = useMemo(() => entries.map((entry) => Math.abs(entry.value)), [entries]);
  const hasValues = series.some((value) => value > 0);
  const options = useMemo<ApexOptions>(() => ({
    chart: { fontFamily: "Outfit, sans-serif", toolbar: { show: false } },
    labels: entries.map((entry) => entry.label),
    colors: entries.map((entry, index) => entry.color ?? palette[index % palette.length]),
    dataLabels: { enabled: false },
    legend: { show: false },
    stroke: { width: 0 },
    plotOptions: {
      pie: {
        donut: {
          size: "70%",
          labels: {
            show: Boolean(centerLabel || centerValue),
            name: { show: Boolean(centerLabel), formatter: () => centerLabel ?? "" },
            value: { show: Boolean(centerValue), formatter: () => centerValue ?? "" },
            total: { show: false },
          },
        },
      },
    },
    tooltip: {
      y: {
        formatter: (_value, context) => entries[context.seriesIndex]?.displayValue ?? "",
      },
    },
  }), [centerLabel, centerValue, entries]);

  return (
    <ComponentCard title={title} compact>
      {!hasValues ? (
        <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
          {emptyMessage}
        </p>
      ) : (
        <>
          <div className={`mx-auto w-full max-w-[260px] ${chartClassName}`} aria-label={title}>
            <Chart options={options} series={series} type="donut" height="100%" width="100%" />
          </div>
          <ul className="space-y-1">
            {entries.map((entry, index) => (
              <li key={entry.label} className="flex items-center justify-between gap-3 text-xs">
                <span className="flex min-w-0 items-center gap-2 text-gray-600 dark:text-gray-300">
                  <span
                    className="h-2.5 w-2.5 shrink-0 rounded-full"
                    style={{ backgroundColor: entry.color ?? palette[index % palette.length] }}
                    aria-hidden="true"
                  />
                  <span className="truncate">{entry.label}</span>
                </span>
                <span className="shrink-0 font-medium text-gray-800 dark:text-white/90">{entry.displayValue}</span>
              </li>
            ))}
          </ul>
          {totalValue ? (
            <div className="flex items-center justify-between gap-4 border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
              <span className="font-medium text-gray-600 dark:text-gray-300">{totalLabel ?? "Totale"}</span>
              <span className="shrink-0 font-semibold text-gray-800 dark:text-white/90">{totalValue}</span>
            </div>
          ) : null}
        </>
      )}
    </ComponentCard>
  );
}
