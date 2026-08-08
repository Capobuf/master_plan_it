import { useState } from "react";
import { Link, useNavigate } from "react-router";
import { ApiError } from "../../api/client";
import { getExpense, type ExpenseRegisterItem } from "../../api/expenses";
import ExpenseActionModal from "./ExpenseActionModal";
import ExpenseKindBadge from "./ExpenseKindBadge";
import ExpenseMoney from "./ExpenseMoney";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

interface ExpenseRegisterTableProps {
  expenses: ExpenseRegisterItem[];
  canEdit: boolean;
  canDelete: boolean;
  onDeleted: () => void;
  disabled?: boolean;
}

export default function ExpenseRegisterTable({
  expenses,
  canEdit,
  canDelete,
  onDeleted,
  disabled = false,
}: ExpenseRegisterTableProps) {
  const navigate = useNavigate();
  const [deleteTarget, setDeleteTarget] = useState<{
    id: number;
    lockVersion: number;
    generated: boolean;
    contractId: number | null;
  } | null>(null);
  const [loadingDeleteId, setLoadingDeleteId] = useState<number | null>(null);
  const [error, setError] = useState<ApiError | null>(null);

  async function prepareDelete(expense: ExpenseRegisterItem) {
    setLoadingDeleteId(expense.id);
    setError(null);
    try {
      const detail = await getExpense(expense.id);
      setDeleteTarget({
        id: detail.id,
        lockVersion: detail.lock_version,
        generated: detail.rows.some((row) => row.generated),
        contractId: detail.contract_id,
      });
    } catch (requestError: unknown) {
      setError(ApiError.from(requestError));
    } finally {
      setLoadingDeleteId(null);
    }
  }

  return (
    <>
      {error && (
        <p className="border-b border-error-100 bg-error-50 px-5 py-3 text-sm text-error-600 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-400">
          {error.message}
        </p>
      )}
      <div className="max-w-full overflow-x-auto">
        <Table>
          <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
            <TableRow>
              {[
                "Spesa",
                "Tipo",
                "Fornitore",
                "Centro di costo",
                "Netto",
                "IVA",
                "Lordo",
                "Azioni",
              ].map((heading) => (
                <TableCell
                  key={heading}
                  isHeader
                  className="whitespace-nowrap px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                >
                  {heading}
                </TableCell>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
            {expenses.map((expense) => (
              <TableRow key={expense.id}>
                <TableCell className="min-w-64 px-5 py-4 text-start">
                  <Link
                    to={`/expenses/${expense.id}`}
                    className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400"
                  >
                    {expense.title}
                  </Link>
                  <span className="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                    {expense.row_count} {expense.row_count === 1 ? "riga" : "righe"} · anno {expense.planning_year_label}
                  </span>
                </TableCell>
                <TableCell className="px-5 py-4 text-start">
                  <ExpenseKindBadge kind={expense.kind} />
                </TableCell>
                <TableCell className="px-5 py-4 text-start text-sm text-gray-600 dark:text-gray-300">
                  <span className="text-gray-500 dark:text-gray-400">Non disponibile</span>
                </TableCell>
                <TableCell className="px-5 py-4 text-start text-sm text-gray-600 dark:text-gray-300">
                  {expense.cost_center_name ?? `#${expense.cost_center_id}`}
                </TableCell>
                <TableCell className="whitespace-nowrap px-5 py-4 text-start text-sm text-gray-700 dark:text-gray-300">
                  <ExpenseMoney money={expense.totals} component="net" />
                </TableCell>
                <TableCell className="whitespace-nowrap px-5 py-4 text-start text-sm text-gray-700 dark:text-gray-300">
                  <ExpenseMoney money={expense.totals} component="vat" />
                </TableCell>
                <TableCell className="whitespace-nowrap px-5 py-4 text-start text-sm font-medium text-gray-800 dark:text-white/90">
                  <ExpenseMoney money={expense.totals} component="gross" />
                </TableCell>
                <TableCell className="px-5 py-4 text-start">
                  <div className="flex flex-wrap gap-2">
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => navigate(`/expenses/${expense.id}`)}
                      disabled={disabled}
                    >
                      Apri
                    </Button>
                    {canEdit && (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => navigate(`/expenses/${expense.id}/edit`)}
                        disabled={disabled}
                      >
                        Modifica
                      </Button>
                    )}
                    {canDelete && (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => void prepareDelete(expense)}
                        disabled={disabled || loadingDeleteId === expense.id}
                      >
                        {loadingDeleteId === expense.id ? "Caricamento…" : "Elimina"}
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      {deleteTarget && (
        <ExpenseActionModal
          expenseId={deleteTarget.id}
          lockVersion={deleteTarget.lockVersion}
          generated={deleteTarget.generated}
          contractId={deleteTarget.contractId}
          isOpen
          onClose={() => setDeleteTarget(null)}
          onDeleted={() => {
            setDeleteTarget(null);
            onDeleted();
          }}
        />
      )}
    </>
  );
}
