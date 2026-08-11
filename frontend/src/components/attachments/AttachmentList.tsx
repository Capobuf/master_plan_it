import type { Attachment, AttachmentAbilities } from "../../api/attachments";
import { formatDateTime } from "../../presentation/formatters";
import Button from "../ui/button/Button";
import formatBytes from "./formatBytes";

export default function AttachmentList({ items, abilities, busyId, onDownload, onDelete }: {
  items: Attachment[];
  abilities: AttachmentAbilities;
  busyId: number | null;
  onDownload: (attachment: Attachment) => void;
  onDelete: (attachment: Attachment) => void;
}) {
  if (items.length === 0) return <p className="rounded-xl border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nessun allegato presente.</p>;

  return <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
    <div className="hidden grid-cols-[minmax(0,2fr)_minmax(7rem,1fr)_minmax(7rem,1fr)_auto] gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3 text-xs font-medium uppercase text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] sm:grid"><span>File</span><span>Dimensione</span><span>Caricato</span><span>Azioni</span></div>
    <ul className="divide-y divide-gray-100 dark:divide-gray-800">{items.map((attachment) => <li key={attachment.id} className="grid gap-3 px-4 py-4 sm:grid-cols-[minmax(0,2fr)_minmax(7rem,1fr)_minmax(7rem,1fr)_auto] sm:items-center">
      <div className="min-w-0"><p className="truncate font-medium text-gray-800 dark:text-white/90">{attachment.name}</p><p className="mt-1 text-xs uppercase text-gray-500 dark:text-gray-400">{attachment.extension} · {attachment.uploaded_by.name}</p></div>
      <p className="text-sm text-gray-600 dark:text-gray-300"><span className="sm:hidden">Dimensione: </span>{formatBytes(attachment.size)}</p>
      <p className="text-sm text-gray-600 dark:text-gray-300"><span className="sm:hidden">Caricato: </span>{attachment.uploaded_at ? formatDateTime(attachment.uploaded_at) : "—"}</p>
      <div className="flex flex-wrap gap-2">{abilities.download ? <Button type="button" size="sm" variant="outline" disabled={busyId === attachment.id} onClick={() => onDownload(attachment)}>Scarica</Button> : null}{abilities.delete ? <Button type="button" size="sm" variant="outline" disabled={busyId === attachment.id} onClick={() => onDelete(attachment)}>Elimina</Button> : null}</div>
    </li>)}</ul>
  </div>;
}
