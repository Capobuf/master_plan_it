import type { ComponentType, SVGProps } from "react";
import Badge from "../ui/badge/Badge";

export interface EcommerceMetric {
  label: string;
  value: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  badge?: string;
  tone?: "default" | "warning";
}

export default function EcommerceMetrics({ metrics }: { metrics: EcommerceMetric[] }) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
      {metrics.map((metric) => {
        const Icon = metric.icon;
        return (
          <article key={metric.label} className="rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03] sm:p-5">
            <div className="flex items-start justify-between gap-3">
              <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800 sm:h-11 sm:w-11 sm:rounded-xl">
                <Icon className="h-5 w-5 text-gray-800 dark:text-white/90 sm:h-6 sm:w-6" aria-hidden="true" />
              </span>
              {metric.badge ? <Badge color={metric.tone === "warning" ? "warning" : "info"} size="sm">{metric.badge}</Badge> : null}
            </div>
            <p className="mt-3 text-xs leading-4 text-gray-500 dark:text-gray-400 sm:mt-5 sm:text-sm sm:leading-5">{metric.label}</p>
            <p className="mt-1 text-lg font-bold leading-6 text-gray-800 dark:text-white/90 sm:mt-2 sm:text-2xl sm:leading-8">{metric.value}</p>
          </article>
        );
      })}
    </div>
  );
}
