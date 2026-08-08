import type { ContractRevision } from "../../api/contracts";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

export default function ContractHistoryTable({ revisions }: { revisions: ContractRevision[] }) {
  if (revisions.length === 0) return <p className="py-4 text-sm text-gray-500 dark:text-gray-400">No revision activity is available.</p>;
  return (
    <div className="max-w-full overflow-x-auto">
      <Table>
        <TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow><TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500">Operation</TableCell><TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500">Actor</TableCell><TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500">Timestamp</TableCell><TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500">Summary</TableCell></TableRow></TableHeader>
        <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{revisions.map((revision) => <TableRow key={revision.id}><TableCell className="py-3 text-sm text-gray-800 dark:text-white/90">{revision.operation}</TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{revision.actor ?? "—"}</TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{revision.timestamp ?? "—"}</TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{revision.summary ?? "—"}</TableCell></TableRow>)}</TableBody>
      </Table>
    </div>
  );
}
