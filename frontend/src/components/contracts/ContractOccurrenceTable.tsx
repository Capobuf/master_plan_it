import type { ContractOccurrence, GeneratedExpense } from "../../api/contracts";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

interface ContractOccurrenceTableProps {
  occurrences: ContractOccurrence[];
  generatedExpenses: GeneratedExpense[];
  canGenerate: boolean;
  canSuppress: boolean;
  canResume: boolean;
  canDeleteGenerated: boolean;
  busyKey: string | null;
  onSynchronize: () => void;
  onSuppress: (occurrence: ContractOccurrence) => void;
  onResume: (occurrence: ContractOccurrence) => void;
  onResumeAndGenerate: (occurrence: ContractOccurrence) => void;
  onDeleteGenerated: (expense: GeneratedExpense) => void;
}

export default function ContractOccurrenceTable({ occurrences, generatedExpenses, canGenerate, canSuppress, canResume, canDeleteGenerated, busyKey, onSynchronize, onSuppress, onResume, onResumeAndGenerate, onDeleteGenerated }: ContractOccurrenceTableProps) {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3"><div><h3 className="text-base font-medium text-gray-800 dark:text-white/90">Occurrences</h3><p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Generation controls affect only the selected source occurrence.</p></div>{canGenerate ? <Button size="sm" variant="outline" onClick={onSynchronize} disabled={busyKey !== null}>Synchronize</Button> : null}</div>
      <div className="max-w-full overflow-x-auto"><Table><TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow>{(["Year", "Date", "Source key", "Gross", "State", "Actions"] as const).map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap py-3 text-start text-theme-xs font-medium text-gray-500">{heading}</TableCell>)}</TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{occurrences.length === 0 ? <TableRow><TableCell className="py-4 text-sm text-gray-500">No expected occurrences returned.</TableCell></TableRow> : occurrences.map((occurrence) => { const key = `occurrence:${occurrence.source_key}`; const busy = busyKey === key; return <TableRow key={occurrence.source_key}><TableCell className="py-3 text-sm text-gray-800 dark:text-white/90">{occurrence.planning_year}</TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{occurrence.occurrence_date}</TableCell><TableCell className="max-w-48 truncate py-3 font-mono text-xs text-gray-500">{occurrence.source_key}</TableCell><TableCell className="whitespace-nowrap py-3 text-sm text-gray-800 dark:text-white/90">{occurrence.gross} {occurrence.currency ?? ""}</TableCell><TableCell className="py-3"><Badge color={occurrence.suppressed ? "warning" : occurrence.expense_id ? "success" : "info"} size="sm">{occurrence.suppressed ? "Suppressed" : occurrence.expense_id ? "Generated" : occurrence.generation_state ?? "Pending"}</Badge></TableCell><TableCell className="py-3"><div className="flex flex-wrap gap-2">{canSuppress && !occurrence.suppressed ? <Button size="sm" variant="outline" onClick={() => onSuppress(occurrence)} disabled={busy}>Suppress</Button> : null}{canResume && occurrence.suppressed ? <Button size="sm" variant="outline" onClick={() => onResume(occurrence)} disabled={busy}>Resume</Button> : null}{canResume && occurrence.suppressed ? <Button size="sm" onClick={() => onResumeAndGenerate(occurrence)} disabled={busy}>Resume & generate</Button> : null}{busy ? <span className="self-center text-xs text-gray-500">Working…</span> : null}</div></TableCell></TableRow>; })}</TableBody></Table></div>
      <div><h3 className="mb-3 text-base font-medium text-gray-800 dark:text-white/90">Generated expenses</h3><div className="max-w-full overflow-x-auto"><Table><TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow><TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500">Title</TableCell><TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500">Source key</TableCell><TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500">Gross</TableCell><TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500">Actions</TableCell></TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{generatedExpenses.length === 0 ? <TableRow><TableCell className="py-4 text-sm text-gray-500">No generated expenses returned.</TableCell></TableRow> : generatedExpenses.map((expense) => { const key = `expense:${expense.id}`; return <TableRow key={expense.id}><TableCell className="py-3 text-sm text-gray-800 dark:text-white/90">{expense.title}</TableCell><TableCell className="max-w-48 truncate py-3 font-mono text-xs text-gray-500">{expense.source_key}</TableCell><TableCell className="whitespace-nowrap py-3 text-sm text-gray-800 dark:text-white/90">{expense.gross} {expense.currency ?? ""}</TableCell><TableCell className="py-3">{canDeleteGenerated ? <Button size="sm" variant="outline" onClick={() => onDeleteGenerated(expense)} disabled={busyKey !== null}>Delete</Button> : null}{busyKey === key ? <span className="ml-2 text-xs text-gray-500">Working…</span> : null}</TableCell></TableRow>; })}</TableBody></Table></div></div>
    </div>
  );
}
