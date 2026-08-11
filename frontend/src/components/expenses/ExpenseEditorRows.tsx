import { useRef, useState } from "react";
import { useDrag, useDrop } from "react-dnd";
import type { ExpenseLookupOption, PlafondExpenseOption } from "../../api/expenses";
import { AngleRightIcon, ChevronDownIcon, ChevronUpIcon, HorizontaLDots, TrashBinIcon } from "../../icons";
import { applyCalculatedAmount, calculateEnteredAmount } from "../../presentation/formatters";
import IconButton from "../common/IconButton";
import Label from "../form/Label";
import Select from "../form/Select";
import Checkbox from "../form/input/Checkbox";
import DecimalInput from "../form/input/DecimalInput";
import InputField from "../form/input/InputField";
import type { ExpenseEditorRow } from "./expenseEditorTypes";

const rowTypeOptions = [
  { value: "estimate", label: "Stima" },
  { value: "quote", label: "Preventivo" },
  { value: "actual", label: "Actual" },
];

const desktopGrid = "lg:grid-cols-[2.25rem_minmax(6.5rem,0.9fr)_minmax(8.5rem,1.25fr)_minmax(4rem,0.55fr)_minmax(6rem,0.8fr)_minmax(6rem,0.8fr)_minmax(4.5rem,0.55fr)_minmax(7.5rem,0.85fr)_3rem_2.5rem_2.5rem]";

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

interface DragItem {
  index: number;
}

function Field({
  label,
  htmlFor,
  className = "",
  labelClassName = "",
  children,
}: {
  label: string;
  htmlFor?: string;
  className?: string;
  labelClassName?: string;
  children: React.ReactNode;
}) {
  return (
    <div className={`min-w-0 ${className}`}>
      <Label htmlFor={htmlFor} className={labelClassName}>{label}</Label>
      {children}
    </div>
  );
}

