import { Link } from "react-router";
import {
  type ApprovalContributor,
  type ApprovalExclusion,
  type BudgetApprovalPreview,
} from "../../api/budget";
import { formatMoney } from "../../presentation/formatters";
import Badge from "../ui/badge/Badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

const exclusionReason: Record<ApprovalExclusion["reason"], string> = {
  alternative_planning: "Pianificazione alternativa",
  actual_not_proposed: "Effettivo non proposto",
  soft_deleted: "Elemento eliminato",
  covered_by_plafond: "Coperto dal Plafond",
  non_current_planning: "Pianificazione non corrente",
};

function withPlanningYearContext(href: string, planningYearId: number): string {
  const [pathAndQuery, hash = ""] = href.split("#", 2);
  const [path, query = ""] = pathAndQuery.split("?", 2);
  const params = new URLSearchParams(query);
  params.set("planning_year_id", String(planningYearId));
  return `${path}?${params.toString()}${hash ? `#${hash}` : ""}`;
}

function SourceLink({ item, planningYearId }: { item: ApprovalContributor | ApprovalExclusion; planningYearId: number }) {
  const label = item.row ? `${item.expense.title} · ${item.row.description}` : item.expense.title;
  if (!item.drill_down.authorized || item.drill_down.href === null) {
    return <span>{label} <span className="text-xs text-gray-500 dark:text-gray-400">(dettaglio non autorizzato)</span></span>;
  }
  return <Link className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400" to={withPlanningYearContext(item.drill_down.href, planningYearId)}>{label}</Link>;
}

function Measures({ total, currency }: Pick<BudgetApprovalPreview, "total" | "currency">) {
  return <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
    <div><dt className="text-gray-500 dark:text-gray-400">Netto</dt><dd className="font-semibold">{formatMoney(total.net, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">IVA</dt><dd className="font-semibold">{formatMoney(total.vat, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Lordo</dt><dd className="font-semibold">{formatMoney(total.gross, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Totale ufficiale</dt><dd className="font-semibold">{formatMoney(total.official, currency)}</dd></div>
  </dl>;
}

function FullMeasures({ total, currency }: Pick<BudgetApprovalPreview, "total" | "currency">) {
  return <dl className="grid min-w-48 grid-cols-2 gap-x-3 gap-y-1 text-xs">
    <div><dt className="text-gray-500 dark:text-gray-400">Netto</dt><dd>{formatMoney(total.net, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">IVA</dt><dd>{formatMoney(total.vat, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Lordo</dt><dd>{formatMoney(total.gross, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Valore ufficiale</dt><dd className="font-medium">{formatMoney(total.official, currency)}</dd></div>
  </dl>;
}

function FrozenDimensions({ contributor }: { contributor: ApprovalContributor }) {
  const dimensions = [
    `Centro di costo: ${contributor.dimensions.cost_center.name}`,
    contributor.dimensions.vendor ? `Fornitore: ${contributor.dimensions.vendor.name}` : null,
    contributor.dimensions.project ? `Progetto: ${contributor.dimensions.project.title}` : null,
    contributor.dimensions.contract ? `Contratto: ${contributor.dimensions.contract.title}` : null,
  ].filter((value): value is string => value !== null);
  return <ul className="space-y-1 text-xs text-gray-600 dark:text-gray-300">{dimensions.map((dimension) => <li key={dimension}>{dimension}</li>)}</ul>;
}

function ImpactContent({ preview }: { preview: BudgetApprovalPreview }) {
  const contributorStatus = preview.empty_composition
    ? "La proposta non contiene componenti economici e non può essere approvata."
    : preview.can_approve
      ? "La composizione completa è pronta per l’approvazione."
      : "La composizione è disponibile in sola lettura e non è approvabile con le autorizzazioni o lo stato correnti.";

  return <div className="space-y-4" aria-live="polite">
    <div className="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p className="text-sm text-gray-500 dark:text-gray-400">{preview.composition.contributor_count} contributori nella composizione proposta</p>
        <p className="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{contributorStatus}</p>
      </div>
      <Badge color={preview.empty_composition ? "warning" : "success"}>{preview.empty_composition ? "Composizione vuota" : "Composizione completa"}</Badge>
    </div>
    <Measures total={preview.total} currency={preview.currency} />

    <div>
      <h4 className="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90">Componenti inclusi</h4>
      {preview.contributors.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Nessun componente contribuisce al totale proposto.</p> : <div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Componente", "Motivo", "Dimensioni congelate", "Importi"].map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap">{heading}</TableCell>)}</TableRow></TableHeader><TableBody>{preview.contributors.map((contributor) => <TableRow key={contributor.source_identity}><TableCell><SourceLink item={contributor} planningYearId={preview.planning_year.id} /></TableCell>{/* Component kind is server-authored and never a user selection. */}<TableCell>{contributor.kind === "plafond_allocation" ? "Allocazione Plafond (conteggiata una volta)" : "Pianificazione corrente"}</TableCell><TableCell><FrozenDimensions contributor={contributor} /></TableCell><TableCell><FullMeasures total={contributor.amount} currency={preview.currency} /></TableCell></TableRow>)}</TableBody></Table></div>}
    </div>

    <div>
      <h4 className="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90">Componenti esclusi</h4>
      {preview.exclusions.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Non ci sono esclusioni da spiegare.</p> : <div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Componente", "Motivo dell’esclusione", "Dettaglio", "Importi non conteggiati"].map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap">{heading}</TableCell>)}</TableRow></TableHeader><TableBody>{preview.exclusions.map((exclusion) => <TableRow key={exclusion.source_identity}><TableCell><SourceLink item={exclusion} planningYearId={preview.planning_year.id} /></TableCell>{/* The server reason is canonical; this label does not affect composition. */}<TableCell>{exclusionReason[exclusion.reason]}</TableCell><TableCell>{exclusion.detail}</TableCell><TableCell><FullMeasures total={exclusion.amount} currency={preview.currency} /></TableCell></TableRow>)}</TableBody></Table></div>}
    </div>
  </div>;
}

export default function BudgetProposalImpact({ preview }: { preview: BudgetApprovalPreview }) {
  return <ImpactContent preview={preview} />;
}
