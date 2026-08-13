import { useState } from "react";
import { approveBudgetProposal, type BudgetApprovalPreview } from "../../api/budget";
import { ApiError } from "../../api/client";
import Label from "../form/Label";
import InputField from "../form/input/InputField";
import TextArea from "../form/input/TextArea";
import Alert from "../ui/alert/Alert";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";
import { formatMoney } from "../../presentation/formatters";
import BudgetProposalImpact from "./BudgetProposalImpact";

interface BudgetApprovalModalProps {
  isOpen: boolean;
  preview: BudgetApprovalPreview;
  onClose: () => void;
  /** Refreshes the server-built overview and impact after a successful approval or stale evidence. */
  onApproved: () => Promise<void> | void;
  onReReview: () => Promise<void> | void;
}

function evidenceRows(preview: BudgetApprovalPreview): Array<{ label: string; value: string }> {
  return [
    { label: "Base economica", value: preview.basis === "net" ? "Netto" : "Lordo" },
    { label: "Contributori", value: String(preview.composition.contributor_count) },
    { label: "Fingerprint composizione", value: preview.composition.fingerprint },
    { label: "Fingerprint superficie", value: preview.surface_fingerprint },
    { label: "Versione Budget", value: String(preview.composition.versions.budget_lock_version) },
    { label: "Versione proiezione", value: preview.composition.versions.projection_version },
  ];
}

function errorMessage(error: ApiError): string {
  const correlation = error.correlationId ? ` Riferimento tecnico: ${error.correlationId}` : "";
  if (error.code === "BUDGET_COMPOSITION_STALE" || error.code === "STALE_VERSION") {
    return `La proposta è cambiata e deve essere riesaminata prima di una nuova conferma.${correlation}`;
  }
  const effectiveDate = fieldMessage(error.fields.effective_date);
  if (effectiveDate !== null) return `Data di efficacia: ${effectiveDate}${correlation}`;
  return `${error.message}${correlation}`;
}

function fieldMessage(value: unknown): string | null {
  const candidate = Array.isArray(value) ? value[0] : value;
  if (typeof candidate !== "string") return null;
  const sanitized = Array.from(candidate, (character) => {
    const codePoint = character.codePointAt(0) ?? 0;
    return codePoint < 32 || codePoint === 127 ? " " : character;
  }).join("").replace(/\s+/g, " ").trim();
  return sanitized === "" ? null : sanitized;
}

