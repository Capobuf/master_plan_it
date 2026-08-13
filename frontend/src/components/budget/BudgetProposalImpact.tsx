import { useEffect, useState } from "react";
import { Link } from "react-router";
import {
  getBudgetApprovalPreview,
  type ApprovalContributor,
  type ApprovalExclusion,
  type BudgetApprovalPreview,
} from "../../api/budget";
import { ApiError } from "../../api/client";
import { routes } from "../../navigation/routes";
import { formatMoney } from "../../presentation/formatters";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

const exclusionReason: Record<ApprovalExclusion["reason"], string> = {
  alternative_planning: "Pianificazione alternativa",
  actual_not_proposed: "Effettivo non proposto",
  soft_deleted: "Elemento eliminato",
  covered_by_plafond: "Coperto dal Plafond",
  non_current_planning: "Pianificazione non corrente",
};

function contextRoute(item: ApprovalContributor | ApprovalExclusion, planningYearId: number): string {
  const route = "kind" in item && item.kind === "plafond_allocation"
    ? routes.plafond(item.expense.id)
    : routes.spesa(item.expense.id);
  return `${route}?planning_year_id=${planningYearId}`;
}

function SourceLink({ item, planningYearId }: { item: ApprovalContributor | ApprovalExclusion; planningYearId: number }) {
  const label = item.row ? `${item.expense.title} · ${item.row.description}` : item.expense.title;
  if (!item.drill_down.authorized || item.drill_down.href === null) {
    return <span>{label} <span className="text-xs text-gray-500 dark:text-gray-400">(dettaglio non autorizzato)</span></span>;
  }
  return <Link className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400" to={contextRoute(item, planningYearId)}>{label}</Link>;
}

function Measures({ total, currency }: Pick<BudgetApprovalPreview, "total" | "currency">) {
  return <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
    <div><dt className="text-gray-500 dark:text-gray-400">Netto</dt><dd className="font-semibold">{formatMoney(total.net, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">IVA</dt><dd className="font-semibold">{formatMoney(total.vat, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Lordo</dt><dd className="font-semibold">{formatMoney(total.gross, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Totale ufficiale</dt><dd className="font-semibold">{formatMoney(total.official, currency)}</dd></div>
  </dl>;
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
      {preview.contributors.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Nessun componente contribuisce al totale proposto.</p> : <div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Componente", "Motivo", "Centro di costo", "Importo ufficiale"].map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap">{heading}</TableCell>)}</TableRow></TableHeader><TableBody>{preview.contributors.map((contributor) => <TableRow key={contributor.source_identity}><TableCell><SourceLink item={contributor} planningYearId={preview.planning_year.id} /></TableCell><TableCell>{contributor.kind === "plafond_allocation" ? "Allocazione Plafond (conteggiata una volta)" : "Pianificazione corrente"}</TableCell><TableCell>{contributor.dimensions.cost_center.name}</TableCell><TableCell className="whitespace-nowrap font-medium">{formatMoney(contributor.amount.official, preview.currency)}</TableCell></TableRow>)}</TableBody></Table></div>}
    </div>

    <div>
      <h4 className="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90">Componenti esclusi</h4>
      {preview.exclusions.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Non ci sono esclusioni da spiegare.</p> : <div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Componente", "Motivo dell’esclusione", "Dettaglio", "Importo non conteggiato"].map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap">{heading}</TableCell>)}</TableRow></TableHeader><TableBody>{preview.exclusions.map((exclusion) => <TableRow key={exclusion.source_identity}><TableCell><SourceLink item={exclusion} planningYearId={preview.planning_year.id} /></TableCell>{/* The server reason is canonical; this label does not affect composition. */}<TableCell>{exclusionReason[exclusion.reason]}</TableCell><TableCell>{exclusion.detail}</TableCell><TableCell className="whitespace-nowrap">{formatMoney(exclusion.amount.official, preview.currency)}</TableCell></TableRow>)}</TableBody></Table></div>}
    </div>
  </div>;
}

export default function BudgetProposalImpact({ planningYearId }: { planningYearId: number }) {
  const [preview, setPreview] = useState<BudgetApprovalPreview | null>(null);
  const [error, setError] = useState<ApiError | null>(null);

  useEffect(() => {
    let active = true;
    setPreview(null);
    setError(null);
    void getBudgetApprovalPreview(planningYearId)
      .then((data) => { if (active) setPreview(data); })
      .catch((cause: unknown) => { if (active) setError(ApiError.from(cause)); });
    return () => { active = false; };
  }, [planningYearId]);

  if (error) return <Alert variant="error" title="Vista di impatto non disponibile" message={error.correlationId ? `${error.message} Riferimento tecnico: ${error.correlationId}` : error.message} />;
  if (preview === null) return <p className="text-sm text-gray-500 dark:text-gray-400" role="status">Caricamento della proposta completa…</p>;
  return <ImpactContent preview={preview} />;
}
