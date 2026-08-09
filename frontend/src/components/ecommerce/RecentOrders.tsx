import { Link } from "react-router";
import { formatDate } from "../../presentation/formatters";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

export interface RecentOrderItem {
  id: number;
  label: string;
  date?: string;
  href: string;
}

export default function RecentOrders({ items, title = "Spese Recenti" }: { items: RecentOrderItem[]; title?: string }) {
  return (
    <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white px-4 pb-3 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6">
      <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">{title}</h2>
      <div className="max-w-full overflow-x-auto">
        <Table>
          <TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow>
            <TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Spesa</TableCell>
            <TableCell isHeader className="py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">Data</TableCell>
          </TableRow></TableHeader>
          <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
            {items.map((item) => <TableRow key={item.id}>
              <TableCell className="py-3"><Link to={item.href} className="text-sm font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">{item.label}</Link></TableCell>
              <TableCell className="py-3 text-sm text-gray-500 dark:text-gray-400">{formatDate(item.date)}</TableCell>
            </TableRow>)}
          </TableBody>
        </Table>
      </div>
    </section>
  );
}
