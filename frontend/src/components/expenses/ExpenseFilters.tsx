import type { ExpenseListParams, ExpenseYearOption } from "../../api/expenses";
import Label from "../form/Label";
import InputField from "../form/input/InputField";
import Select from "../form/Select";

interface ExpenseFiltersProps {
  value: ExpenseListParams;
  yearOptions: ExpenseYearOption[];
  onChange: (next: ExpenseListParams) => void;
  disabled?: boolean;
}

export default function ExpenseFilters({
  value,
  yearOptions,
  onChange,
  disabled = false,
}: ExpenseFiltersProps) {
  return (
    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div className="block">
        <Label>Anno di pianificazione</Label>
        <Select
          key={`expense-planning-year-${value.planning_year_id ?? "all"}`}
          options={[
            { value: "__all__", label: "Tutti gli anni" },
            ...yearOptions.map((year) => ({
              value: String(year.id),
              label: `${year.label}${year.active ? "" : " (inattivo)"}`,
            })),
          ]}
          defaultValue={value.planning_year_id ? String(value.planning_year_id) : "__all__"}
          onChange={(selected) => {
            if (disabled) return;
            onChange({
              ...value,
              planning_year_id: selected === "__all__" ? undefined : Number(selected),
              page: 1,
            });
          }}
          className={disabled ? "pointer-events-none opacity-60" : ""}
        />
      </div>

      <div className="block">
        <Label htmlFor="expense-cost-center">Centro di costo (ID)</Label>
        <InputField
          id="expense-cost-center"
          type="number"
          min="1"
          value={value.cost_center_id ?? ""}
          onChange={(event) =>
            onChange({
              ...value,
              cost_center_id: event.target.value ? Number(event.target.value) : undefined,
              page: 1,
            })
          }
          disabled={disabled}
          placeholder="Tutti i centri"
        />
      </div>

      <div className="block">
        <Label htmlFor="expense-search">Ricerca</Label>
        <InputField
          id="expense-search"
          type="search"
          value={value.q ?? ""}
          onChange={(event) => onChange({ ...value, q: event.target.value || undefined, page: 1 })}
          disabled={disabled}
          placeholder="Titolo spesa"
        />
      </div>

      <div className="block">
        <Label htmlFor="expense-kind">Tipo</Label>
        <Select
          key={`expense-kind-${value.kind ?? "all"}`}
          options={[
            { value: "__all__", label: "Tutti i tipi" },
            { value: "ordinary", label: "Ordinaria" },
            { value: "plafond", label: "Plafond" },
          ]}
          defaultValue={value.kind ?? "__all__"}
          onChange={(selected) => {
            if (disabled) return;
            onChange({ ...value, kind: selected === "__all__" ? undefined : selected, page: 1 });
          }}
          className={disabled ? "pointer-events-none opacity-60" : ""}
        />
      </div>
    </div>
  );
}
