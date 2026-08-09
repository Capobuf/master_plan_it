import type { ExpenseListParams, ExpenseYearOption } from "../../api/expenses";
import Label from "../form/Label";
import Select from "../form/Select";

interface ExpenseFiltersProps {
  value: ExpenseListParams;
  yearOptions: ExpenseYearOption[];
  onChange: (next: ExpenseListParams) => void;
  disabled?: boolean;
}

export default function ExpenseFilters({ value, yearOptions, onChange, disabled = false }: ExpenseFiltersProps) {
  return <div className="grid gap-4 md:grid-cols-2">
    <div><Label htmlFor="expense-planning-year">Anno di pianificazione</Label><Select id="expense-planning-year" options={yearOptions.map((year) => ({ value: String(year.id), label: `${year.label}${year.active ? "" : " (inattivo)"}` }))} value={value.planning_year_id ? String(value.planning_year_id) : ""} placeholder="Tutti gli anni" allowEmpty disabled={disabled} onChange={(selected) => onChange({ ...value, planning_year_id: selected ? Number.parseInt(selected, 10) : undefined, page: 1 })} /></div>
    <div><Label htmlFor="expense-kind">Natura</Label><Select id="expense-kind" options={[{ value: "ordinary", label: "Ordinaria" }, { value: "plafond", label: "Plafond" }]} value={value.kind ?? ""} placeholder="Tutte le nature" allowEmpty disabled={disabled} onChange={(kind) => onChange({ ...value, kind: kind || undefined, page: 1 })} /></div>
  </div>;
}
