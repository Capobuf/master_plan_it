import type { AnnualBudget, BudgetApprovalPreview } from "../../api/budget";
import { formatMoney } from "../../presentation/formatters";
import ComponentCard from "../common/ComponentCard";
import Badge from "../ui/badge/Badge";
import BudgetProposalImpact from "./BudgetProposalImpact";
import Button from "../ui/button/Button";
import BudgetApprovalModal from "./BudgetApprovalModal";
import BudgetApprovalHistory from "./BudgetApprovalHistory";
import { useState } from "react";

const stateLabel: Record<AnnualBudget["planning_year"]["state"], string> = {
  preparation: "Preparazione",
  approved: "Approvato",
  closed: "Chiuso",
};

async function noRefresh(): Promise<void> {}

export default function BudgetView({ dataset, preview, onRefresh = noRefresh }: { dataset: AnnualBudget; preview: BudgetApprovalPreview; onRefresh?: () => Promise<void> | void }) {
  const [approvalOpen, setApprovalOpen] = useState(false);
  const approvedTotal = dataset.approved_snapshot?.total.official ?? null;
  const canApprove = dataset.actions.can_approve && preview.can_approve && !preview.empty_composition;
  const metrics = [
    { label: "Budget proposto", value: dataset.proposal.total.official },
    { label: "Valutazioni informative", value: dataset.informative_evaluations.official },
    { label: "Effettivi correnti", value: dataset.actuals.official },
    { label: "Previsto approvato", value: approvedTotal },
  ];

  return <div className="space-y-6">
    <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div><p className="text-sm text-gray-500 dark:text-gray-400">Anno economico {dataset.planning_year.year_label}</p><p className="mt-1 font-medium text-gray-800 dark:text-white/90">Stato del Budget: {stateLabel[dataset.planning_year.state]}</p><p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Base economica: <strong>{dataset.basis === "net" ? "Netto" : "Lordo"}</strong></p></div>
      <Badge color={dataset.planning_year.state === "preparation" ? "warning" : dataset.planning_year.state === "approved" ? "success" : "light"}>{stateLabel[dataset.planning_year.state]}</Badge>
    </div>
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">{metrics.map((metric) => <article key={metric.label} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><p className="text-sm text-gray-500 dark:text-gray-400">{metric.label}</p><p className="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{metric.value === null ? "—" : formatMoney(metric.value, dataset.currency)}</p></article>)}</div>
    <ComponentCard title="Vista di impatto del Budget Proposto" desc="La composizione è calcolata dal dataset economico autorevole: non sono disponibili selezioni o importi manuali.">
      {dataset.actions.can_view_approval_preview ? <BudgetProposalImpact preview={preview} /> : <p className="text-sm text-gray-500 dark:text-gray-400">La vista di impatto non è disponibile con il contesto corrente.</p>}
      {canApprove ? <div className="mt-5 flex justify-end"><Button type="button" onClick={() => setApprovalOpen(true)}>Approva Budget proposto</Button></div> : null}
    </ComponentCard>
    <BudgetApprovalHistory planningYearId={dataset.planning_year.id} />
    <BudgetApprovalModal isOpen={approvalOpen} preview={preview} onClose={() => setApprovalOpen(false)} onApproved={onRefresh} onReReview={onRefresh} />
  </div>;
}
