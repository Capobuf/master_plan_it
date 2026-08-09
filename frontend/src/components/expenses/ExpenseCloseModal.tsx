import { useState } from "react";
import { ApiError } from "../../api/client";
import {
  closeExpense,
  type ExpenseDetail,
} from "../../api/expenses";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";

interface ExpenseCloseModalProps {
  expenseId: number;
  lockVersion: number;
  isOpen: boolean;
  onClose: () => void;
  onClosed: (expense: ExpenseDetail) => void;
}

export default function ExpenseCloseModal({
  expenseId,
  lockVersion,
  isOpen,
  onClose,
  onClosed,
}: ExpenseCloseModalProps) {
  const [outcome, setOutcome] = useState<"" | "not_incurred" | "cancelled">("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function handleCloseExpense() {
    setBusy(true);
    setError(null);
    try {
      const expense = await closeExpense(expenseId, {
        lock_version: lockVersion,
        ...(outcome ? { outcome } : {}),
      });
      onClosed(expense);
    } catch (requestError: unknown) {
      setError(ApiError.from(requestError));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal isOpen={isOpen} onClose={busy ? () => undefined : onClose} className="max-w-lg p-6">
      <h3 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">
        Chiudi la spesa
      </h3>
      <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
        La chiusura rende finale lo scostamento. Una successiva modifica economica
        riaprirà automaticamente la Spesa; le modifiche descrittive non lo faranno.
      </p>
      <label htmlFor="expense-close-outcome" className="mt-5 block text-sm font-medium text-gray-700 dark:text-gray-300">
        Esito (opzionale)
      </label>
      <select
        id="expense-close-outcome"
        value={outcome}
        onChange={(event) => setOutcome(event.target.value as typeof outcome)}
        disabled={busy}
        className="mt-2 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90"
      >
        <option value="">Nessun esito speciale</option>
        <option value="not_incurred">Non sostenuta</option>
        <option value="cancelled">Annullata</option>
      </select>
      {error ? <p className="mt-4 rounded-lg bg-error-50 p-3 text-sm text-error-600 dark:bg-error-500/15 dark:text-error-400">{error.message}{error.correlationId ? ` Riferimento tecnico: ${error.correlationId}` : ""}</p> : null}
      <div className="mt-6 flex justify-end gap-3">
        <Button variant="outline" onClick={onClose} disabled={busy}>Annulla</Button>
        <Button onClick={() => void handleCloseExpense()} disabled={busy}>{busy ? "Chiusura…" : "Chiudi spesa"}</Button>
      </div>
    </Modal>
  );
}
