import { Link } from "react-router";
import type { DashboardRecentExpense } from "../../api/dashboard";
import { routes } from "../../navigation/routes";
import { formatDateTime, formatMoney } from "../../presentation/formatters";
import Badge from "../ui/badge/Badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

export default function RecentOrders({ items, currency }: { items: DashboardRecentExpense[]; currency: string }) {
  return (
    <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white px-3 pb-2 pt-4 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pb-3 sm:pt-5">
      <h2 className="mb-3 text-base font-semibold text-gray-800 dark:text-white/90 sm:mb-4 sm:text-lg">Ultime Spese</h2>
      <div className="max-w-full overflow-x-auto">
        <Table className="table-fixed sm:table-auto">
          <TableHeader className="border-y border-gray-100 dark:border-gray-800">
            <TableRow>
              <TableCell isHeader className="w-[54%] py-2 pr-2 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:w-auto sm:py-2.5 sm:pr-4">Spesa</TableCell>
              <TableCell isHeader className="hidden py-2.5 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400 md:table-cell">Centro di Costo</TableCell>
              <TableCell isHeader className="hidden py-2.5 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400 xl:table-cell">Fornitore</TableCell>
              <TableCell isHeader className="hidden py-2.5 pr-4 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400 2xl:table-cell">Progetto</TableCell>
              <TableCell isHeader className="hidden py-2.5 pr-4 text-end text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:table-cell">Pianificato</TableCell>
              <TableCell isHeader className="w-[26%] py-2 pr-2 text-end text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:w-auto sm:py-2.5 sm:pr-4">Actual</TableCell>
              <TableCell isHeader className="w-[20%] py-2 text-end text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:w-auto sm:py-2.5">Stato</TableCell>
            </TableRow>
          </TableHeader>
          <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
            {items.map((item) => (
              <TableRow key={item.id}>
                <TableCell className="py-2 pr-2 sm:py-2.5 sm:pr-4">
                  <Link to={routes.spesa(item.id)} className="line-clamp-2 text-sm font-medium leading-5 text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400 sm:max-w-[220px]">{item.label}</Link>
                  <span className="mt-0.5 block text-theme-xs text-gray-400 dark:text-gray-500">{formatDateTime(item.date)}</span>
                </TableCell>
                <TableCell className="hidden max-w-[150px] truncate py-2.5 pr-4 text-sm text-gray-600 dark:text-gray-300 md:table-cell">{item.cost_center}</TableCell>
                <TableCell className="hidden max-w-[150px] truncate py-2.5 pr-4 text-sm text-gray-600 dark:text-gray-300 xl:table-cell">{item.vendor ?? "—"}</TableCell>
                <TableCell className="hidden max-w-[150px] truncate py-2.5 pr-4 text-sm text-gray-600 dark:text-gray-300 2xl:table-cell">{item.project ?? "Senza progetto"}</TableCell>
                <TableCell className="hidden whitespace-nowrap py-2.5 pr-4 text-end text-sm text-gray-700 dark:text-gray-300 sm:table-cell">{formatMoney(item.planned, currency)}</TableCell>
                <TableCell className="whitespace-nowrap py-2 pr-2 text-end text-xs font-medium text-gray-800 dark:text-white/90 sm:py-2.5 sm:pr-4 sm:text-sm">{formatMoney(item.actual, currency)}</TableCell>
                <TableCell className="py-2 text-end sm:py-2.5"><Badge color={item.state === "open" ? "info" : "success"} size="sm">{item.state === "open" ? "Aperta" : "Chiusa"}</Badge></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </section>
  );
}
