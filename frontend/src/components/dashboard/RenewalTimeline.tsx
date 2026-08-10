import { Link } from "react-router";
import type { DashboardListItem } from "../../api/dashboard";
import { ArrowRightIcon } from "../../icons";
import { routes } from "../../navigation/routes";
import ComponentCard from "../common/ComponentCard";
import Badge from "../ui/badge/Badge";

const monthLabels = ["GEN", "FEB", "MAR", "APR", "MAG", "GIU", "LUG", "AGO", "SET", "OTT", "NOV", "DIC"];

function dateParts(value?: string) {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value ?? "");
  if (!match) return { day: "—", month: "", year: "" };

  return {
    day: match[3],
    month: monthLabels[Number(match[2]) - 1] ?? "",
    year: match[1],
  };
}

export default function RenewalTimeline({ items }: { items: DashboardListItem[] }) {
  const visibleItems = items.slice(0, 5);

  return (
    <ComponentCard title="Rinnovi e Scadenze" compact>
      {visibleItems.length === 0 ? (
        <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
          Nessun rinnovo o scadenza nei prossimi dodici mesi.
        </p>
      ) : (
        <ol className="space-y-4">
          {visibleItems.map((item, index) => {
            const renewal = item.event_type === "renewal";
            const date = dateParts(item.date);
            return (
              <li key={`${item.id}-${item.event_type}-${index}`} className="grid min-h-[62px] grid-cols-[62px_14px_minmax(0,1fr)] gap-2 sm:min-h-[68px] sm:grid-cols-[72px_18px_minmax(0,1fr)]">
                <time dateTime={item.date} className="flex h-[62px] flex-col items-center justify-center rounded-xl border border-gray-200 bg-gray-50 px-2 text-center dark:border-gray-800 dark:bg-gray-900 sm:h-[68px]">
                  <span className="flex items-baseline gap-1">
                    <span className="text-xl font-semibold leading-none text-gray-800 dark:text-white/90 sm:text-2xl">{date.day}</span>
                    <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">{date.month}</span>
                  </span>
                  <span className="mt-1 text-xs text-gray-500 dark:text-gray-400">{date.year}</span>
                </time>

                <div className="relative flex justify-center pt-5">
                  {index < visibleItems.length - 1 ? <span className="absolute bottom-[-16px] top-6 w-px bg-gray-200 dark:bg-gray-700" aria-hidden="true" /> : null}
                  <span className={`relative z-1 h-3 w-3 rounded-full border-2 border-white ring-4 dark:border-gray-900 ${renewal ? "bg-brand-500 ring-brand-50 dark:bg-brand-400 dark:ring-brand-500/15" : "bg-warning-500 ring-warning-50 dark:bg-orange-400 dark:ring-warning-500/15"}`} aria-hidden="true" />
                </div>

                <div className="min-w-0 pt-1">
                  <div className="2xl:flex 2xl:items-start 2xl:justify-between 2xl:gap-2">
                    <Link to={routes.contratto(item.id)} className="min-w-0 flex-1 text-sm font-semibold leading-5 text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">
                      {item.label}
                    </Link>
                    <span className="mt-1.5 block 2xl:mt-0">
                      <Badge color={renewal ? "info" : "warning"} size="sm">
                        {renewal ? "Rinnovo" : "Fine contratto"}
                      </Badge>
                    </span>
                  </div>
                  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {renewal ? "Rinnovo annuale" : "Fine contratto"}
                  </p>
                </div>
              </li>
            );
          })}
        </ol>
      )}

      {items.length > 5 ? (
        <Link to={routes.contratti} className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400 dark:hover:text-brand-300">
          Visualizza tutti i Contratti
          <ArrowRightIcon className="h-4 w-4" aria-hidden="true" />
        </Link>
      ) : null}
    </ComponentCard>
  );
}
