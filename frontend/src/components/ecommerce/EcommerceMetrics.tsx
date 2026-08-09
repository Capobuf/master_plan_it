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
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      {metrics.map((metric) => {
        const Icon = metric.icon;
        return (
          <article key={metric.label} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-start justify-between gap-3">
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                <Icon className="h-6 w-6 text-gray-800 dark:text-white/90" aria-hidden="true" />
              </span>
              {metric.badge ? <Badge color={metric.tone === "warning" ? "warning" : "info"} size="sm">{metric.badge}</Badge> : null}
            </div>
            <p className="mt-5 text-sm text-gray-500 dark:text-gray-400">{metric.label}</p>
            <p className="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{metric.value}</p>
          </article>
        );
      })}
    </div>
  );
}
