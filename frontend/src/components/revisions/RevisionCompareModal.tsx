import { useEffect, useState } from "react";
import type { RevisionComparison, RevisionDiff } from "../../api/revisions";
import { formatDecimal, formatPercentage } from "../../presentation/formatters";
import { domainLabel } from "../../presentation/labels";
import Alert from "../ui/alert/Alert";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";

interface RevisionCompareModalProps {
  comparison: RevisionComparison | null;
  busy: boolean;
  error: string | null;
  onClose: () => void;
  onRestore: (() => Promise<void>) | null;
}

export default function RevisionCompareModal({ comparison, busy, error, onClose, onRestore }: RevisionCompareModalProps) {
  const [confirming, setConfirming] = useState(false);
  useEffect(() => { setConfirming(false); }, [comparison?.revision.id]);

  return <Modal isOpen={comparison !== null} onClose={onClose} className="max-h-[90vh] max-w-3xl overflow-y-auto p-6">
    {comparison ? <>
      <h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">Confronta con lo stato attuale</h2>
      <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Sono mostrate soltanto le differenze business della revisione selezionata.</p>
      {error ? <div className="mt-4"><Alert variant="error" title="Operazione non riuscita" message={error} /></div> : null}
      <div className="mt-5 space-y-3">
        {comparison.changes.length === 0 ? <p className="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-300">La revisione coincide con lo stato corrente.</p> : comparison.changes.map((change, index) => <DiffRow key={`${change.scope}:${change.subject}:${change.field}:${index}`} change={change} />)}
      </div>
      {confirming ? <div className="mt-5 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10"><p className="font-medium text-gray-800 dark:text-white/90">Confermare il ripristino?</p><p className="mt-1 text-sm text-gray-600 dark:text-gray-300">L’intero stato mostrato verrà rivalidato. Il ripristino creerà una nuova revisione e non modificherà quella sorgente.</p></div> : null}
      <div className="mt-6 flex flex-wrap justify-end gap-3">
        <Button type="button" variant="outline" onClick={confirming ? () => setConfirming(false) : onClose} disabled={busy}>{confirming ? "Indietro" : "Chiudi"}</Button>
        {onRestore ? <Button type="button" onClick={confirming ? () => { void onRestore(); } : () => setConfirming(true)} disabled={busy || comparison.changes.length === 0}>{busy ? "Ripristino…" : confirming ? "Conferma ripristino" : "Ripristina"}</Button> : null}
      </div>
    </> : null}
  </Modal>;
}

function DiffRow({ change }: { change: RevisionDiff }) {
  return <article className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
    <div className="flex flex-col items-start gap-1 sm:flex-row sm:flex-wrap sm:items-baseline sm:justify-between sm:gap-2"><h3 className="font-medium text-gray-800 dark:text-white/90">{change.subject}</h3><span className="max-w-full break-words text-xs font-medium text-gray-500 dark:text-gray-400">{change.label}</span></div>
    <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2"><div><dt className="text-xs text-gray-500 dark:text-gray-400">Revisione</dt><dd className="mt-1 break-words text-gray-800 dark:text-white/90">{displayValue(change.revision_value, change.field)}</dd></div><div><dt className="text-xs text-gray-500 dark:text-gray-400">Stato attuale</dt><dd className="mt-1 break-words text-gray-800 dark:text-white/90">{displayValue(change.current_value, change.field)}</dd></div></dl>
  </article>;
}

const decimalFields = new Set(["quantity", "unit_price", "entered_amount", "gross_amount"]);
const percentageFields = new Set(["vat_rate"]);
const domainFields = new Set(["kind", "type", "billing_cycle", "stage"]);

function displayValue(value: RevisionDiff["revision_value"], field: string): string {
  if (value === null || value === "") return "—";
  if (typeof value === "boolean") return value ? "Sì" : "No";
  if (decimalFields.has(field)) return formatDecimal(String(value), 2);
  if (percentageFields.has(field)) return formatPercentage(String(value));
  if (domainFields.has(field)) return domainLabel(String(value));
  return String(value);
}
