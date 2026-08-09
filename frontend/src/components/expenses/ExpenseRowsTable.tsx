import type { ExpenseRow } from "../../api/expenses";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";
import ExpenseMoney from "./ExpenseMoney";
import { ExpenseRowStateBadge } from "./ExpenseKindBadge";
import { domainLabel } from "../../presentation/labels";
import { formatDate } from "../../presentation/formatters";

interface ExpenseRowsTableProps {
  rows: ExpenseRow[];
  canConfirm: boolean;
  confirmingRowId: number | null;
  onConfirm: (row: ExpenseRow) => void;
}

export default function ExpenseRowsTable({
  rows,
  canConfirm,
  confirmingRowId,
  onConfirm,
}: ExpenseRowsTableProps) {
  if (rows.length === 0) {
    return (
      <p className="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
        Questa spesa non contiene righe correnti.
      </p>
    );
  }

  return (
    <div className="max-w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-white/[0.05]">
      <Table>
        <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
          <TableRow>
            {["Tipo", "Descrizione", "Fornitore", "Stato", "Netto", "IVA", "Lordo", "Azioni"].map(
              (heading) => (
                <TableCell
                  key={heading}
                  isHeader
                  className="whitespace-nowrap px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                >
                  {heading}
                </TableCell>
              ),
            )}
          </TableRow>
        </TableHeader>
        <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
          {rows.map((row) => {
            const isActual = row.type.toLowerCase() === "actual";
            const canConfirmRow = isActual && row.confirmation_state === "to_confirm";

            return (
              <TableRow key={row.id}>
                <TableCell className="px-5 py-4 text-start text-sm font-medium text-gray-800 dark:text-white/90">
                  {domainLabel(row.type)}
                  {row.generated && (
                    <span className="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400">
                      Generata{row.contract_term_id ? ` · termine #${row.contract_term_id}` : ""}
                    </span>
                  )}
                </TableCell>
                <TableCell className="min-w-56 px-5 py-4 text-start text-sm text-gray-700 dark:text-gray-300">
                  {row.description}
                  <span className="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                    {row.is_extra ? "Extra" : "Ordinaria"}
                    {row.spend_date ? ` · ${formatDate(row.spend_date)}` : ""}
                  </span>
                </TableCell>
                <TableCell className="px-5 py-4 text-start text-sm text-gray-600 dark:text-gray-300">
                  {row.vendor_name ?? "—"}
                </TableCell>
                <TableCell className="px-5 py-4 text-start">
                  {isActual ? <ExpenseRowStateBadge state={row.confirmation_state} /> : <span className="text-sm text-gray-500 dark:text-gray-400">—</span>}
                  {row.is_system_managed && (
                    <span className="mt-1 block text-xs text-gray-500 dark:text-gray-400">Gestita dal sistema</span>
                  )}
                </TableCell>
                <TableCell className="whitespace-nowrap px-5 py-4 text-start text-sm text-gray-700 dark:text-gray-300">
                  <ExpenseMoney money={row.totals} component="net" />
                </TableCell>
                <TableCell className="whitespace-nowrap px-5 py-4 text-start text-sm text-gray-700 dark:text-gray-300">
                  <ExpenseMoney money={row.totals} component="vat" />
                </TableCell>
                <TableCell className="whitespace-nowrap px-5 py-4 text-start text-sm font-medium text-gray-800 dark:text-white/90">
                  <ExpenseMoney money={row.totals} component="gross" />
                </TableCell>
                <TableCell className="px-5 py-4 text-start">
                  {canConfirm && canConfirmRow && (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => onConfirm(row)}
                      disabled={confirmingRowId === row.id}
                    >
                      {confirmingRowId === row.id ? "Conferma…" : "Conferma"}
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            );
          })}
        </TableBody>
      </Table>
    </div>
  );
}
