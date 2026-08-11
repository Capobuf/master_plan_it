import type { ExpenseDetail } from "../../api/expenses";
import AttachmentPanel from "./AttachmentPanel";

export default function ExpenseAttachmentsPanel({ expense }: { expense: ExpenseDetail }) {
  return <div className="space-y-4">
    <AttachmentPanel parent={{ kind: "expense", expenseId: expense.id }} title="Spesa" compact />
    <section aria-labelledby="expense-row-attachments-title" className="space-y-3">
      <div><h2 id="expense-row-attachments-title" className="text-lg font-semibold text-gray-800 dark:text-white/90">Righe della Spesa</h2><p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Gli allegati di una riga vengono eliminati definitivamente quando la riga viene rimossa.</p></div>
      <div className="grid gap-4 xl:grid-cols-2">{expense.rows.map((row) => <AttachmentPanel key={row.id} parent={{ kind: "expense-row", expenseId: expense.id, rowId: row.id }} title={`Riga ${row.position} · ${row.description}`} compact />)}</div>
    </section>
  </div>;
}
