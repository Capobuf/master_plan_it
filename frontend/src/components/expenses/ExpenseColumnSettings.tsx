import { useState } from "react";
import { updateExpenseRegisterPreferences, type ExpenseColumnPreference } from "../../api/expenses";
import { ApiError } from "../../api/client";
import { ArrowDownIcon, ArrowUpIcon, GridIcon } from "../../icons";
import Button from "../ui/button/Button";
import { Dropdown } from "../ui/dropdown/Dropdown";

const labels: Record<ExpenseColumnPreference["key"], string> = {
  kind: "Natura", contract: "Contratto", project: "Progetto", cost_center: "Centro di Costo",
  vendor: "Fornitore", net: "Netto", vat: "IVA", gross: "Lordo", state: "Stato",
};

const defaultColumns: ExpenseColumnPreference[] = [
  { key: "kind", visible: true },
  { key: "contract", visible: true },
  { key: "project", visible: true },
  { key: "cost_center", visible: true },
  { key: "vendor", visible: false },
  { key: "net", visible: true },
  { key: "vat", visible: true },
  { key: "gross", visible: true },
  { key: "state", visible: true },
];

interface Props {
  value: ExpenseColumnPreference[];
  onChange: (columns: ExpenseColumnPreference[]) => void;
  disabled?: boolean;
}

export default function ExpenseColumnSettings({ value, onChange, disabled = false }: Props) {
  const [open, setOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function save(next: ExpenseColumnPreference[]) {
    setSaving(true);
    setError(null);
    try { onChange(await updateExpenseRegisterPreferences(next)); }
    catch (requestError: unknown) { setError(ApiError.from(requestError)); }
    finally { setSaving(false); }
  }

  function move(index: number, offset: -1 | 1) {
    const target = index + offset;
    if (target < 0 || target >= value.length) return;
    const next = [...value];
    [next[index], next[target]] = [next[target], next[index]];
    void save(next);
  }

  function toggle(index: number) {
    const current = value[index];
    if (!current) return;
    const next = value.map((column, columnIndex) => columnIndex === index ? { ...column, visible: !column.visible } : column);
    const hasMoney = next.some((column) => ["net", "vat", "gross"].includes(column.key) && column.visible);
    if (!hasMoney) { setError(new ApiError({ message: "Mantieni visibile almeno una colonna economica." })); return; }
    void save(next);
  }

  return <div className="relative shrink-0">
    <Button type="button" size="sm" variant="outline" className="shrink-0 whitespace-nowrap" startIcon={<GridIcon className="size-4" />} onClick={() => setOpen((current) => !current)} disabled={disabled || saving}>Colonne</Button>
    <Dropdown isOpen={open} onClose={() => setOpen(false)} className="w-80 p-3" triggerId="expense-column-settings">
      <p className="px-2 pb-2 text-sm font-semibold text-gray-800 dark:text-white/90">Visibilità e ordine</p>
      <ul className="space-y-1">
        {value.map((column, index) => <li key={column.key} className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-white/[0.03]">
          <input type="checkbox" checked={column.visible} onChange={() => toggle(index)} disabled={saving} aria-label={`Mostra ${labels[column.key]}`} className="size-4 rounded border-gray-300 text-brand-500" />
          <span className="min-w-0 flex-1 text-sm text-gray-700 dark:text-gray-300">{labels[column.key]}</span>
          <button type="button" onClick={() => move(index, -1)} disabled={saving || index === 0} aria-label={`Sposta ${labels[column.key]} in alto`} className="rounded p-1 text-gray-500 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-800"><ArrowUpIcon className="size-4" /></button>
          <button type="button" onClick={() => move(index, 1)} disabled={saving || index === value.length - 1} aria-label={`Sposta ${labels[column.key]} in basso`} className="rounded p-1 text-gray-500 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-800"><ArrowDownIcon className="size-4" /></button>
        </li>)}
      </ul>
      {error ? <p className="mt-2 text-xs text-error-600 dark:text-error-400">{error.message}</p> : null}
      <Button type="button" size="sm" variant="outline" className="mt-3 w-full" onClick={() => void save(defaultColumns)} disabled={saving}>Ripristina default</Button>
    </Dropdown>
  </div>;
}
