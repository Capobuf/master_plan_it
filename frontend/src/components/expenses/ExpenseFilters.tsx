import type { ExpenseListParams, ExpenseYearOption } from "../../api/expenses";

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
      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
          Anno di pianificazione
        </span>
        <select
          value={value.planning_year_id ?? ""}
          onChange={(event) =>
            onChange({
              ...value,
              planning_year_id: event.target.value ? Number(event.target.value) : undefined,
              page: 1,
            })
          }
          disabled={disabled}
          className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="">Tutti gli anni</option>
          {yearOptions.map((year) => (
            <option key={year.id} value={year.id}>
              {year.label}
              {!year.active ? " (inattivo)" : ""}
            </option>
          ))}
        </select>
      </label>

      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
          Centro di costo (ID)
        </span>
        <input
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
          className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
      </label>

      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
          Ricerca
        </span>
        <input
          type="search"
          value={value.q ?? ""}
          onChange={(event) => onChange({ ...value, q: event.target.value || undefined, page: 1 })}
          disabled={disabled}
          placeholder="Titolo spesa"
          className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
      </label>

      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
          Tipo
        </span>
        <select
          value={value.kind ?? ""}
          onChange={(event) => onChange({ ...value, kind: event.target.value || undefined, page: 1 })}
          disabled={disabled}
          className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="">Tutti i tipi</option>
          <option value="ordinary">Ordinaria</option>
          <option value="plafond">Plafond</option>
        </select>
      </label>
    </div>
  );
}
