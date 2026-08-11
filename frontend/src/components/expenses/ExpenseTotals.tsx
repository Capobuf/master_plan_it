import type { ExpenseMoney as ExpenseMoneyValue } from "../../api/expenses";
import { DollarLineIcon } from "../../icons";
import ExpenseMoney from "./ExpenseMoney";

export default function ExpenseTotals({
  totals,
  title = "Totali",
  description,
}: {
  totals: ExpenseMoneyValue;
  title?: string;
  description?: string;
}) {
  return (
    <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] sm:p-5">
      <div className="flex items-start gap-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
          <DollarLineIcon className="size-5" aria-hidden="true" />
        </span>
        <div className="min-w-0">
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            {title}
          </h3>
          {description ? (
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {description}
            </p>
          ) : null}
        </div>
      </div>
      <div className="mt-5 grid overflow-hidden rounded-xl border border-gray-200 bg-gray-50/70 divide-y divide-gray-200 dark:border-gray-800 dark:bg-gray-900/50 dark:divide-gray-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        {(["net", "vat", "gross"] as const).map((component) => (
          <div key={component} className={`min-w-0 p-4 sm:px-5 ${component === "gross" ? "bg-brand-50/50 dark:bg-brand-500/[0.07]" : ""}`}>
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
              {component === "net" ? "Netto" : component === "vat" ? "IVA" : "Lordo"}
            </p>
            <p className={`mt-1.5 break-words text-xl font-semibold ${component === "gross" ? "text-brand-700 dark:text-brand-300" : "text-gray-800 dark:text-white/90"}`}>
              <ExpenseMoney money={totals} component={component} />
            </p>
          </div>
        ))}
      </div>
    </section>
  );
}
