import type { ExpenseRow } from "../../api/expenses";
import { formatDate, formatDecimal, formatMoney, formatPercentage } from "../../presentation/formatters";
import { domainLabel } from "../../presentation/labels";
import Badge from "../ui/badge/Badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";
import ExpenseMoney from "./ExpenseMoney";

export default function ExpenseRowsTable({ rows, compact = false }: { rows: ExpenseRow[]; compact?: boolean }) {
  if (rows.length === 0) return <p className="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700">Questa Spesa non contiene righe correnti.</p>;
  const cell = compact ? "px-2 py-2" : "px-3 py-3";
  return <div className="max-w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-white/[0.05]">
    <Table className="text-sm"><TableHeader className="border-b border-gray-100 dark:border-white/[0.05]"><TableRow>
      {["Tipo", "Fornitore", "Descrizione", "Q.tà", "Prezzo unitario", "Importo", "IVA", "Data", "Pianificazione", "Netto", "Lordo"].map((heading) => <TableCell key={heading} isHeader className={`${cell} whitespace-nowrap text-start text-theme-xs font-medium text-gray-500 ${["Q.tà", "Prezzo unitario", "Netto", "Lordo"].includes(heading) ? "hidden lg:table-cell" : ""}`}>{heading}</TableCell>)}
    </TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
      {rows.map((row) => <TableRow key={row.id}>
        <TableCell className={`${cell} whitespace-nowrap font-medium`}>{domainLabel(row.type)}{row.generated ? <span className="block text-xs font-normal text-gray-500">Generata</span> : null}</TableCell>
        <TableCell className={`${cell} min-w-32`}>{row.vendor_name ?? "—"}</TableCell>
        <TableCell className={`${cell} min-w-52`}>{row.description}{row.notes ? <span className="block text-xs text-gray-500">{row.notes}</span> : null}{row.funded_plafond ? <span className="mt-1 block text-xs text-brand-600 dark:text-brand-300">Copertura integrale: {row.funded_plafond.title}<br />Centro Plafond: {row.funded_plafond.cost_center.name}</span> : null}</TableCell>
        <TableCell className={`${cell} hidden whitespace-nowrap lg:table-cell`}>{formatDecimal(row.quantity)}</TableCell>
        <TableCell className={`${cell} hidden whitespace-nowrap lg:table-cell`}>{formatMoney(row.unit_price, "EUR")}</TableCell>
        <TableCell className={`${cell} whitespace-nowrap`}>{formatMoney(row.entered_amount, "EUR")}</TableCell>
        <TableCell className={`${cell} whitespace-nowrap`}>{formatPercentage(row.vat_rate ?? "0.00")}{row.amount_includes_vat ? <span className="block text-xs text-gray-500">inclusa</span> : null}</TableCell>
        <TableCell className={`${cell} whitespace-nowrap`}>{row.spend_date ? formatDate(row.spend_date) : "—"}</TableCell>
        <TableCell className={cell}>{row.is_current_planning ? <Badge color="success">Corrente</Badge> : row.type === "actual" ? <Badge color="info">Actual</Badge> : <span className="text-gray-500">Alternativa</span>}</TableCell>
        <TableCell className={`${cell} hidden whitespace-nowrap lg:table-cell`}><ExpenseMoney money={row.amount} component="net" /></TableCell>
        <TableCell className={`${cell} hidden whitespace-nowrap font-medium lg:table-cell`}><ExpenseMoney money={row.amount} component="gross" /></TableCell>
      </TableRow>)}
    </TableBody></Table>
  </div>;
}
