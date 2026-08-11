import { useState } from "react";
import { Link } from "react-router";
import { closeBudget, type AnnualBudget } from "../../api/budget";
import { ApiError } from "../../api/client";
import { routes } from "../../navigation/routes";
import { formatMoney } from "../../presentation/formatters";
import ComponentCard from "../common/ComponentCard";
import BudgetApprovalModal from "./BudgetApprovalModal";
import Alert from "../ui/alert/Alert";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

export default function BudgetView({ dataset, asOf, canApprove, canClose, onAsOfChange, onChange }: { dataset: AnnualBudget; asOf: string; canApprove: boolean; canClose: boolean; onAsOfChange: (value: string) => void; onChange: (value: AnnualBudget) => void }) {
  const [approvalOpen, setApprovalOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const { summary, budget } = dataset;

  async function close() {
    setBusy(true);
    setError(null);
    try {
      onChange(await closeBudget(budget.planning_year_id, budget.lock_version));
    } catch (cause: unknown) {
      setError(ApiError.from(cause));
    } finally {
      setBusy(false);
    }
  }

  const metrics: Array<{ label: string; value: string | null; percentage?: boolean }> = [
    { label: "Proposto", value: summary.proposed },
    { label: "Approvazione iniziale", value: summary.initial_approved },
    { label: "Variazioni", value: summary.approved_variations },
    { label: "Approvato corrente", value: summary.approved_current },
    { label: "Actual", value: summary.actual },
    { label: "Residuo", value: summary.residual },
    { label: "Scostamento", value: summary.variance },
    { label: "Utilizzo", value: summary.utilization_percentage, percentage: true },
  ];

  return <div className="space-y-6">
    <div className="flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div><label htmlFor="budget-as-of" className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Vista temporale</label><input id="budget-as-of" type="datetime-local" value={asOf} onChange={(event) => onAsOfChange(event.target.value)} className="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white" /></div>
      <div className="flex items-center gap-2"><Badge color={budget.state === "closed" ? "light" : budget.state === "approved" ? "success" : "warning"}>{budget.state}</Badge>{asOf ? <Button variant="outline" size="sm" onClick={() => onAsOfChange("")}>Torna al corrente</Button> : null}</div>
    </div>
    {dataset.read_only ? <Alert variant="info" title="Vista storica in sola lettura" message={`Valori ricostruiti al cutoff ${dataset.cutoff_utc ?? dataset.requested_as_of ?? "richiesto"}.`} /> : null}
    {budget.warning ? <Alert variant="warning" title="Budget chiuso" message="Le operazioni economiche restano consentite e sono tracciate; verifica il warning prima di procedere." /> : null}
    {error ? <Alert variant="error" title="Operazione non riuscita" message={error.message} /> : null}
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">{metrics.map((metric) => <article key={metric.label} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><p className="text-sm text-gray-500 dark:text-gray-400">{metric.label}</p><p className="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{metric.percentage ? (metric.value === null ? "—" : `${metric.value}%`) : formatMoney(metric.value ?? "0.00", summary.currency)}</p></article>)}</div>
    <ComponentCard title="Dettaglio spese annuali">
      <div className="max-w-full overflow-x-auto"><Table><TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow>{["Spesa", "Tipo", "Centro di costo", "Pianificato", "Approvato", "Actual", "Residuo", "Stato", "Esito"].map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">{heading}</TableCell>)}</TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{dataset.expenses.map((expense) => <TableRow key={expense.id}><TableCell className="py-3 text-sm font-medium"><Link to={routes.spesa(expense.id)} className="text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">{expense.title}</Link></TableCell><TableCell className="py-3"><Badge color={expense.kind === "plafond" ? "info" : "light"}>{expense.kind === "plafond" ? "Plafond" : "Spesa"}</Badge></TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{expense.cost_center_name}</TableCell><TableCell className="py-3 text-sm">{formatMoney(expense.planned ?? "0.00", summary.currency)}</TableCell><TableCell className="py-3 text-sm">{expense.approved === null ? "Non approvata" : formatMoney(expense.approved, summary.currency)}</TableCell><TableCell className="py-3 text-sm">{formatMoney(expense.actual, summary.currency)}</TableCell><TableCell className="py-3 text-sm">{expense.residual === null ? "—" : formatMoney(expense.residual, summary.currency)}</TableCell><TableCell className="py-3"><Badge color={expense.state === "closed" ? "light" : "success"}>{expense.state === "closed" ? "Chiusa" : "Aperta"}</Badge></TableCell><TableCell className="py-3 text-sm">{expense.closure_outcome === "not_incurred" ? "Non sostenuta" : expense.closure_outcome === "cancelled" ? "Annullata" : expense.closure_outcome === "moved" ? "Spostata" : "—"}</TableCell></TableRow>)}</TableBody></Table></div>
    </ComponentCard>
    {!dataset.read_only && (canApprove || canClose) ? <ComponentCard title="Azioni Budget"><div className="flex flex-wrap gap-2">{canApprove && dataset.expenses.length > 0 ? <Button onClick={() => setApprovalOpen(true)} disabled={busy}>{budget.state === "preparation" ? "Registra prima approvazione" : "Registra variazione"}</Button> : null}{canClose && budget.state !== "closed" ? <Button variant="outline" onClick={() => void close()} disabled={busy}>{busy ? "Chiusura…" : "Chiudi Budget"}</Button> : null}</div></ComponentCard> : null}
    <ComponentCard title="Plafond"><div className="flex flex-wrap gap-6 text-sm"><span>Sforamento complessivo: <strong>{formatMoney(summary.plafond_overrun, summary.currency)}</strong></span><span>Spese aperte: <strong>{summary.open_expenses}</strong></span><span>Spese chiuse: <strong>{summary.closed_expenses}</strong></span><span>Actual senza approvato: <strong>{summary.unapproved_actual_expenses}</strong></span></div></ComponentCard>
    <BudgetApprovalModal dataset={dataset} isOpen={approvalOpen} onClose={() => setApprovalOpen(false)} onApplied={onChange} />
  </div>;
}
