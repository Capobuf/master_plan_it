import { Link } from "react-router";
import type { PlafondDetail as PlafondDetailData } from "../../api/plafonds";
import { routes } from "../../navigation/routes";
import { formatDate, formatMoney } from "../../presentation/formatters";
import ComponentCard from "../common/ComponentCard";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";
import PlafondMeasures from "./PlafondMeasures";

export default function PlafondDetail({ plafond }: { plafond: PlafondDetailData }) {
  return <div className="space-y-4"><ComponentCard title="Misure Plafond" compact><PlafondMeasures measures={plafond.measures} currency={plafond.currency} /></ComponentCard><ComponentCard title="Variazioni Allocazione" compact><div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Descrizione", "Data", "Autore", "Importo"].map((heading) => <TableCell key={heading} isHeader>{heading}</TableCell>)}</TableRow></TableHeader><TableBody>{plafond.allocation_adjustments.map((row) => <TableRow key={row.id}><TableCell>{row.description}</TableCell><TableCell>{formatDate(row.date)}</TableCell><TableCell>{row.created_by.name}</TableCell><TableCell>{formatMoney(row.amount.official, plafond.currency)}</TableCell></TableRow>)}</TableBody></Table></div></ComponentCard><ComponentCard title="Righe coperte" compact>{plafond.covered_rows.length === 0 ? <p className="text-sm text-gray-500">Nessuna riga coperta.</p> : <div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Spesa", "Riga", "Tipo", "Centro Riga", "Centro Plafond", "Importo"].map((heading) => <TableCell key={heading} isHeader>{heading}</TableCell>)}</TableRow></TableHeader><TableBody>{plafond.covered_rows.map((row) => <TableRow key={row.row_id}><TableCell><Link className="hover:text-brand-500" to={routes.spesa(row.expense_id)}>{row.expense_title}</Link></TableCell><TableCell>{row.description}</TableCell><TableCell>{row.type}</TableCell><TableCell>{row.expense_cost_center.name}</TableCell><TableCell>{row.plafond_cost_center.name}</TableCell><TableCell>{formatMoney(row.amount.official, plafond.currency)}</TableCell></TableRow>)}</TableBody></Table></div>}</ComponentCard></div>;
}
