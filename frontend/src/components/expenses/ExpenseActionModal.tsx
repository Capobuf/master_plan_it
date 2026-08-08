import { useState } from "react";
import { ApiError } from "../../api/client";
import { deleteExpense } from "../../api/expenses";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";

interface ExpenseActionModalProps {
  expenseId: number;
  lockVersion: number;
  generated: boolean;
  isOpen: boolean;
  onClose: () => void;
  onDeleted: () => void;
}

export default function ExpenseActionModal({
  expenseId,
  lockVersion,
  generated,
  isOpen,
  onClose,
  onDeleted,
}: ExpenseActionModalProps) {
  const [allowRegeneration, setAllowRegeneration] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function handleDelete() {
    setBusy(true);
    setError(null);

    try {
      await deleteExpense(expenseId, {
        lock_version: lockVersion,
        ...(generated ? { allow_regeneration: allowRegeneration } : {}),
      });
      onDeleted();
    } catch (requestError: unknown) {
      setError(ApiError.from(requestError));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal isOpen={isOpen} onClose={busy ? () => undefined : onClose} className="max-w-lg p-6">
      <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
        Elimina spesa
      </h3>
      <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
        La spesa verrà rimossa dalla vista corrente. Questa operazione richiede
        una nuova revisione e non può essere annullata da questa pagina.
      </p>
      {generated && (
        <label className="mt-5 flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300">
          <input
            type="checkbox"
            checked={allowRegeneration}
            onChange={(event) => setAllowRegeneration(event.target.checked)}
            disabled={busy}
            className="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
          />
          <span>
            Consenti la rigenerazione dell&apos;occorrenza generata dal contratto.
          </span>
        </label>
      )}
      {error && (
        <p className="mt-4 rounded-lg bg-error-50 p-3 text-sm text-error-600 dark:bg-error-500/15 dark:text-error-400">
          {error.message}
          {error.correlationId ? ` Correlation ID: ${error.correlationId}` : ""}
        </p>
      )}
      <div className="mt-6 flex justify-end gap-3">
        <Button variant="outline" onClick={onClose} disabled={busy}>
          Annulla
        </Button>
        <Button onClick={() => void handleDelete()} disabled={busy}>
          {busy ? "Eliminazione…" : "Elimina"}
        </Button>
      </div>
    </Modal>
  );
}
