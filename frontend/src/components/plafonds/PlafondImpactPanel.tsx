import { Link } from "react-router";
import type { PlafondImpact } from "../../api/plafonds";
import { routes } from "../../navigation/routes";
import { formatMoney } from "../../presentation/formatters";
import Alert from "../ui/alert/Alert";
import PlafondMeasures from "./PlafondMeasures";

export default function PlafondImpactPanel({ impact, title = "Impatto sul Plafond", canOpenRows = true }: {
  impact: PlafondImpact;
  title?: string;
  canOpenRows?: boolean;
}) {
  return <section aria-label={title} className="space-y-4 rounded-2xl border border-brand-200 bg-brand-50/40 p-4 dark:border-brand-500/30 dark:bg-brand-500/[0.06]">
    <header><h3 className="font-semibold text-gray-900 dark:text-white">{title}</h3><p className="mt-1 text-sm text-gray-600 dark:text-gray-300">{impact.plafond.title}</p></header>
    <div><p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Situazione attuale</p><PlafondMeasures measures={impact.current} currency={impact.currency} compact /></div>
    <div><p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Situazione proposta</p><PlafondMeasures measures={impact.proposed} currency={impact.currency} compact /></div>
    <dl className="grid gap-3 sm:grid-cols-2"><div><dt className="text-xs text-gray-500">Importo richiesto</dt><dd className="font-semibold">{formatMoney(impact.requested, impact.currency)}</dd></div><div><dt className="text-xs text-gray-500">Importo mancante</dt><dd className={impact.shortage === "0.00" ? "font-semibold" : "font-semibold text-error-600"}>{formatMoney(impact.shortage, impact.currency)}</dd></div></dl>
    {!impact.can_confirm ? <Alert variant="warning" title="Capienza insufficiente" message="Riduci l’importo, aumenta l’Allocazione, dividi la Spesa o rimuovi la copertura." /> : null}
    {impact.blocking_rows.length > 0 ? <div><p className="mb-2 text-sm font-medium">Righe che rendono insufficiente la riduzione</p><ul className="space-y-2 text-sm">{impact.blocking_rows.map((row) => <li key={row.row_id} className="flex flex-wrap justify-between gap-2 rounded-lg bg-white/70 p-2 dark:bg-gray-900/50"><span>{canOpenRows ? <Link className="font-medium hover:text-brand-500" to={routes.spesa(row.expense_id)}>{row.expense_title}: {row.description}</Link> : <>{row.expense_title}: {row.description}</>}<span className="ml-2 text-xs text-gray-500">Centro riga: {row.expense_cost_center.name} · Centro Plafond: {row.plafond_cost_center.name}</span></span><strong>{formatMoney(row.amount.official, impact.currency)}</strong></li>)}</ul></div> : null}
  </section>;
}
