import {
  ArrowDownIcon,
  ArrowUpIcon,
  BoxIconLine,
  GroupIcon,
} from "../../icons";
import Badge from "../ui/badge/Badge";

export interface EcommerceMetric {
  label: string;
  value: string;
  badge?: string;
  tone?: "default" | "warning";
}

interface EcommerceMetricsProps {
  metrics?: EcommerceMetric[];
}

const defaultMetrics: EcommerceMetric[] = [
  { label: "Customers", value: "3,782", badge: "11.01%" },
  { label: "Orders", value: "5,359", badge: "9.05%", tone: "warning" },
];

export default function EcommerceMetrics({ metrics = defaultMetrics }: EcommerceMetricsProps) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 md:gap-6 xl:grid-cols-4">
      {metrics.map((metric, index) => (
        <div key={metric.label} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
          <div className="flex items-center justify-center w-12 h-12 bg-gray-100 rounded-xl dark:bg-gray-800">
            {index % 2 === 0 ? <GroupIcon className="text-gray-800 size-6 dark:text-white/90" /> : <BoxIconLine className="text-gray-800 size-6 dark:text-white/90" />}
          </div>
          <div className="flex items-end justify-between mt-5 gap-3">
            <div>
              <span className="text-sm text-gray-500 dark:text-gray-400">{metric.label}</span>
              <h4 className="mt-2 font-bold text-gray-800 text-title-sm dark:text-white/90">{metric.value}</h4>
            </div>
            {metric.badge && <Badge color={metric.tone === "warning" ? "warning" : "success"}>
              {metric.tone === "warning" ? <ArrowDownIcon /> : <ArrowUpIcon />}
              {metric.badge}
            </Badge>}
          </div>
        </div>
      ))}
    </div>
  );
}
