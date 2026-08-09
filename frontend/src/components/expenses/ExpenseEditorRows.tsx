import { useRef } from "react";
import { useDrag, useDrop } from "react-dnd";
import type { ExpenseLookupOption, PlafondExpenseOption } from "../../api/expenses";
import { ChevronDownIcon, ChevronUpIcon, HorizontaLDots, TrashBinIcon } from "../../icons";
import IconButton from "../common/IconButton";
import DatePicker from "../form/date-picker";
import Label from "../form/Label";
import Select from "../form/Select";
import Checkbox from "../form/input/Checkbox";
import DecimalInput from "../form/input/DecimalInput";
import InputField from "../form/input/InputField";
import type { ExpenseEditorRow } from "./expenseEditorTypes";

const rowTypeOptions = [{ value: "estimate", label: "Stima" }, { value: "quote", label: "Preventivo" }, { value: "actual", label: "Consuntivo" }];

interface ExpenseEditorRowsProps {
  rows: ExpenseEditorRow[];
  vendors: ExpenseLookupOption[];
  plafonds: PlafondExpenseOption[];
  plafondsLoading?: boolean;
  onChange: (index: number, patch: Partial<ExpenseEditorRow>) => void;
  onMove: (from: number, to: number) => void;
  onRemove: (index: number) => void;
  disabled?: boolean;
}

interface DragItem { index: number; }

