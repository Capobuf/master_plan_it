import { useRef, useState } from "react";
import { useDrag, useDrop } from "react-dnd";
import type { ExpenseLookupOption, PlafondExpenseOption } from "../../api/expenses";
import { AngleDownIcon, AngleRightIcon, ChevronDownIcon, ChevronUpIcon, HorizontaLDots, TrashBinIcon } from "../../icons";
import { applyCalculatedAmount, calculateEnteredAmount } from "../../presentation/formatters";
import IconButton from "../common/IconButton";
import Label from "../form/Label";
import Select from "../form/Select";
import Checkbox from "../form/input/Checkbox";
import DecimalInput from "../form/input/DecimalInput";
import InputField from "../form/input/InputField";
import type { ExpenseEditorRow } from "./expenseEditorTypes";

const rowTypeOptions = [{ value: "estimate", label: "Stima" }, { value: "quote", label: "Preventivo" }, { value: "actual", label: "Actual" }];

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

function Field({ label, htmlFor, className = "", children }: { label: string; htmlFor: string; className?: string; children: React.ReactNode }) {
  return <div className={className}><Label htmlFor={htmlFor}>{label}</Label>{children}</div>;
}

function DraggableExpenseRow({ row, index, count, vendors, plafonds, plafondsLoading, onChange, onMove, onRemove, disabled }: { row: ExpenseEditorRow; index: number; count: number; vendors: ExpenseLookupOption[]; plafonds: PlafondExpenseOption[]; plafondsLoading: boolean; onChange: (patch: Partial<ExpenseEditorRow>) => void; onMove: (from: number, to: number) => void; onRemove: () => void; disabled: boolean }) {
  const [detailsOpen, setDetailsOpen] = useState(false);
  const handleRef = useRef<HTMLSpanElement>(null);
  const [, drop] = useDrop<DragItem>({ accept: "EXPENSE_EDITOR_ROW", hover(item) { if (item.index !== index) { onMove(item.index, index); item.index = index; } } });
  const [, drag] = useDrag({ type: "EXPENSE_EDITOR_ROW", item: { index }, canDrag: !disabled });
  drag(drop(handleRef));
  const vendorOptions = vendors.filter((vendor) => vendor.active !== false || vendor.id === row.vendor_id).map((vendor) => ({ value: String(vendor.id), label: vendor.name }));
  const plafondOptions = plafonds.map((plafond) => ({ value: String(plafond.id), label: `${plafond.title}${plafond.cost_center_name ? ` · ${plafond.cost_center_name}` : ""}` }));
  const calculatedAmount = calculateEnteredAmount(row.quantity, row.unit_price);
  const economic = (patch: Partial<Pick<ExpenseEditorRow, "quantity" | "unit_price" | "entered_amount">>) => onChange(applyCalculatedAmount(row, patch));
  const primaryDetailsVisibility = detailsOpen ? "grid" : "hidden lg:grid";
  const rareDetailsVisibility = detailsOpen ? "grid" : "hidden";

  return <fieldset className="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.02]">
    <legend className="sr-only">Riga {index + 1}</legend>
    <div className="mb-3 flex items-center justify-between gap-2">
      <div className="flex items-center gap-2"><span ref={handleRef} className="inline-flex size-8 cursor-grab items-center justify-center rounded-lg border border-dashed border-gray-300 text-gray-500 dark:border-gray-700" title="Trascina per riordinare"><HorizontaLDots className="size-4" /></span><span className="text-sm font-semibold text-gray-800 dark:text-white/90">Riga {index + 1}</span></div>
      <div className="flex items-center gap-1"><IconButton icon={ChevronUpIcon} label={`Sposta la riga ${index + 1} in alto`} onClick={() => onMove(index, index - 1)} disabled={disabled || index === 0} /><IconButton icon={ChevronDownIcon} label={`Sposta la riga ${index + 1} in basso`} onClick={() => onMove(index, index + 1)} disabled={disabled || index === count - 1} /><IconButton icon={TrashBinIcon} label={`Rimuovi la riga ${index + 1}`} onClick={onRemove} disabled={disabled || count === 1} destructive /></div>
    </div>

    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-12">
      <Field label="Tipo" htmlFor={`${row.editorKey}-type`} className="lg:col-span-2"><Select id={`${row.editorKey}-type`} options={rowTypeOptions} value={row.type} onChange={(type) => onChange({ type, ...(type === "actual" ? { is_current_planning: false } : {}) })} disabled={disabled} /></Field>
      <Field label="Fornitore" htmlFor={`${row.editorKey}-vendor`} className="lg:col-span-2"><Select id={`${row.editorKey}-vendor`} options={vendorOptions} value={row.vendor_id ? String(row.vendor_id) : ""} placeholder="Nessun fornitore" allowEmpty onChange={(value) => onChange({ vendor_id: value ? Number(value) : undefined })} disabled={disabled} /></Field>
      <Field label="Descrizione" htmlFor={`${row.editorKey}-description`} className="md:col-span-2 lg:col-span-3"><InputField id={`${row.editorKey}-description`} value={row.description} onChange={(event) => onChange({ description: event.target.value })} disabled={disabled} /></Field>
      <Field label="Importo" htmlFor={`${row.editorKey}-entered-amount`} className="lg:col-span-2"><DecimalInput id={`${row.editorKey}-entered-amount`} value={row.entered_amount} onChange={(entered_amount) => onChange({ entered_amount })} fixedScale={2} maxScale={2} readOnly={calculatedAmount !== null} disabled={disabled} /></Field>
      <Field label="Data" htmlFor={`${row.editorKey}-spend-date`} className="lg:col-span-2"><InputField id={`${row.editorKey}-spend-date`} type="date" value={row.spend_date ?? ""} onChange={(event) => onChange({ spend_date: event.target.value || undefined })} disabled={disabled} /></Field>
      <div className="flex items-end lg:col-span-1"><button type="button" onClick={() => setDetailsOpen((current) => !current)} aria-expanded={detailsOpen} aria-label={`${detailsOpen ? "Nascondi" : "Mostra"} dettagli riga ${index + 1}`} className="inline-flex h-11 w-full items-center justify-center rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">{detailsOpen ? <AngleDownIcon className="size-4" /> : <AngleRightIcon className="size-4" />}<span className="ml-2 lg:sr-only">Dettagli</span></button></div>
    </div>

    <div className={`${primaryDetailsVisibility} mt-3 gap-3 border-t border-gray-100 pt-3 md:grid-cols-2 lg:grid-cols-12 dark:border-gray-800`}>
      <Field label="Q.tà" htmlFor={`${row.editorKey}-quantity`} className="lg:col-span-2"><DecimalInput id={`${row.editorKey}-quantity`} value={row.quantity ?? ""} onChange={(quantity) => economic({ quantity })} maxScale={2} trimTrailingZeros disabled={disabled} /></Field>
      <Field label="Prezzo unitario" htmlFor={`${row.editorKey}-unit-price`} className="lg:col-span-2"><DecimalInput id={`${row.editorKey}-unit-price`} value={row.unit_price ?? ""} onChange={(unit_price) => economic({ unit_price })} maxScale={2} trimTrailingZeros disabled={disabled} /></Field>
      <Field label="IVA" htmlFor={`${row.editorKey}-vat-rate`} className="lg:col-span-2"><DecimalInput id={`${row.editorKey}-vat-rate`} value={row.vat_rate ?? ""} onChange={(vat_rate) => onChange({ vat_rate })} fixedScale={2} maxScale={2} disabled={disabled} /></Field>
      <div className="flex items-end pb-3 lg:col-span-2">{row.type !== "actual" ? <Checkbox label="Corrente" checked={row.is_current_planning ?? false} onChange={(is_current_planning) => onChange({ is_current_planning })} disabled={disabled} /> : <span className="text-xs text-gray-500">Actual non è planning</span>}</div>
    </div>

    <div className={`${rareDetailsVisibility} mt-3 gap-3 border-t border-gray-100 pt-3 md:grid-cols-2 lg:grid-cols-12 dark:border-gray-800`}>
      <div className="flex items-end pb-3 lg:col-span-2"><Checkbox label="IVA inclusa" checked={row.amount_includes_vat} onChange={(amount_includes_vat) => onChange({ amount_includes_vat })} disabled={disabled} /></div>
      <div className="flex items-end pb-3 lg:col-span-2"><Checkbox label="Spesa Extra" checked={row.is_extra} onChange={(is_extra) => onChange({ is_extra, ...(is_extra ? { funded_plafond_expense_id: undefined } : {}) })} disabled={disabled} /></div>
      <Field label="Plafond di riferimento" htmlFor={`${row.editorKey}-funded`} className="md:col-span-2 lg:col-span-6"><Select id={`${row.editorKey}-funded`} options={plafondOptions} value={row.funded_plafond_expense_id ? String(row.funded_plafond_expense_id) : ""} placeholder={plafondsLoading ? "Caricamento Plafond…" : "Nessun Plafond"} allowEmpty onChange={(value) => onChange({ funded_plafond_expense_id: value ? Number(value) : undefined, ...(value ? { is_extra: false } : {}) })} disabled={disabled || plafondsLoading} /></Field>
      <Field label="Riferimento esterno" htmlFor={`${row.editorKey}-external`} className="md:col-span-2 lg:col-span-6"><InputField id={`${row.editorKey}-external`} value={row.external_reference ?? ""} onChange={(event) => onChange({ external_reference: event.target.value })} disabled={disabled} /></Field>
    </div>
  </fieldset>;
}

export default function ExpenseEditorRows({ rows, vendors, plafonds, plafondsLoading = false, onChange, onMove, onRemove, disabled = false }: ExpenseEditorRowsProps) {
  return <div className="space-y-3">{rows.map((row, index) => <DraggableExpenseRow key={row.editorKey} row={row} index={index} count={rows.length} vendors={vendors} plafonds={plafonds} plafondsLoading={plafondsLoading} onChange={(patch) => onChange(index, patch)} onMove={onMove} onRemove={() => onRemove(index)} disabled={disabled} />)}</div>;
}
