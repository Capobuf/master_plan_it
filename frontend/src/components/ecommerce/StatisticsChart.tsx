import type { ApexOptions } from "apexcharts";
import Chart from "react-apexcharts";
import { useMemo } from "react";
import { formatMoney } from "../../presentation/formatters";

interface StatisticsChartProps {
  title: string;
  description?: string;
  categories: string[];
  values: number[];
  currency?: string;
  type?: "area" | "bar";
  seriesName?: string;
}

export default function StatisticsChart({
  title,
  description,
  categories,
  values,
  currency = "EUR",
  type = "area",
  seriesName = "Importo",
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
      ? { plotOptions: { bar: { borderRadius: 4, columnWidth: "48%" } } }
      : {}),
    tooltip: { y: { formatter: (value) => formatMoney(String(value), currency) } },
    xaxis: { categories, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { fontSize: "12px", colors: "#6B7280" } } },
    yaxis: { labels: { formatter: (value) => formatMoney(String(value), currency), style: { fontSize: "12px", colors: ["#6B7280"] } } },
    responsive: [{ breakpoint: 640, options: { chart: { height: 280 }, xaxis: { labels: { rotate: -35 } } } }],
  }), [categories, currency, type]);
  const series = useMemo(() => [{ name: seriesName, data: values }], [seriesName, values]);

  return (
    <section className="rounded-2xl border border-gray-200 bg-white px-4 pb-4 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pb-6">
      <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">{title}</h2>
      {description ? <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p> : null}
      <div className="mt-5 min-h-[310px] w-full" aria-label={title}>
        <Chart options={options} series={series} type={type} height={310} width="100%" />
      </div>
    </section>
  );
}
