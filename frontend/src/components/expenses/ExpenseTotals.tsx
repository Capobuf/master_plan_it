import type { ExpenseMoney as ExpenseMoneyValue } from "../../api/expenses";
import ExpenseMoney from "./ExpenseMoney";
import { domainLabel } from "../../presentation/labels";

export default function ExpenseTotals({
  totals,
  title = "Totali",
}: {
  totals: ExpenseMoneyValue;
  title?: string;
}) {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
          {title}
        </h3>
        <span className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
          Base Ufficiale: {domainLabel(totals.official_basis)}
        </span>
      </div>
      <div className="mt-4 grid gap-4 sm:grid-cols-3">
        {(["net", "vat", "gross"] as const).map((component) => (
          <div key={component}>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {component === "net" ? "Netto" : component === "vat" ? "IVA" : "Lordo"}
            </p>
            <p className="mt-1 text-lg font-semibold text-gray-800 dark:text-white/90">
              <ExpenseMoney money={totals} component={component} />
            </p>
          </div>
        ))}
      </div>
    </div>
  );
}