function DraggableExpenseRow({
  row,
  index,
  count,
  vendors,
  plafonds,
  plafondsLoading,
  onChange,
  onMove,
  onRemove,
  disabled,
}: {
  row: ExpenseEditorRow;
  index: number;
  count: number;
  vendors: ExpenseLookupOption[];
  plafonds: PlafondExpenseOption[];
  plafondsLoading: boolean;
  onChange: (patch: Partial<ExpenseEditorRow>) => void;
  onMove: (from: number, to: number) => void;
  onRemove: () => void;
  disabled: boolean;
}) {
  const [detailsOpen, setDetailsOpen] = useState(false);
  const handleRef = useRef<HTMLSpanElement>(null);
  const [, drop] = useDrop<DragItem>({
    accept: "EXPENSE_EDITOR_ROW",
    hover(item) {
      if (item.index !== index) {
        onMove(item.index, index);
        item.index = index;
      }
    },
  });
  const [, drag] = useDrag({ type: "EXPENSE_EDITOR_ROW", item: { index }, canDrag: !disabled });
  drag(drop(handleRef));

  const vendorOptions = vendors
    .filter((vendor) => vendor.active !== false || vendor.id === row.vendor_id)
    .map((vendor) => ({ value: String(vendor.id), label: vendor.name }));
  const plafondOptions = plafonds.map((plafond) => ({
    value: String(plafond.id),
    label: `${plafond.title}${plafond.cost_center_name ? ` · ${plafond.cost_center_name}` : ""}`,
  }));
  const calculatedAmount = calculateEnteredAmount(row.quantity, row.unit_price);
  const economic = (patch: Partial<Pick<ExpenseEditorRow, "quantity" | "unit_price" | "entered_amount">>) =>
    onChange(applyCalculatedAmount(row, patch));
  const mobileDetail = detailsOpen ? "" : "hidden lg:block";
  const rowNumber = index + 1;

  return (
    <div
      role="group"
      aria-label={`Riga ${rowNumber}`}
      className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.02] lg:rounded-none lg:border-0 lg:border-t lg:bg-transparent lg:first:border-t-0 lg:dark:bg-transparent"
    >
      <div className={`grid grid-cols-2 items-end gap-3 p-3 lg:gap-2 lg:px-3 lg:py-2 ${desktopGrid}`} role="row">
        <div className="hidden h-11 items-center justify-center lg:flex">
          <span
            ref={handleRef}
            className="inline-flex size-8 cursor-grab items-center justify-center rounded-lg border border-dashed border-gray-300 text-gray-500 dark:border-gray-700 dark:text-gray-400"
            title={`Trascina la riga ${rowNumber} per riordinare`}
          >
            <HorizontaLDots className="size-4" aria-hidden="true" />
          </span>
        </div>

        <Field label="Tipo" htmlFor={`${row.editorKey}-type`} labelClassName="lg:sr-only" className="order-1 lg:order-none">
          <Select
            id={`${row.editorKey}-type`}
            options={rowTypeOptions}
            value={row.type}
            onChange={(type) => onChange({ type, ...(type === "actual" ? { is_current_planning: false } : {}) })}
            disabled={disabled}
          />
        </Field>
        <Field label="Fornitore" htmlFor={`${row.editorKey}-vendor`} labelClassName="lg:sr-only" className="order-2 lg:order-none">
          <Select
            id={`${row.editorKey}-vendor`}
            options={vendorOptions}
            value={row.vendor_id ? String(row.vendor_id) : ""}
            placeholder="Nessun fornitore"
            allowEmpty
            onChange={(value) => onChange({ vendor_id: value ? Number(value) : undefined })}
            disabled={disabled}
          />
        </Field>
        <Field label="Q.tà" htmlFor={`${row.editorKey}-quantity`} labelClassName="lg:sr-only" className={`order-5 lg:order-none ${mobileDetail}`}>
          <DecimalInput id={`${row.editorKey}-quantity`} value={row.quantity ?? ""} onChange={(quantity) => economic({ quantity })} maxScale={2} trimTrailingZeros disabled={disabled} />
        </Field>
        <Field label="Prezzo unitario" htmlFor={`${row.editorKey}-unit-price`} labelClassName="lg:sr-only" className={`order-6 lg:order-none ${mobileDetail}`}>
          <DecimalInput id={`${row.editorKey}-unit-price`} value={row.unit_price ?? ""} onChange={(unit_price) => economic({ unit_price })} maxScale={2} trimTrailingZeros disabled={disabled} />
        </Field>
        <Field label="Importo" htmlFor={`${row.editorKey}-entered-amount`} labelClassName="lg:sr-only" className="order-3 lg:order-none">
          <DecimalInput id={`${row.editorKey}-entered-amount`} value={row.entered_amount} onChange={(entered_amount) => onChange({ entered_amount })} fixedScale={2} maxScale={2} readOnly={calculatedAmount !== null} disabled={disabled} />
        </Field>
        <Field label="IVA" htmlFor={`${row.editorKey}-vat-rate`} labelClassName="lg:sr-only" className={`order-7 lg:order-none ${mobileDetail}`}>
          <DecimalInput id={`${row.editorKey}-vat-rate`} value={row.vat_rate ?? ""} onChange={(vat_rate) => onChange({ vat_rate })} fixedScale={2} maxScale={2} disabled={disabled} />
        </Field>
        <Field label="Data" htmlFor={`${row.editorKey}-spend-date`} labelClassName="lg:sr-only" className="order-4 lg:order-none">
          <InputField id={`${row.editorKey}-spend-date`} type="date" value={row.spend_date ?? ""} onChange={(event) => onChange({ spend_date: event.target.value || undefined })} disabled={disabled} />
        </Field>
        <Field label="Corrente" htmlFor={`${row.editorKey}-current`} labelClassName="lg:sr-only" className={`order-8 lg:order-none ${mobileDetail}`}>
          <div className="flex h-11 items-center justify-center">
            {row.type !== "actual" ? (
              <Checkbox id={`${row.editorKey}-current`} checked={row.is_current_planning ?? false} onChange={(is_current_planning) => onChange({ is_current_planning })} disabled={disabled} />
            ) : (
              <span className="text-sm font-medium text-gray-500 dark:text-gray-400" aria-label={`Riga ${rowNumber}: Actual non può essere pianificazione corrente`}>—</span>
            )}
          </div>
        </Field>

        <div className="order-9 flex h-11 items-center justify-center lg:order-none">
          <button
            type="button"
            onClick={() => setDetailsOpen((current) => !current)}
            aria-expanded={detailsOpen}
            aria-label={`${detailsOpen ? "Nascondi" : "Mostra"} dettagli riga ${rowNumber}`}
            className="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-gray-600 transition hover:bg-gray-50 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05] lg:size-9 lg:px-0"
          >
            <AngleRightIcon className={`size-4 transition-transform ${detailsOpen ? "rotate-90" : ""}`} aria-hidden="true" />
            <span className="ml-2 text-sm lg:sr-only">Dettagli</span>
          </button>
        </div>
        <div className="order-10 flex h-11 items-center justify-center lg:order-none">
          <IconButton icon={TrashBinIcon} label={`Rimuovi la riga ${rowNumber}`} onClick={onRemove} disabled={disabled || count === 1} destructive />
        </div>
      </div>

      {detailsOpen ? (
        <div className="grid gap-3 border-l-2 border-l-brand-500 border-t border-gray-100 bg-gray-50/70 p-3 md:grid-cols-2 lg:grid-cols-[minmax(12rem,1.4fr)_auto_auto_minmax(11rem,1fr)_minmax(10rem,1fr)_auto] lg:items-end lg:px-4 dark:border-t-gray-800 dark:bg-white/[0.025]">
          <Field label="Descrizione" htmlFor={`${row.editorKey}-description`}>
            <InputField id={`${row.editorKey}-description`} value={row.description} onChange={(event) => onChange({ description: event.target.value })} disabled={disabled} />
          </Field>
          <div className="flex min-h-11 items-center lg:pb-0.5">
            <Checkbox label="IVA inclusa" checked={row.amount_includes_vat} onChange={(amount_includes_vat) => onChange({ amount_includes_vat })} disabled={disabled} />
          </div>
          <div className="flex min-h-11 items-center lg:pb-0.5">
            <Checkbox label="Spesa Extra" checked={row.is_extra} onChange={(is_extra) => onChange({ is_extra, ...(is_extra ? { funded_plafond_expense_id: undefined } : {}) })} disabled={disabled} />
          </div>
          <Field label="Plafond di riferimento" htmlFor={`${row.editorKey}-funded`}>
            <Select id={`${row.editorKey}-funded`} options={plafondOptions} value={row.funded_plafond_expense_id ? String(row.funded_plafond_expense_id) : ""} placeholder={plafondsLoading ? "Caricamento Plafond…" : "Nessun Plafond"} allowEmpty onChange={(value) => onChange({ funded_plafond_expense_id: value ? Number(value) : undefined, ...(value ? { is_extra: false } : {}) })} disabled={disabled || plafondsLoading} />
          </Field>
          <Field label="Riferimento esterno" htmlFor={`${row.editorKey}-external`}>
            <InputField id={`${row.editorKey}-external`} value={row.external_reference ?? ""} onChange={(event) => onChange({ external_reference: event.target.value })} disabled={disabled} />
          </Field>
          <div className="flex items-center justify-end gap-1 md:col-span-2 lg:col-span-1 lg:h-11">
            <IconButton icon={ChevronUpIcon} label={`Sposta la riga ${rowNumber} in alto`} onClick={() => onMove(index, index - 1)} disabled={disabled || index === 0} />
            <IconButton icon={ChevronDownIcon} label={`Sposta la riga ${rowNumber} in basso`} onClick={() => onMove(index, index + 1)} disabled={disabled || index === count - 1} />
          </div>
        </div>
      ) : null}
    </div>
  );
}