export default function BudgetApprovalModal({
  isOpen,
  preview,
  onClose,
  onApproved,
  onReReview,
}: BudgetApprovalModalProps) {
  const [effectiveDate, setEffectiveDate] = useState("");
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [refreshError, setRefreshError] = useState<ApiError | null>(null);
  const [approvalCommitted, setApprovalCommitted] = useState(false);
  const staleEvidence = error?.code === "BUDGET_COMPOSITION_STALE" || error?.code === "STALE_VERSION";
  const dateBoundAvailable = /^\d{4}-\d{2}-\d{2}$/.test(preview.effective_date_max);
  const effectiveDateError = error === null ? null : fieldMessage(error.fields.effective_date);
  const canConfirm = !busy && !approvalCommitted && !preview.empty_composition && preview.can_approve && dateBoundAvailable && effectiveDate.length > 0;

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!canConfirm) return;

    setBusy(true);
    setError(null);
    setRefreshError(null);
    try {
      await approveBudgetProposal(preview.planning_year.id, {
        effective_date: effectiveDate,
        note: note.trim() === "" ? null : note,
        composition: {
          schema_version: preview.composition.schema_version,
          fingerprint: preview.composition.fingerprint,
          versions: preview.composition.versions,
        },
      });
    } catch (cause: unknown) {
      setError(ApiError.from(cause));
      setBusy(false);
      return;
    }

    setApprovalCommitted(true);
    try {
      await onApproved();
      onClose();
    } catch (cause: unknown) {
      setRefreshError(ApiError.from(cause));
    } finally {
      setBusy(false);
    }
  }

  async function reReview() {
    setBusy(true);
    try {
      await onReReview();
      setError(null);
    } catch (cause: unknown) {
      setError(ApiError.from(cause));
    } finally {
      setBusy(false);
    }
  }

  async function retryRefresh() {
    setBusy(true);
    setRefreshError(null);
    try {
      await onApproved();
      onClose();
    } catch (cause: unknown) {
      setRefreshError(ApiError.from(cause));
    } finally {
      setBusy(false);
    }
  }

  return <Modal isOpen={isOpen} onClose={busy ? () => undefined : onClose} className="max-w-2xl p-6" ariaLabelledBy="budget-approval-modal-title">
    <h2 id="budget-approval-modal-title" className="pr-12 text-xl font-semibold text-gray-800 dark:text-white/90">Conferma l’approvazione del Budget</h2>
    <p className="mt-3 text-sm text-gray-600 dark:text-gray-300">Stai approvando l’intera composizione mostrata nella Vista di impatto. Non puoi selezionare componenti né modificare importi.</p>

    <section className="mt-5 rounded-xl border border-gray-200 p-4 dark:border-gray-800" aria-labelledby="approval-evidence-title">
      <h3 id="approval-evidence-title" className="text-sm font-semibold text-gray-800 dark:text-white/90">Evidenza della composizione riesaminata</h3>
      <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Totale ufficiale: <strong>{formatMoney(preview.total.official, preview.currency)}</strong> ({preview.total.net} Netto, {preview.total.vat} IVA, {preview.total.gross} Lordo).</p>
      <dl className="mt-3 grid gap-2 text-xs sm:grid-cols-2">{evidenceRows(preview).map((row) => <div key={row.label}><dt className="text-gray-500 dark:text-gray-400">{row.label}</dt><dd className="break-all font-medium text-gray-800 dark:text-white/90">{row.value}</dd></div>)}</dl>
    </section>

    <section className="mt-5" aria-labelledby="approval-full-impact-title">
      <h3 id="approval-full-impact-title" className="text-sm font-semibold text-gray-800 dark:text-white/90">Impatto completo da confermare</h3>
      <div className="mt-3 max-h-96 overflow-y-auto rounded-xl border border-gray-200 p-4 dark:border-gray-800"><BudgetProposalImpact preview={preview} /></div>
    </section>

    {preview.empty_composition ? <Alert variant="warning" title="Composizione vuota" message="La proposta non contiene componenti economici e non può essere approvata." /> : null}
    {!dateBoundAvailable ? <Alert variant="error" title="Data di efficacia non disponibile" message="Il server non ha fornito il limite della data nel fuso del Tenant. Aggiorna la proposta prima di confermare." /> : null}
    {error ? <div className="mt-5"><Alert variant="error" title={staleEvidence ? "Proposta da riesaminare" : "Approvazione non riuscita"} message={errorMessage(error)} /></div> : null}
    {refreshError ? <div className="mt-5"><Alert variant="warning" title="Approvazione registrata; aggiornamento vista non riuscito" message={`${refreshError.message}${refreshError.correlationId ? ` Riferimento tecnico: ${refreshError.correlationId}` : ""}`} /></div> : null}

    <form className="mt-5 space-y-4" noValidate onSubmit={(event) => void submit(event)}>
      <div>
        <Label htmlFor="budget-approval-effective-date">Data di efficacia</Label>
        <InputField id="budget-approval-effective-date" type="date" value={effectiveDate} max={preview.effective_date_max} disabled={busy || !dateBoundAvailable || approvalCommitted} error={effectiveDateError !== null} onChange={(event) => setEffectiveDate(event.target.value)} hint={effectiveDateError ?? "Non può essere successiva a oggi nel fuso del Tenant, come calcolato dal server."} />
      </div>
      <div>
        <Label htmlFor="budget-approval-note">Nota (facoltativa)</Label>
        <TextArea id="budget-approval-note" value={note} disabled={busy || approvalCommitted} onChange={setNote} placeholder="Aggiungi una nota alla decisione, se utile." />
      </div>
      <div className="flex flex-wrap justify-end gap-3">
        <Button type="button" variant="outline" onClick={onClose} disabled={busy}>Annulla</Button>
        {staleEvidence ? <Button type="button" variant="outline" onClick={() => void reReview()} disabled={busy}>Aggiorna e riesamina la proposta</Button> : null}
        {refreshError ? <Button type="button" variant="outline" onClick={() => void retryRefresh()} disabled={busy}>Riprova aggiornamento vista</Button> : null}
        <Button disabled={!canConfirm}>{approvalCommitted ? "Approvazione registrata" : busy ? "Approvazione in corso…" : "Conferma approvazione"}</Button>
      </div>
    </form>
  </Modal>;
}
