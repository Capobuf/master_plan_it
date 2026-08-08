import { Link } from "react-router";

import type { Contract } from "../../api/contracts";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import {
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
} from "../ui/table";

interface ContractTableProps {
  contracts: Contract[];
  canEdit: boolean;
  canDelete: boolean;
  onDelete: (contract: Contract) => void;
}

export default function ContractTable({ contracts, canEdit, canDelete, onDelete }: ContractTableProps) {
  return (
    <div className="max-w-full overflow-x-auto">
      <Table>
        <TableHeader className="border-y border-gray-100 dark:border-gray-800">
          <TableRow>
            {(["Title", "Vendor", "Cost center", "Status", "Terms", "Renewal", "Actions"] as const).map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap px-4 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">{heading}</TableCell>)}
          </TableRow>
        </TableHeader>
        <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
          {contracts.map((contract) => (
            <TableRow key={contract.id}>
              <TableCell className="px-4 py-4"><Link to={`/contracts/${contract.id}`} className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{contract.title}</Link><span className="mt-1 block text-xs text-gray-500">#{contract.id}</span></TableCell>
              <TableCell className="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{contract.vendor?.name ?? `#${contract.vendor_id}`}</TableCell>
              <TableCell className="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{contract.cost_center?.name ?? `#${contract.cost_center_id}`}</TableCell>
              <TableCell className="px-4 py-4"><Badge color={contract.active ? "success" : "light"} size="sm">{contract.active ? "Active" : "Inactive"}</Badge></TableCell>
              <TableCell className="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{contract.term_count}</TableCell>
              <TableCell className="whitespace-nowrap px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{contract.renewal_date ?? "—"}</TableCell>
              <TableCell className="px-4 py-4"><div className="flex flex-wrap gap-2"><Link to={`/contracts/${contract.id}`} className="text-sm font-medium text-brand-500 hover:text-brand-600">View</Link>{canEdit ? <Link to={`/contracts/${contract.id}/edit`} className="text-sm font-medium text-gray-600 hover:text-brand-500 dark:text-gray-300">Edit</Link> : null}{canDelete ? <Button size="sm" variant="outline" onClick={() => onDelete(contract)}>Delete</Button> : null}</div></TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