export default function ExpenseEditorRows({
  rows,
  vendors,
  plafonds,
  plafondsLoading = false,
  onChange,
  onMove,
  onRemove,
  disabled = false,
}: ExpenseEditorRowsProps) {
  return (
    <div className="space-y-3 lg:space-y-0 lg:overflow-hidden lg:rounded-xl lg:border lg:border-gray-200 lg:dark:border-gray-800">
      <div className={`hidden items-center gap-2 bg-gray-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.025] dark:text-gray-400 lg:grid ${desktopGrid}`} role="row">
        <span role="columnheader"><span className="sr-only">Riordina</span></span>
        {["Tipo", "Fornitore", "Q.tà", "Prezzo unit.", "Importo", "IVA", "Data", "Corrente", "Dettagli", "Elimina"].map((heading) => (
          <span key={heading} role="columnheader" className="truncate text-center first:text-left">{heading}</span>
        ))}
      </div>
      {rows.map((row, index) => (
        <DraggableExpenseRow
          key={row.editorKey}
          row={row}
          index={index}
          count={rows.length}
          vendors={vendors}
          plafonds={plafonds}
          plafondsLoading={plafondsLoading}
          onChange={(patch) => onChange(index, patch)}
          onMove={onMove}
          onRemove={() => onRemove(index)}
          disabled={disabled}
        />
      ))}
    </div>
  );
}
