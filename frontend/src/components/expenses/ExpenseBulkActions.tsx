import { useState } from "react";
import { ApiError } from "../../api/client";
import { bulkExpenseAction, type ExpenseRegisterItem } from "../../api/expenses";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";

interface Props {
  selected: ExpenseRegisterItem[];
  planningYearId: number;
  canDelete: boolean;
  onChanged: () => void;
}

export default function ExpenseBulkActions({ selected, planningYearId, canDelete, onChanged }: Props) {
  const [open, setOpen] = useState(false);
  const [allowRegeneration, setAllowRegeneration] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  if (selected.length === 0) return null;

  async function submit() {
    const base = { planning_year_id: planningYearId, items: selected.map(({ id, lock_version }) => ({ id, lock_version })) };
    setBusy(true); setError(null);
    try {
      await bulkExpenseAction({ ...base, action: "delete", allow_regeneration: allowRegeneration });
      setOpen(false); onChanged();
    } catch (requestError: unknown) { setError(ApiError.from(requestError)); }
    finally { setBusy(false); }
  }

  return <>
    <div className="flex flex-wrap items-center gap-2">
      <span className="mr-1 text-sm font-semibold text-brand-700 dark:text-brand-300">{selected.length} Spese selezionate</span>
      {canDelete ? <Button type="button" size="sm" variant="outline" onClick={() => setOpen(true)}>Elimina</Button> : null}
    </div>
    <Modal isOpen={open} onClose={busy ? () => undefined : () => setOpen(false)} className="max-w-lg p-6">
      <h3 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">Elimina le Spese selezionate</h3>
      <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">L'operazione riguarda {selected.length} Spese ed è atomica: in caso di errore non verrà applicata parzialmente.</p>
      <label className="mt-5 flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={allowRegeneration} onChange={(event) => setAllowRegeneration(event.target.checked)} className="mt-0.5 size-4" />Consenti la rigenerazione futura delle occorrenze generate da Contratto.</label>
      {error ? <p className="mt-4 rounded-lg bg-error-50 p-3 text-sm text-error-600 dark:bg-error-500/15 dark:text-error-400">{error.message}</p> : null}
      <div className="mt-6 flex justify-end gap-3"><Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={busy}>Annulla</Button><Button type="button" onClick={() => void submit()} disabled={busy}>{busy ? "Operazione…" : "Conferma"}</Button></div>
    </Modal>
  </>;
}