function DraggableExpenseRow({ row, index, count, vendors, plafonds, plafondsLoading, onChange, onMove, onRemove, disabled }: { row: ExpenseEditorRow; index: number; count: number; vendors: ExpenseLookupOption[]; plafonds: PlafondExpenseOption[]; plafondsLoading: boolean; onChange: (patch: Partial<ExpenseEditorRow>) => void; onMove: (from: number, to: number) => void; onRemove: () => void; disabled: boolean }) {
  const handleRef = useRef<HTMLSpanElement>(null);
  const [, drop] = useDrop<DragItem>({ accept: "EXPENSE_EDITOR_ROW", hover(item) { if (item.index !== index) { onMove(item.index, index); item.index = index; } } });
  const [, drag] = useDrag({ type: "EXPENSE_EDITOR_ROW", item: { index }, canDrag: !disabled });
  drag(drop(handleRef));
  const vendorOptions = vendors.filter((vendor) => vendor.active !== false || vendor.id === row.vendor_id).map((vendor) => ({ value: String(vendor.id), label: vendor.name }));
  const plafondOptions = plafonds.filter((plafond) => plafond.id !== row.id).map((plafond) => ({ value: String(plafond.id), label: `${plafond.title}${plafond.cost_center_name ? ` · ${plafond.cost_center_name}` : ""}` }));

  return <fieldset className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02] sm:p-5">
    <legend className="sr-only">Riga {index + 1}</legend>
    <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
      <div className="flex items-center gap-3"><span ref={handleRef} className="inline-flex h-10 w-10 cursor-grab items-center justify-center rounded-lg border border-dashed border-gray-300 text-gray-500 dark:border-gray-700 dark:text-gray-400" title="Trascina per riordinare" aria-label={`Trascina la riga ${index + 1}`}><HorizontaLDots className="h-5 w-5" /></span><div><h3 className="font-semibold text-gray-800 dark:text-white/90">Riga {index + 1}</h3><p className="text-xs text-gray-500 dark:text-gray-400">{row.id ? "Riga esistente" : "Nuova riga"}</p></div></div>
      <div className="flex items-center gap-2"><IconButton icon={ChevronUpIcon} label={`Sposta la riga ${index + 1} in alto`} onClick={() => onMove(index, index - 1)} disabled={disabled || index === 0} /><IconButton icon={ChevronDownIcon} label={`Sposta la riga ${index + 1} in basso`} onClick={() => onMove(index, index + 1)} disabled={disabled || index === count - 1} /><IconButton icon={TrashBinIcon} label={`Rimuovi la riga ${index + 1}`} onClick={onRemove} disabled={disabled || count === 1} destructive /></div>
    </div>

    <div className="space-y-6">
      <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Classificazione</h4><div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-12">
        <div className="xl:col-span-3"><Label htmlFor={`${row.editorKey}-type`}>Tipo</Label><Select id={`${row.editorKey}-type`} options={rowTypeOptions} value={row.type} onChange={(type) => onChange({ type })} disabled={disabled} /></div>
        <div className="xl:col-span-4"><Label htmlFor={`${row.editorKey}-vendor`}>Fornitore</Label><Select id={`${row.editorKey}-vendor`} options={vendorOptions} value={row.vendor_id ? String(row.vendor_id) : ""} placeholder="Nessun fornitore" allowEmpty onChange={(value) => onChange({ vendor_id: value ? Number.parseInt(value, 10) : undefined })} disabled={disabled} /></div>
        <div className="md:col-span-2 xl:col-span-5"><Label htmlFor={`${row.editorKey}-description`}>Descrizione</Label><InputField id={`${row.editorKey}-description`} value={row.description} onChange={(event) => onChange({ description: event.target.value })} disabled={disabled} /></div>
      </div></section>

      {row.type !== "actual" ? <section><Checkbox label="Pianificazione corrente" checked={row.is_current_planning ?? false} onChange={(is_current_planning) => onChange({ is_current_planning })} disabled={disabled} /></section> : null}

      <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Quantità e Importi</h4><div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-12">
        <div className="xl:col-span-2"><Label htmlFor={`${row.editorKey}-quantity`}>Quantità</Label><DecimalInput id={`${row.editorKey}-quantity`} value={row.quantity ?? ""} onChange={(quantity) => onChange({ quantity })} trimTrailingZeros disabled={disabled} /></div>
        <div className="xl:col-span-2"><Label htmlFor={`${row.editorKey}-unit-price`}>Prezzo unitario</Label><DecimalInput id={`${row.editorKey}-unit-price`} value={row.unit_price ?? ""} onChange={(unit_price) => onChange({ unit_price })} trimTrailingZeros disabled={disabled} /></div>
        <div className="xl:col-span-2"><Label htmlFor={`${row.editorKey}-entered-amount`}>Importo</Label><DecimalInput id={`${row.editorKey}-entered-amount`} value={row.entered_amount} onChange={(entered_amount) => onChange({ entered_amount })} fixedScale={2} disabled={disabled} /></div>
        <div className="xl:col-span-2"><Label htmlFor={`${row.editorKey}-vat-rate`}>Aliquota IVA</Label><DecimalInput id={`${row.editorKey}-vat-rate`} value={row.vat_rate ?? ""} onChange={(vat_rate) => onChange({ vat_rate })} fixedScale={2} disabled={disabled} /></div>
        <div className="flex items-center xl:col-span-2"><Checkbox label="Importo IVA inclusa" checked={row.amount_includes_vat} onChange={(amount_includes_vat) => onChange({ amount_includes_vat })} disabled={disabled} /></div>
        <div className="flex items-center xl:col-span-2"><Checkbox label="Spesa Extra" checked={row.is_extra} onChange={(is_extra) => onChange({ is_extra })} disabled={disabled} /></div>
      </div></section>

      <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Competenza</h4><div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-12">
        <div className="xl:col-span-4"><DatePicker id={`${row.editorKey}-spend-date`} label={row.type === "actual" ? "Data Actual (obbligatoria)" : "Data pianificata (opzionale)"} placeholder="Seleziona la data" defaultDate={row.spend_date || undefined} onChange={(_, value) => onChange({ spend_date: value || undefined })} disabled={disabled} /></div>
      </div></section>

      <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Finanziamento</h4><div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div><Label htmlFor={`${row.editorKey}-funded`}>Plafond di riferimento</Label><Select id={`${row.editorKey}-funded`} options={plafondOptions} value={row.funded_plafond_expense_id ? String(row.funded_plafond_expense_id) : ""} placeholder={plafondsLoading ? "Caricamento Plafond…" : plafondOptions.length ? "Nessun Plafond" : "Nessun Plafond disponibile"} allowEmpty onChange={(value) => onChange({ funded_plafond_expense_id: value ? Number.parseInt(value, 10) : undefined })} disabled={disabled || plafondsLoading || plafondOptions.length === 0} /></div>
        <div><Label htmlFor={`${row.editorKey}-external`}>Riferimento esterno</Label><InputField id={`${row.editorKey}-external`} value={row.external_reference ?? ""} onChange={(event) => onChange({ external_reference: event.target.value })} disabled={disabled} placeholder="Riferimento opzionale" /></div>
      </div></section>
    </div>
  </fieldset>;
}

export default function ExpenseEditorRows({ rows, vendors, plafonds, plafondsLoading = false, onChange, onMove, onRemove, disabled = false }: ExpenseEditorRowsProps) {
  return <div className="space-y-5">{rows.map((row, index) => <DraggableExpenseRow key={row.editorKey} row={row} index={index} count={rows.length} vendors={vendors} plafonds={plafonds} plafondsLoading={plafondsLoading} onChange={(patch) => onChange(index, patch)} onMove={onMove} onRemove={() => onRemove(index)} disabled={disabled} />)}</div>;
}
