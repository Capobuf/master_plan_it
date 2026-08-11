import { useState } from "react";
import { ApiError } from "../../api/client";
import { bulkExpenseAction, type ExpenseRegisterItem, type ExpenseYearOption } from "../../api/expenses";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";

type BulkKind = "close" | "move" | "delete";

interface Props {
  selected: ExpenseRegisterItem[];
  planningYearId: number;
  planningYears: ExpenseYearOption[];
  canEdit: boolean;
  canCreate: boolean;
  canDelete: boolean;
  onChanged: () => void;
  onPlanningYearChange: (planningYearId: number) => void;
}

export default function ExpenseBulkActions({ selected, planningYearId, planningYears, canEdit, canCreate, canDelete, onChanged, onPlanningYearChange }: Props) {
  const [kind, setKind] = useState<BulkKind | null>(null);
  const [outcome, setOutcome] = useState<"" | "not_incurred" | "cancelled">("");
  const [targetYearId, setTargetYearId] = useState("");
  const [allowRegeneration, setAllowRegeneration] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  if (selected.length === 0) return null;

  async function submit() {
    if (!kind) return;
    const base = { planning_year_id: planningYearId, items: selected.map(({ id, lock_version }) => ({ id, lock_version })) };
    setBusy(true); setError(null);
    try {
      if (kind === "close") await bulkExpenseAction({ ...base, action: "close", outcome: outcome || null });
      if (kind === "delete") await bulkExpenseAction({ ...base, action: "delete", allow_regeneration: allowRegeneration });
      if (kind === "move") {
        const destination = Number(targetYearId);
        await bulkExpenseAction({ ...base, action: "move", target_planning_year_id: destination });
        onPlanningYearChange(destination);
      }
      setKind(null); onChanged();
    } catch (requestError: unknown) { setError(ApiError.from(requestError)); }
    finally { setBusy(false); }
  }

  const eligibleYears = planningYears.filter((year) => year.active && year.id !== planningYearId);
  return <>
    <div className="flex flex-wrap items-center gap-2">
      <span className="mr-1 text-sm font-semibold text-brand-700 dark:text-brand-300">{selected.length} Spese selezionate</span>
      {canEdit ? <Button type="button" size="sm" variant="outline" onClick={() => setKind("close")}>Chiudi</Button> : null}
      {canEdit && canCreate ? <Button type="button" size="sm" variant="outline" onClick={() => setKind("move")}>Sposta</Button> : null}
      {canDelete ? <Button type="button" size="sm" variant="outline" onClick={() => setKind("delete")}>Elimina</Button> : null}
    </div>
    <Modal isOpen={kind !== null} onClose={busy ? () => undefined : () => setKind(null)} className="max-w-lg p-6">
      <h3 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">{kind === "close" ? "Chiudi le Spese selezionate" : kind === "move" ? "Sposta le Spese selezionate" : "Elimina le Spese selezionate"}</h3>
      <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">L'operazione riguarda {selected.length} Spese ed è atomica: in caso di errore non verrà applicata parzialmente.</p>
      {kind === "close" ? <label className="mt-5 block text-sm text-gray-700 dark:text-gray-300">Esito (opzionale)<select value={outcome} onChange={(event) => setOutcome(event.target.value as typeof outcome)} className="mt-2 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700"><option value="">Nessun esito speciale</option><option value="not_incurred">Non sostenuta</option><option value="cancelled">Annullata</option></select></label> : null}
      {kind === "move" ? <label className="mt-5 block text-sm text-gray-700 dark:text-gray-300">Anno destinazione<select value={targetYearId} onChange={(event) => setTargetYearId(event.target.value)} className="mt-2 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700"><option value="">Seleziona un anno</option>{eligibleYears.map((year) => <option key={year.id} value={year.id}>{year.label}</option>)}</select></label> : null}
      {kind === "delete" ? <label className="mt-5 flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={allowRegeneration} onChange={(event) => setAllowRegeneration(event.target.checked)} className="mt-0.5 size-4" />Consenti la rigenerazione futura delle occorrenze generate da Contratto.</label> : null}
      {error ? <p className="mt-4 rounded-lg bg-error-50 p-3 text-sm text-error-600 dark:bg-error-500/15 dark:text-error-400">{error.message}</p> : null}
      <div className="mt-6 flex justify-end gap-3"><Button type="button" variant="outline" onClick={() => setKind(null)} disabled={busy}>Annulla</Button><Button type="button" onClick={() => void submit()} disabled={busy || (kind === "move" && !targetYearId)}>{busy ? "Operazione…" : "Conferma"}</Button></div>
    </Modal>
  </>;
}
