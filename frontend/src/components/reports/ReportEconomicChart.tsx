import type { ApexOptions } from "apexcharts";
import { useMemo } from "react";
import Chart from "react-apexcharts";
import { formatMoney } from "../../presentation/formatters";

interface ReportEconomicChartProps {
  title: string;
  categories: string[];
  proposed: number[];
  approved: number[];
  actual: number[];
  currency: string;
}

const formatCompactMoney = (value: number, currency: string): string => new Intl.NumberFormat("it-IT", {
  style: "currency",
  currency,
  notation: "compact",
  maximumFractionDigits: 1,
}).format(value);

export default function ReportEconomicChart({ title, categories, proposed, approved, actual, currency }: ReportEconomicChartProps) {
  const options = useMemo<ApexOptions>(() => ({
    colors: ["#465FFF", "#7A5AF8", "#12B76A"],
    chart: { fontFamily: "Outfit, sans-serif", toolbar: { show: false }, animations: { enabled: true } },
    dataLabels: { enabled: false },
    legend: { show: true, position: "top", horizontalAlign: "right" },
    stroke: { width: 0 },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "58%" } },
    grid: { xaxis: { lines: { show: false } }, yaxis: { lines: { show: true } } },
    tooltip: { y: { formatter: (value) => formatMoney(String(value), currency) } },
    xaxis: {
      categories,
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: { rotate: -30, trim: true, hideOverlappingLabels: true, style: { fontSize: "11px", colors: "#6B7280" } },
    },
    yaxis: { labels: { formatter: (value) => formatCompactMoney(value, currency), style: { fontSize: "11px", colors: ["#6B7280"] } } },
    responsive: [{
      breakpoint: 640,
      options: {
        chart: { height: 330 },
        legend: { position: "bottom", horizontalAlign: "center" },
        plotOptions: { bar: { horizontal: true, barHeight: "64%" } },
        xaxis: { labels: { rotate: 0, formatter: (value: string) => formatCompactMoney(Number(value), currency) } },
        yaxis: { labels: { maxWidth: 110, style: { fontSize: "10px", colors: ["#6B7280"] } } },
      },
    }],
  }), [categories, currency]);
  const series = useMemo(() => [
    { name: "Proposto", data: proposed },
    { name: "Approvato", data: approved },
    { name: "Actual", data: actual },
  ], [actual, approved, proposed]);

  return (
    <section className="rounded-2xl border border-gray-200 bg-white px-4 pb-3 pt-4 dark:border-gray-800 dark:bg-white/[0.03] sm:px-5 sm:pb-4 sm:pt-5">
      <h2 className="text-base font-semibold text-gray-800 dark:text-white/90 sm:text-lg">{title}</h2>
      {categories.length === 0 ? (
        <p className="mt-4 rounded-xl border border-dashed border-gray-300 px-4 py-8 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nessun gruppo da rappresentare.</p>
      ) : (
        <div className="mt-3 h-[330px] w-full sm:mt-4 sm:h-[340px]" aria-label={title}>
          <Chart options={options} series={series} type="bar" height="100%" width="100%" />
        </div>
      )}
    </section>
  );
}
