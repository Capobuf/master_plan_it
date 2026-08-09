import { useState } from "react";
import { Link, useNavigate } from "react-router";
import { ApiError } from "../../api/client";
import { getExpense, type ExpenseRegisterItem } from "../../api/expenses";
import ExpenseActionModal from "./ExpenseActionModal";
import ExpenseKindBadge from "./ExpenseKindBadge";
import ExpenseMoney from "./ExpenseMoney";
import IconButton from "../common/IconButton";
import { PencilIcon, TrashBinIcon } from "../../icons";
import { routes } from "../../navigation/routes";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

interface ExpenseRegisterTableProps {
  expenses: ExpenseRegisterItem[];
  canEdit: boolean;
  canDelete: boolean;
  canViewProjects: boolean;
  onDeleted: () => void;
  disabled?: boolean;
}

export default function ExpenseRegisterTable({
  expenses,
  canEdit,
  canDelete,
  canViewProjects,
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
                "Contratto",
                "Progetto",
                "Centro di costo",
                "Netto",
                "IVA",
                "Lordo",
                "Azioni",
              ].map((heading) => (
                <TableCell
                  key={heading}
                  isHeader
                  className={`whitespace-nowrap px-4 py-3 text-theme-xs font-medium text-gray-500 dark:text-gray-400 ${["Netto", "IVA", "Lordo"].includes(heading) ? "text-end" : "text-start"}`}
                >
                  {heading}
                </TableCell>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
            {expenses.map((expense) => (
              <TableRow key={expense.id}>
                <TableCell className="min-w-56 px-4 py-3 text-start">
                  <Link
                    to={routes.spesa(expense.id)}
                    className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400"
                  >
                    {expense.title}
                  </Link>
                  <span className="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                    {expense.row_count} {expense.row_count === 1 ? "riga" : "righe"} · anno {expense.planning_year_label}
                  </span>
                </TableCell>
                <TableCell className="px-4 py-3 text-start">
                  <ExpenseKindBadge kind={expense.kind} />
                </TableCell>
                <TableCell className="px-4 py-3 text-start text-sm text-gray-600 dark:text-gray-300">
                  {expense.contract_id && expense.contract_title ? <Link to={routes.contratto(expense.contract_id)} className="font-medium text-gray-700 hover:text-brand-500 dark:text-gray-300 dark:hover:text-brand-400">{expense.contract_title}</Link> : "—"}
                </TableCell>
                <TableCell className="px-4 py-3 text-start text-sm text-gray-600 dark:text-gray-300">
                  {expense.project_id && expense.project_title ? canViewProjects ? <Link to={routes.progetto(expense.project_id)} className="font-medium text-gray-700 hover:text-brand-500 dark:text-gray-300 dark:hover:text-brand-400">{expense.project_title}</Link> : expense.project_title : "—"}
                </TableCell>
                <TableCell className="px-4 py-3 text-start text-sm text-gray-600 dark:text-gray-300">
                  {expense.cost_center_name ?? "—"}
                </TableCell>
                <TableCell className="whitespace-nowrap px-4 py-3 text-end text-sm text-gray-700 dark:text-gray-300">
                  <ExpenseMoney money={expense.totals} component="net" />
                </TableCell>
                <TableCell className="whitespace-nowrap px-4 py-3 text-end text-sm text-gray-700 dark:text-gray-300">
                  <ExpenseMoney money={expense.totals} component="vat" />
                </TableCell>
                <TableCell className="whitespace-nowrap px-4 py-3 text-end text-sm font-medium text-gray-800 dark:text-white/90">
                  <ExpenseMoney money={expense.totals} component="gross" />
                </TableCell>
                <TableCell className="px-4 py-3 text-start">
                  <div className="flex items-center gap-2">
                    {canEdit && (
                      <IconButton
                        icon={PencilIcon}
                        label={`Modifica ${expense.title}`}
                        onClick={() => navigate(routes.modificaSpesa(expense.id))}
                        disabled={disabled}
                      />
                    )}
                    {canDelete && (
                      <IconButton
                        icon={TrashBinIcon}
                        label={loadingDeleteId === expense.id ? `Caricamento ${expense.title}` : `Elimina ${expense.title}`}
                        onClick={() => void prepareDelete(expense)}
                        disabled={disabled || loadingDeleteId === expense.id}
                        destructive
                      />
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
