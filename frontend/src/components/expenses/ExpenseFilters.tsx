import { useEffect, useState, type ReactNode } from "react";
import type {
  ExpenseContractOption,
  ExpenseListParams,
  ExpenseLookupOption,
} from "../../api/expenses";
import type { ProjectLookupOption } from "../../api/projects";
import InputField from "../form/input/InputField";
import Select from "../form/Select";

interface ExpenseFiltersProps {
  value: ExpenseListParams;
  costCenters: ExpenseLookupOption[];
  vendors: ExpenseLookupOption[];
  projects: ProjectLookupOption[];
  contracts: ExpenseContractOption[];
  showCostCenters: boolean;
  showVendors: boolean;
  showProjects: boolean;
  showContracts: boolean;
  actions?: ReactNode;
  onChange: (next: ExpenseListParams) => void;
  disabled?: boolean;
}

export default function ExpenseFilters({
  value,
  costCenters,
  vendors,
  projects,
  contracts,
  showCostCenters,
  showVendors,
  showProjects,
  showContracts,
  actions,
  onChange,
  disabled = false,
}: ExpenseFiltersProps) {
  const [search, setSearch] = useState(value.q ?? "");

  useEffect(() => setSearch(value.q ?? ""), [value.q]);
  useEffect(() => {
    if (search === (value.q ?? "")) return;
    const timer = window.setTimeout(() => {
      onChange({ ...value, q: search.trim() || undefined, page: 1 });
    }, 300);
    return () => window.clearTimeout(timer);
  }, [onChange, search, value]);

  const select = (patch: Partial<ExpenseListParams>) =>
    onChange({ ...value, ...patch, page: 1 });

  return (
    <div
      data-expense-filters
      className={`grid items-center gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 ${
        actions
          ? "2xl:grid-cols-[minmax(12rem,1.35fr)_repeat(7,minmax(0,1fr))]"
          : "2xl:grid-cols-[minmax(12rem,1.35fr)_repeat(6,minmax(0,1fr))]"
      }`}
    >
      <InputField
        id="expense-search"
        value={search}
        onChange={(event) => setSearch(event.target.value)}
        placeholder="Cerca Spesa"
        ariaLabel="Cerca Spesa"
        disabled={disabled}
      />
      <Select
        id="expense-kind"
        options={[{ value: "ordinary", label: "Ordinaria" }, { value: "plafond", label: "Plafond" }]}
        value={value.kind ?? ""}
        placeholder="Tutte le nature"
        ariaLabel="Natura"
        allowEmpty
        disabled={disabled}
        onChange={(kind) => select({ kind: kind || undefined })}
      />
      {showCostCenters ? <Select id="expense-cost-center" options={costCenters.map((item) => ({ value: String(item.id), label: item.name }))} value={value.cost_center_id ? String(value.cost_center_id) : ""} placeholder="Tutti i centri di costo" ariaLabel="Centro di Costo" allowEmpty disabled={disabled} onChange={(id) => select({ cost_center_id: id ? Number(id) : undefined })} /> : null}
      {showVendors ? <Select id="expense-vendor" options={vendors.map((item) => ({ value: String(item.id), label: item.name }))} value={value.vendor_id ? String(value.vendor_id) : ""} placeholder="Tutti i fornitori" ariaLabel="Fornitore" allowEmpty disabled={disabled} onChange={(id) => select({ vendor_id: id ? Number(id) : undefined })} /> : null}
      {showProjects ? <Select id="expense-project" options={projects.map((item) => ({ value: String(item.id), label: item.title }))} value={value.project_id ? String(value.project_id) : ""} placeholder="Tutti i progetti" ariaLabel="Progetto" allowEmpty disabled={disabled} onChange={(id) => select({ project_id: id ? Number(id) : undefined })} /> : null}
      {showContracts ? <Select id="expense-contract" options={contracts.map((item) => ({ value: String(item.id), label: item.title }))} value={value.contract_id ? String(value.contract_id) : ""} placeholder="Tutti i contratti" ariaLabel="Contratto" allowEmpty disabled={disabled} onChange={(id) => select({ contract_id: id ? Number(id) : undefined })} /> : null}
      <Select id="expense-state" options={[{ value: "open", label: "Aperte" }, { value: "closed", label: "Chiuse" }]} value={value.state ?? ""} placeholder="Tutti gli stati" ariaLabel="Stato" allowEmpty disabled={disabled} onChange={(state) => select({ state: state ? state as "open" | "closed" : undefined })} />
      {actions ? <div className="flex justify-end">{actions}</div> : null}
    </div>
  );
}
