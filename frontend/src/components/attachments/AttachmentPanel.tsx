import { useCallback, useEffect, useMemo, useState } from "react";
import { ApiError } from "../../api/client";
import { deleteAttachment, downloadAttachment, listAttachments, uploadAttachment, type Attachment, type AttachmentAbilities, type AttachmentMeta, type AttachmentParent } from "../../api/attachments";
import ComponentCard from "../common/ComponentCard";
import Alert from "../ui/alert/Alert";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";
import AttachmentDropZone from "./AttachmentDropZone";
import AttachmentList from "./AttachmentList";
import formatBytes from "./formatBytes";

const noAbilities: AttachmentAbilities = { upload: false, download: false, delete: false };

export default function AttachmentPanel({ parent, title = "Allegati", compact = false }: { parent: AttachmentParent; title?: string; compact?: boolean }) {
  const kind = parent.kind;
  const expenseId = parent.kind === "expense" || parent.kind === "expense-row" ? parent.expenseId : null;
  const rowId = parent.kind === "expense-row" ? parent.rowId : null;
  const contractId = parent.kind === "contract" ? parent.contractId : null;
  const projectId = parent.kind === "project" ? parent.projectId : null;
  const stableParent = useMemo<AttachmentParent>(() => {
    if (kind === "expense-row") return { kind, expenseId: expenseId as number, rowId: rowId as number };
    if (kind === "expense") return { kind, expenseId: expenseId as number };
    if (kind === "contract") return { kind, contractId: contractId as number };
    return { kind, projectId: projectId as number };
  }, [contractId, expenseId, kind, projectId, rowId]);
  const [items, setItems] = useState<Attachment[]>([]);
  const [meta, setMeta] = useState<AttachmentMeta | null>(null);
  const [abilities, setAbilities] = useState<AttachmentAbilities>(noAbilities);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState<ApiError | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Attachment | null>(null);

  const load = useCallback(async () => {
    setLoading(true); setError(null);
    try { const response = await listAttachments(stableParent); setItems(response.data); setMeta(response.meta); setAbilities(response.abilities); }
    catch (requestError) { setError(ApiError.from(requestError)); }
    finally { setLoading(false); }
  }, [stableParent]);
  useEffect(() => { void load(); }, [load]);

  const onFiles = async (files: File[]) => {
    const file = files[0]; if (!file) return;
    setUploading(true); setProgress(0); setError(null);
    try { const response = await uploadAttachment(stableParent, file, setProgress); setItems((current) => [response.data, ...current]); setMeta(response.meta); setAbilities(response.abilities); setProgress(100); }
    catch (requestError) { setError(ApiError.from(requestError)); }
    finally { setUploading(false); }
  };
  const download = async (attachment: Attachment) => {
    setBusyId(attachment.id); setError(null);
    try { const blob = await downloadAttachment(stableParent, attachment.id); const url = URL.createObjectURL(blob); const link = document.createElement("a"); link.href = url; link.download = attachment.name; link.click(); URL.revokeObjectURL(url); }
    catch (requestError) { setError(ApiError.from(requestError)); }
    finally { setBusyId(null); }
  };
  const remove = async () => {
    if (!deleteTarget) return;
    setBusyId(deleteTarget.id); setError(null);
    try { await deleteAttachment(stableParent, deleteTarget.id); setDeleteTarget(null); await load(); }
    catch (requestError) { setError(ApiError.from(requestError)); }
    finally { setBusyId(null); }
  };

  return <ComponentCard title={`${title} (${items.length})`} compact={compact}>
    {error ? <Alert variant="error" title="Operazione allegati non riuscita" message={`${error.message}${error.correlationId ? ` Riferimento tecnico: ${error.correlationId}` : ""}`} /> : null}
    {meta ? <p className="text-xs text-gray-500 dark:text-gray-400">Spazio Tenant utilizzato: {formatBytes(Number(meta.used_bytes))} di {formatBytes(Number(meta.quota_bytes))}</p> : null}
    {abilities.upload ? <AttachmentDropZone onFiles={(files) => void onFiles(files)} onRejected={() => setError(new ApiError({ message: "Il file non rispetta formato o dimensione consentiti." }))} disabled={uploading} /> : null}
    {uploading ? <div aria-live="polite"><div className="mb-1 flex justify-between text-xs text-gray-500 dark:text-gray-400"><span>Caricamento in corso…</span><span>{progress}%</span></div><div className="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div className="h-full bg-brand-500 transition-all" style={{ width: `${progress}%` }} /></div></div> : null}
    {loading ? <p className="text-sm text-gray-500 dark:text-gray-400">Caricamento allegati…</p> : <AttachmentList items={items} abilities={abilities} busyId={busyId} onDownload={(item) => void download(item)} onDelete={setDeleteTarget} />}
    <Modal isOpen={deleteTarget !== null} onClose={() => setDeleteTarget(null)} className="max-w-lg p-6"><h2 className="pr-10 text-lg font-semibold text-gray-800 dark:text-white/90">Eliminare l'allegato?</h2><p className="mt-3 text-sm text-gray-600 dark:text-gray-300">{deleteTarget?.name} verrà eliminato definitivamente e non sarà recuperato da un ripristino dello storico.</p><div className="mt-6 flex justify-end gap-3"><Button type="button" variant="outline" onClick={() => setDeleteTarget(null)} disabled={busyId !== null}>Annulla</Button><Button type="button" onClick={() => void remove()} disabled={busyId !== null}>{busyId !== null ? "Eliminazione…" : "Elimina"}</Button></div></Modal>
  </ComponentCard>;
}
