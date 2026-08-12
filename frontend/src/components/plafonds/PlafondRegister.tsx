import { Link } from "react-router";
import type { PlafondSummary } from "../../api/plafonds";
import { routes } from "../../navigation/routes";
import PlafondMeasures from "./PlafondMeasures";

export default function PlafondRegister({ plafonds }: { plafonds: PlafondSummary[] }) {
  return <div className="space-y-3">{plafonds.map((plafond) => <article key={plafond.id} className="rounded-xl border border-gray-200 p-4 dark:border-gray-800"><div className="mb-3 flex flex-wrap items-center justify-between gap-2"><div><Link to={routes.plafond(plafond.id)} className="font-semibold text-gray-900 hover:text-brand-500 dark:text-white">{plafond.title}</Link><p className="text-sm text-gray-500">Centro di Costo: {plafond.cost_center.name} · {plafond.economic_year_label}</p></div></div><PlafondMeasures measures={plafond.measures} currency={plafond.currency} compact /></article>)}</div>;
}
