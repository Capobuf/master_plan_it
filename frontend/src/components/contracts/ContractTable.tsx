import { Link, useNavigate } from "react-router";
import type { Contract } from "../../api/contracts";
import { PencilIcon, TrashBinIcon } from "../../icons";
import { routes } from "../../navigation/routes";
import { formatDate } from "../../presentation/formatters";
import IconButton from "../common/IconButton";
import Badge from "../ui/badge/Badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

export default function ContractTable({ contracts, canEdit, canDelete, onDelete }: { contracts: Contract[]; canEdit: boolean; canDelete: boolean; onDelete: (contract: Contract) => void }) {
  const navigate = useNavigate();
  return <div className="max-w-full overflow-x-auto"><Table>
    <TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow>{["Titolo", "Fornitore", "Centro di costo", "Stato", "Termini", "Rinnovo", "Azioni"].map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap px-4 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">{heading}</TableCell>)}</TableRow></TableHeader>
    <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{contracts.map((contract) => <TableRow key={contract.id}>
      <TableCell className="px-4 py-3"><Link to={routes.contratto(contract.id)} className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">{contract.title}</Link></TableCell>
      <TableCell className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{contract.vendor?.name ?? "—"}</TableCell><TableCell className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{contract.cost_center?.name ?? "—"}</TableCell>
      <TableCell className="px-4 py-3"><Badge color={contract.active ? "success" : "light"} size="sm">{contract.active ? "Attivo" : "Inattivo"}</Badge></TableCell><TableCell className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{contract.term_count}</TableCell><TableCell className="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{formatDate(contract.renewal_date)}</TableCell>
      <TableCell className="px-4 py-3"><div className="flex items-center gap-2">{canEdit ? <IconButton icon={PencilIcon} label={`Modifica ${contract.title}`} onClick={() => navigate(routes.modificaContratto(contract.id))} /> : null}{canDelete ? <IconButton icon={TrashBinIcon} label={`Elimina ${contract.title}`} onClick={() => onDelete(contract)} destructive /> : null}</div></TableCell>
    </TableRow>)}</TableBody>
  </Table></div>;
}
