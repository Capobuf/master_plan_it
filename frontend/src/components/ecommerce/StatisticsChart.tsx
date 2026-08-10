import type { ApexOptions } from "apexcharts";
import Chart from "react-apexcharts";
import { type CSSProperties, useMemo } from "react";
import { formatMoney } from "../../presentation/formatters";

const formatCompactMoney = (value: string, currency: string): string => new Intl.NumberFormat("it-IT", {
  style: "currency",
  currency,
  notation: "compact",
  maximumFractionDigits: 1,
}).format(Number(value));

interface StatisticsChartProps {
  title: string;
  description?: string;
  categories: string[];
  values: number[];
  currency?: string;
  type?: "area" | "bar";
  seriesName?: string;
  horizontal?: boolean;
  height?: number;
  mobileHeight?: number;
}

export default function StatisticsChart({
  title,
  description,
  categories,
  values,
  currency = "EUR",
  type = "area",
  seriesName = "Importo",
  horizontal = false,
  height = 310,
  mobileHeight = 230,
}: StatisticsChartProps) {
  const options = useMemo<ApexOptions>(() => ({
    colors: ["#465FFF"],
    chart: { fontFamily: "Outfit, sans-serif", toolbar: { show: false }, animations: { enabled: true } },
    dataLabels: { enabled: false },
    legend: { show: false },
    stroke: type === "area" ? { curve: "smooth", width: 2 } : { width: 0 },
    fill: type === "area" ? { type: "gradient", gradient: { opacityFrom: 0.45, opacityTo: 0.05 } } : { opacity: 1 },
    grid: { xaxis: { lines: { show: false } }, yaxis: { lines: { show: true } } },
    ...(type === "bar"
      ? { plotOptions: { bar: { borderRadius: 4, columnWidth: "48%", barHeight: "55%", horizontal } } }
      : {}),
    tooltip: { y: { formatter: (value) => formatMoney(String(value), currency) } },
    xaxis: {
      categories,
      axisBorder: { show: false },
      axisTicks: { show: false },
      tickAmount: horizontal ? 3 : undefined,
      labels: horizontal
        ? { formatter: (value) => formatCompactMoney(String(value), currency), style: { fontSize: "11px", colors: "#6B7280" } }
        : { style: { fontSize: "12px", colors: "#6B7280" } },
    },
    yaxis: {
      labels: horizontal
        ? { maxWidth: 145, style: { fontSize: "12px", colors: ["#6B7280"] } }
        : { formatter: (value) => formatMoney(String(value), currency), style: { fontSize: "12px", colors: ["#6B7280"] } },
    },
    responsive: [{ breakpoint: 640, options: { xaxis: { tickAmount: horizontal ? 3 : 6, labels: { rotate: 0, hideOverlappingLabels: true } } } }],
  }), [categories, currency, horizontal, type]);
  const series = useMemo(() => [{ name: seriesName, data: values }], [seriesName, values]);
  const chartHeights = {
    "--chart-height": `${height}px`,
    "--chart-mobile-height": `${mobileHeight}px`,
  } as CSSProperties;

  return (
    <section className="rounded-2xl border border-gray-200 bg-white px-4 pb-3 pt-4 dark:border-gray-800 dark:bg-white/[0.03] sm:px-5 sm:pb-4 sm:pt-5">
      <h2 className="text-base font-semibold text-gray-800 dark:text-white/90 sm:text-lg">{title}</h2>
      {description ? <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p> : null}
      <div className="mt-3 h-(--chart-mobile-height) w-full sm:mt-4 sm:h-(--chart-height)" style={chartHeights} aria-label={title}>
        <Chart options={options} series={series} type={type} height="100%" width="100%" />
      </div>
    </section>
  );
}
