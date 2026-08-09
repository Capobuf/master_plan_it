import { useEffect, useState } from "react";
import { ApiError } from "../../api/client";
import {
  listExpensePlanningYears,
  moveExpense,
  type ExpenseYearOption,
} from "../../api/expenses";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";

interface ExpenseMoveModalProps {
  expenseId: number;
  lockVersion: number;
  currentPlanningYearId: number;
  isOpen: boolean;
  onClose: () => void;
  onMoved: (destinationExpenseId: number) => void;
}

export default function ExpenseMoveModal({
  expenseId,
  lockVersion,
  currentPlanningYearId,
  isOpen,
  onClose,
  onMoved,
}: ExpenseMoveModalProps) {
  const [years, setYears] = useState<ExpenseYearOption[]>([]);
  const [targetYearId, setTargetYearId] = useState("");
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  useEffect(() => {
    if (!isOpen) return;
    setLoading(true);
    setError(null);
    void listExpensePlanningYears()
      .then((options) => {
        const eligible = options.filter(
          (year) => year.active && year.id !== currentPlanningYearId,
        );
        setYears(eligible);
        setTargetYearId(eligible.length === 1 ? String(eligible[0].id) : "");
      })
      .catch((requestError: unknown) => setError(ApiError.from(requestError)))
      .finally(() => setLoading(false));
  }, [currentPlanningYearId, isOpen]);

  async function handleMove() {
    if (!targetYearId) return;
    setBusy(true);
    setError(null);
    try {
      const result = await moveExpense(expenseId, {
        lock_version: lockVersion,
        target_planning_year_id: Number(targetYearId),
      });
      onMoved(result.destination.id);
    } catch (requestError: unknown) {
      setError(ApiError.from(requestError));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={busy ? () => undefined : onClose}
      className="max-w-lg p-6"
    >
      <h3 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">
        Sposta la spesa in un altro anno
      </h3>
      <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
        La spesa corrente verrà chiusa come spostata. La destinazione conserverà
        Stime e Preventivi, ma partirà senza approvato e senza Effettivi.
      </p>
      <label
        htmlFor="expense-move-year"
        className="mt-5 block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Anno di destinazione
      </label>
      <select
        id="expense-move-year"
        value={targetYearId}
        onChange={(event) => setTargetYearId(event.target.value)}
        disabled={loading || busy}
        className="mt-2 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90"
      >
        <option value="">Seleziona un anno attivo</option>
        {years.map((year) => (
          <option key={year.id} value={year.id}>
            {year.label}
          </option>
        ))}
      </select>
      {!loading && years.length === 0 && (
        <p className="mt-3 text-sm text-warning-600 dark:text-warning-400">
          Non è disponibile un altro anno di pianificazione attivo.
        </p>
      )}
      {error && (
        <p className="mt-4 rounded-lg bg-error-50 p-3 text-sm text-error-600 dark:bg-error-500/15 dark:text-error-400">
          {error.message}
          {error.correlationId ? ` Riferimento tecnico: ${error.correlationId}` : ""}
        </p>
      )}
      <div className="mt-6 flex justify-end gap-3">
        <Button variant="outline" onClick={onClose} disabled={busy}>
          Annulla
        </Button>
        <Button
          onClick={() => void handleMove()}
          disabled={busy || loading || !targetYearId}
        >
          {busy ? "Spostamento…" : "Sposta spesa"}
        </Button>
      </div>
    </Modal>
  );
}
