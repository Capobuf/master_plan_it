import { useEffect, useState } from "react";
import {
  applyBudgetApproval,
  type AnnualBudget,
} from "../../api/budget";
import { ApiError } from "../../api/client";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";

function today(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
}

interface BudgetApprovalModalProps {
  dataset: AnnualBudget;
  isOpen: boolean;
  onClose: () => void;
  onApplied: (dataset: AnnualBudget) => void;
}

export default function BudgetApprovalModal({
  dataset,
  isOpen,
  onClose,
  onApplied,
}: BudgetApprovalModalProps) {
  const [selected, setSelected] = useState<number[]>([]);
  const [amounts, setAmounts] = useState<Record<number, string>>({});
  const [effectiveDate, setEffectiveDate] = useState(today);
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  useEffect(() => {
    if (!isOpen) return;
    setSelected([]);
    setAmounts(
      Object.fromEntries(
        dataset.expenses.map((expense) => [
          expense.id,
          expense.approved ?? expense.planned ?? "0.00",
        ]),
      ),
    );
    setEffectiveDate(today());
    setReason("");
    setError(null);
  }, [dataset, isOpen]);

  function toggle(expenseId: number, checked: boolean) {
    setSelected((current) =>
      checked
        ? [...current, expenseId]
        : current.filter((id) => id !== expenseId),
    );
  }

  async function approve() {
    if (selected.length === 0) return;
    setBusy(true);
    setError(null);
    try {
      const expenses = new Map(dataset.expenses.map((expense) => [expense.id, expense]));
      const next = await applyBudgetApproval(dataset.budget.planning_year_id, {
        budget_lock_version: dataset.budget.lock_version,
        effective_date: effectiveDate,
        ...(reason.trim() ? { reason: reason.trim() } : {}),
        items: selected.map((expenseId) => ({
          expense_id: expenseId,
          expense_lock_version: expenses.get(expenseId)?.lock_version ?? 1,
          approved_amount: amounts[expenseId] ?? "0.00",
        })),
      });
      onApplied(next);
      onClose();
    } catch (cause: unknown) {
      setError(ApiError.from(cause));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={busy ? () => undefined : onClose}
      className="max-h-[90vh] max-w-3xl overflow-y-auto p-6"
    >
      <h3 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">
        {dataset.budget.state === "preparation"
          ? "Prima approvazione"
          : "Variazione approvata"}
      </h3>
      <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
        Seleziona soltanto le Spese incluse in questa decisione. Un importo pari
        a zero resta distinto da una Spesa non selezionata e non approvata.
      </p>
      <div className="mt-5 grid gap-4 md:grid-cols-2">
        <div>
          <label htmlFor="approval-effective-date" className="mb-1 block text-sm font-medium">
            Data di efficacia
          </label>
          <input
            id="approval-effective-date"
            type="date"
            value={effectiveDate}
            onChange={(event) => setEffectiveDate(event.target.value)}
            disabled={busy}
            className="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 dark:border-gray-700"
          />
        </div>
        <div>
          <label htmlFor="approval-reason" className="mb-1 block text-sm font-medium">
            Motivazione
          </label>
          <input
            id="approval-reason"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            maxLength={500}
            disabled={busy}
            className="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 dark:border-gray-700"
          />
        </div>
      </div>
      <div className="mt-5 divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
        {dataset.expenses.map((expense) => {
          const checked = selected.includes(expense.id);
          return (
            <div key={expense.id} className="grid gap-3 p-4 sm:grid-cols-[1fr_10rem] sm:items-center">
              <label className="flex items-start gap-3 text-sm text-gray-800 dark:text-white/90">
                <input
                  type="checkbox"
                  checked={checked}
                  onChange={(event) => toggle(expense.id, event.target.checked)}
                  disabled={busy}
                  className="mt-0.5 h-4 w-4 rounded border-gray-300"
                />
                <span>
                  <span className="block font-medium">{expense.title}</span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    Corrente: {expense.approved ?? "non approvata"} · Pianificato: {expense.planned ?? "—"}
                  </span>
                </span>
              </label>
              <input
                aria-label={`Nuovo approvato ${expense.title}`}
                inputMode="decimal"
                value={amounts[expense.id] ?? ""}
                onChange={(event) =>
                  setAmounts((current) => ({
                    ...current,
                    [expense.id]: event.target.value,
                  }))
                }
                disabled={!checked || busy}
                className="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-end text-sm disabled:opacity-50 dark:border-gray-700"
              />
            </div>
          );
        })}
      </div>
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
          onClick={() => void approve()}
          disabled={busy || selected.length === 0 || effectiveDate === ""}
        >
          {busy ? "Salvataggio…" : "Registra decisione"}
        </Button>
      </div>
    </Modal>
  );
}
