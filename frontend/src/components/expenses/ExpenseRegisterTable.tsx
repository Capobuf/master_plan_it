import { Fragment, useEffect, useMemo, useRef, useState } from "react";
import { Link, useNavigate } from "react-router";
import { ApiError } from "../../api/client";
import { getExpense, type ExpenseColumnKey, type ExpenseColumnPreference, type ExpenseDetail, type ExpenseRegisterItem, type ExpenseYearOption } from "../../api/expenses";
import { AngleDownIcon, AngleRightIcon, MoreDotIcon } from "../../icons";
import { routes } from "../../navigation/routes";
import ExpenseActionModal from "./ExpenseActionModal";
import ExpenseBulkActions from "./ExpenseBulkActions";
import ExpenseColumnSettings from "./ExpenseColumnSettings";
import ExpenseKindBadge from "./ExpenseKindBadge";
import ExpenseMoney from "./ExpenseMoney";
import ExpenseRowsTable from "./ExpenseRowsTable";
import { Dropdown } from "../ui/dropdown/Dropdown";
import { DropdownItem } from "../ui/dropdown/DropdownItem";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

const headings: Record<ExpenseColumnKey, string> = {
  kind: "Natura", contract: "Contratto", project: "Progetto", cost_center: "Centro di Costo",
  vendor: "Fornitore", net: "Netto", vat: "IVA", gross: "Lordo", state: "Stato",
};

interface Props {
  expenses: ExpenseRegisterItem[];
  planningYearId: number;
  planningYears: ExpenseYearOption[];
  columnPreferences: ExpenseColumnPreference[];
  canEdit: boolean;
  canCreate: boolean;
  canDelete: boolean;
  canViewProjects: boolean;
  onChanged: () => void;
  onPlanningYearChange: (planningYearId: number) => void;
  disabled?: boolean;
}

function RowActions({ expense, planningYearId, canEdit, canDelete, disabled, onDelete }: { expense: ExpenseRegisterItem; planningYearId: number; canEdit: boolean; canDelete: boolean; disabled: boolean; onDelete: (detail: ExpenseDetail) => void }) {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const navigate = useNavigate();
  const menuId = `expense-actions-${expense.id}`;
  async function prepareDelete() {
    setLoading(true); setError(null);
    try { onDelete(await getExpense(expense.id, planningYearId)); setOpen(false); }
    catch (requestError: unknown) { setError(ApiError.from(requestError)); }
    finally { setLoading(false); }
  }
  return <div className="relative">
    <button type="button" aria-label={`Azioni ${expense.title}`} aria-controls={menuId} aria-expanded={open} onClick={() => setOpen((current) => !current)} disabled={disabled || loading} className="inline-flex size-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-white/[0.05]"><MoreDotIcon className="size-5" /></button>
    <Dropdown isOpen={open} onClose={() => setOpen(false)} triggerId={menuId} className="w-48 overflow-hidden py-1">
      <DropdownItem tag="a" to={routes.spesa(expense.id)} onItemClick={() => setOpen(false)} className="dark:text-gray-300 dark:hover:bg-white/[0.05]">Apri dettaglio</DropdownItem>
      {canEdit ? <DropdownItem onClick={() => navigate(routes.modificaSpesa(expense.id))} onItemClick={() => setOpen(false)} className="dark:text-gray-300 dark:hover:bg-white/[0.05]">Modifica</DropdownItem> : null}
      {canDelete ? <DropdownItem onClick={() => void prepareDelete()} className="text-error-600 hover:bg-error-50 dark:text-error-400 dark:hover:bg-error-500/10">Elimina</DropdownItem> : null}
      {error ? <p className="px-3 py-2 text-xs text-error-600">{error.message}</p> : null}
    </Dropdown>
  </div>;
}

