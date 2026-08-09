import { useState } from "react";
import { ApiError } from "../../api/client";
import {
  deleteExpense,
  deleteGeneratedExpense,
} from "../../api/expenses";
import Checkbox from "../form/input/Checkbox";
import InputField from "../form/input/InputField";
import Label from "../form/Label";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";

interface ExpenseActionModalProps {
  expenseId: number;
  lockVersion: number;
  generated: boolean;
  contractId: number | null;
  isOpen: boolean;
  onClose: () => void;
  onDeleted: () => void;
}

export default function ExpenseActionModal({
  expenseId,
  lockVersion,
  generated,
  contractId,
  isOpen,
  onClose,
  onDeleted,
}: ExpenseActionModalProps) {
  const [allowRegeneration, setAllowRegeneration] = useState(false);
  const [deletionReason, setDeletionReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function handleDelete() {
    setBusy(true);
    setError(null);

    try {
      if (generated && contractId !== null) {
        await deleteGeneratedExpense(contractId, expenseId, {
          lock_version: lockVersion,
          allow_regeneration: allowRegeneration,
        });
      } else {
        await deleteExpense(expenseId, {
          lock_version: lockVersion,
          ...(deletionReason.trim()
            ? { deletion_reason: deletionReason.trim() }
            : {}),
        });
      }
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
      {generated && contractId !== null && (
        <div className="mt-5">
          <Checkbox
            checked={allowRegeneration}
            onChange={setAllowRegeneration}
            disabled={busy}
            label="Consenti la rigenerazione dell'occorrenza generata dal contratto"
          />
        </div>
      )}
      <div className="mt-5">
        <Label htmlFor="expense-deletion-reason">Motivo (opzionale)</Label>
        <InputField
          id="expense-deletion-reason"
          value={deletionReason}
          onChange={(event) => setDeletionReason(event.target.value)}
          disabled={busy}
          placeholder="Inserisci un motivo"
        />
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
        <Button onClick={() => void handleDelete()} disabled={busy}>
          {busy ? "Eliminazione…" : "Elimina"}
        </Button>
      </div>
    </Modal>
  );
}
