import type { ContractRevision } from "../../api/contracts";
import { formatDateTime } from "../../presentation/formatters";
import { domainLabel } from "../../presentation/labels";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

export default function ContractHistoryTable({ revisions }: { revisions: ContractRevision[] }) {
  if (!revisions.length) return <p className="rounded-xl border border-dashed border-gray-300 p-5 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nessuna revisione disponibile.</p>;
  return <div className="max-w-full overflow-x-auto"><Table><TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow>{['Operazione','Utente','Data e ora','Riepilogo'].map((heading)=><TableCell key={heading} isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">{heading}</TableCell>)}</TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{revisions.map((revision)=><TableRow key={revision.id}><TableCell className="py-3 text-sm font-medium text-gray-800 dark:text-white/90">{domainLabel(revision.operation)}</TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{revision.actor.label}</TableCell><TableCell className="whitespace-nowrap py-3 text-sm text-gray-600 dark:text-gray-300">{formatDateTime(revision.timestamp)}</TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{revision.summary}</TableCell></TableRow>)}</TableBody></Table></div>;
}