export default function ExpenseRegisterTable({ expenses, planningYearId, planningYears, columnPreferences, canEdit, canCreate, canDelete, canViewProjects, onChanged, onPlanningYearChange, disabled = false }: Props) {
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [columns, setColumns] = useState(columnPreferences);
  const [expanded, setExpanded] = useState<Set<number>>(new Set());
  const [details, setDetails] = useState<Record<number, ExpenseDetail>>({});
  const [detailErrors, setDetailErrors] = useState<Record<number, ApiError>>({});
  const [loadingIds, setLoadingIds] = useState<Set<number>>(new Set());
  const [deleteTarget, setDeleteTarget] = useState<ExpenseDetail | null>(null);
  const headerCheckbox = useRef<HTMLInputElement>(null);
  const visibleColumns = columns.filter((column) => column.visible);
  const allSelected = selectedIds.size === expenses.length && expenses.length > 0;
  const selected = useMemo(() => expenses.filter((expense) => selectedIds.has(expense.id)), [expenses, selectedIds]);

  useEffect(() => { if (headerCheckbox.current) headerCheckbox.current.indeterminate = selectedIds.size > 0 && !allSelected; }, [allSelected, selectedIds.size]);

  async function loadDetail(expense: ExpenseRegisterItem) {
    if (details[expense.id] || loadingIds.has(expense.id)) return;
    setLoadingIds((current) => new Set(current).add(expense.id));
    try {
      const detail = await getExpense(expense.id, planningYearId);
      setDetails((current) => ({ ...current, [expense.id]: detail }));
      setDetailErrors((current) => { const next = { ...current }; delete next[expense.id]; return next; });
    } catch (requestError: unknown) { setDetailErrors((current) => ({ ...current, [expense.id]: ApiError.from(requestError) })); }
    finally { setLoadingIds((current) => { const next = new Set(current); next.delete(expense.id); return next; }); }
  }

  function toggleExpansion(expense: ExpenseRegisterItem) {
    if (expanded.has(expense.id)) { setExpanded((current) => { const next = new Set(current); next.delete(expense.id); return next; }); return; }
    setExpanded((current) => new Set(current).add(expense.id));
    void loadDetail(expense);
  }

  function renderColumn(expense: ExpenseRegisterItem, key: ExpenseColumnKey) {
    if (key === "kind") return <ExpenseKindBadge kind={expense.kind} />;
    if (key === "contract") return expense.contract_id && expense.contract_title ? <Link to={routes.contratto(expense.contract_id)} className="font-medium hover:text-brand-500">{expense.contract_title}</Link> : "—";
    if (key === "project") return expense.project_id && expense.project_title ? canViewProjects ? <Link to={routes.progetto(expense.project_id)} className="font-medium hover:text-brand-500">{expense.project_title}</Link> : expense.project_title : "—";
    if (key === "cost_center") return expense.cost_center_name ?? "—";
    if (key === "vendor") return expense.vendor_summary;
    if (key === "state") return <span className={`rounded-full px-2 py-1 text-xs font-medium ${expense.state === "open" ? "bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400" : "bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300"}`}>{expense.state === "open" ? "Aperta" : "Chiusa"}</span>;
    return <ExpenseMoney money={expense.totals} component={key} />;
  }

  return <>
    <div className="space-y-3 border-b border-gray-100 p-3 dark:border-white/[0.05]">
      <div className="flex justify-end"><ExpenseColumnSettings value={columns} onChange={setColumns} disabled={disabled} /></div>
      <ExpenseBulkActions selected={selected} planningYearId={planningYearId} planningYears={planningYears} canEdit={canEdit} canCreate={canCreate} canDelete={canDelete} onChanged={onChanged} onPlanningYearChange={onPlanningYearChange} />
    </div>
    <div className="max-w-full overflow-x-auto">
      <Table className="text-sm">
        <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]"><TableRow>
          <TableCell isHeader className="w-10 px-2 py-2"><input ref={headerCheckbox} type="checkbox" aria-label="Seleziona tutte le Spese della pagina" checked={allSelected} onChange={(event) => setSelectedIds(event.target.checked ? new Set(expenses.map(({ id }) => id)) : new Set())} /></TableCell>
          <TableCell isHeader className="w-10 px-2 py-2"><span className="sr-only">Espandi</span></TableCell>
          <TableCell isHeader className="min-w-44 px-3 py-2 text-start text-theme-xs font-medium text-gray-500">Spesa</TableCell>
          {visibleColumns.map((column) => <TableCell key={column.key} isHeader className={`whitespace-nowrap px-3 py-2 text-theme-xs font-medium text-gray-500 ${["net", "vat", "gross"].includes(column.key) ? "text-end" : "text-start"}`}>{headings[column.key]}</TableCell>)}
          <TableCell isHeader className="w-12 px-2 py-2"><span className="sr-only">Azioni</span></TableCell>
        </TableRow></TableHeader>
        <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
          {expenses.map((expense) => <Fragment key={expense.id}>
            <TableRow className={selectedIds.has(expense.id) ? "bg-brand-50/50 dark:bg-brand-500/[0.06]" : ""}>
              <TableCell className="px-2 py-2"><input type="checkbox" aria-label={`Seleziona ${expense.title}`} checked={selectedIds.has(expense.id)} onChange={(event) => setSelectedIds((current) => { const next = new Set(current); if (event.target.checked) next.add(expense.id); else next.delete(expense.id); return next; })} /></TableCell>
              <TableCell className="px-2 py-2"><button type="button" aria-label={`${expanded.has(expense.id) ? "Comprimi" : "Espandi"} righe di ${expense.title}`} aria-expanded={expanded.has(expense.id)} onClick={() => toggleExpansion(expense)} className="inline-flex size-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]">{expanded.has(expense.id) ? <AngleDownIcon className="size-4" /> : <AngleRightIcon className="size-4" />}</button></TableCell>
              <TableCell className="px-3 py-2"><Link to={routes.spesa(expense.id)} className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{expense.title}</Link><span className="block text-xs text-gray-500">{expense.row_count} {expense.row_count === 1 ? "riga" : "righe"}</span></TableCell>
              {visibleColumns.map((column) => <TableCell key={column.key} className={`whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-300 ${["net", "vat", "gross"].includes(column.key) ? "text-end" : "text-start"}`}>{renderColumn(expense, column.key)}</TableCell>)}
              <TableCell className="px-2 py-2"><RowActions expense={expense} planningYearId={planningYearId} canEdit={canEdit} canDelete={canDelete} disabled={disabled} onDelete={setDeleteTarget} /></TableCell>
            </TableRow>
            {expanded.has(expense.id) ? <TableRow className="bg-gray-50/70 dark:bg-white/[0.02]"><td colSpan={visibleColumns.length + 4} className="p-3 pl-12">{loadingIds.has(expense.id) ? <p className="text-sm text-gray-500">Caricamento righe…</p> : detailErrors[expense.id] ? <div className="flex items-center gap-3 text-sm text-error-600"><span>{detailErrors[expense.id].message}</span><button type="button" className="font-medium underline" onClick={() => { setDetailErrors((current) => { const next = { ...current }; delete next[expense.id]; return next; }); void loadDetail(expense); }}>Riprova</button></div> : details[expense.id] ? <ExpenseRowsTable rows={details[expense.id].rows} compact /> : null}</td></TableRow> : null}
          </Fragment>)}
        </TableBody>
      </Table>
    </div>
    {deleteTarget ? <ExpenseActionModal expenseId={deleteTarget.id} lockVersion={deleteTarget.lock_version} generated={deleteTarget.rows.some((row) => row.generated)} contractId={deleteTarget.contract_id} isOpen onClose={() => setDeleteTarget(null)} onDeleted={() => { setDeleteTarget(null); onChanged(); }} /> : null}
  </>;
}
