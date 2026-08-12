import { useRef, useState } from "react";
import { useDrag, useDrop } from "react-dnd";
import type { ExpenseLookupOption, PlafondExpenseOption } from "../../api/expenses";
import { ChevronDownIcon, HorizontaLDots, TrashBinIcon } from "../../icons";
import IconButton from "../common/IconButton";
import Select from "../form/Select";
import DatePicker from "../form/date-picker";
import Checkbox from "../form/input/Checkbox";
import DecimalInput from "../form/input/DecimalInput";
import InputField from "../form/input/InputField";
import type { ExpenseEditorRow } from "./expenseEditorTypes";

const rowTypeOptions = [
  { value: "estimate", label: "Stima" },
  { value: "quote", label: "Preventivo" },
  { value: "actual", label: "Actual" },
];

const rowGridClass = "grid grid-cols-[64px_105px_120px_minmax(190px,1fr)_170px_70px_95px_95px_90px_125px_95px_84px] items-start gap-2";
const columnHeadingClass = "px-1 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400";
const columnHeadings = [
  "Riga",
  "Tipo",
  "Fornitore",
  "Descrizione",
  "Copertura Plafond",
  "Q.tà",
  "Prezzo Unitario",
  "Importo",
  "IVA inclusa",
  "Data",
  "Pianificazione",
  "Azioni",
] as const;

interface ExpenseEditorRowsProps {
  rows: ExpenseEditorRow[];
  defaultVatRate?: string | null;
  vendors: ExpenseLookupOption[];
  plafonds?: PlafondExpenseOption[];
  onChange: (index: number, patch: Partial<ExpenseEditorRow>) => void;
  onMove: (from: number, to: number) => void;
  onRemove: (index: number) => void;
  disabled?: boolean;
  validationErrors?: Record<string, string>;
}

interface DragItem {
  index: number;
}

function DraggableExpenseRow({
  row,
  defaultVatRate,
  index,
  count,
  vendors,
  plafonds = [],
  onChange,
  onMove,
  onRemove,
  disabled,
  validationErrors,
}: {
  row: ExpenseEditorRow;
  defaultVatRate: string | null;
  index: number;
  count: number;
  vendors: ExpenseLookupOption[];
  plafonds: PlafondExpenseOption[];
  onChange: (patch: Partial<ExpenseEditorRow>) => void;
  onMove: (from: number, to: number) => void;
  onRemove: () => void;
  disabled: boolean;
  validationErrors: Record<string, string>;
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
  const [, drag] = useDrag({
    type: "EXPENSE_EDITOR_ROW",
    item: { index },
    canDrag: !disabled,
  });
  drag(drop(handleRef));

  const vendorOptions = vendors
    .filter((vendor) => vendor.active !== false || vendor.id === row.vendor_id)
    .map((vendor) => ({ value: String(vendor.id), label: vendor.name }));
  const plafondOptions = plafonds.map((plafond) => ({
    value: String(plafond.id),
    label: `${plafond.title} · Centro: ${plafond.cost_center.name}`,
  }));
  const calculatedMode = Boolean(row.quantity || row.unit_price);
  const errorFor = (field: string) => validationErrors[`rows.${index}.${field}`];
  const rowError = validationErrors[`rows.${index}`];
  const structuralError = rowError ?? errorFor("id") ?? errorFor("position") ?? errorFor("lock_version");
  const hasRowError = rowError !== undefined
    || Object.keys(validationErrors).some((field) => field.startsWith(`rows.${index}.`));
  const hasAdvancedError = [
    "vat_rate",
    "external_reference",
  ].some((field) => validationErrors[`rows.${index}.${field}`] !== undefined);
  const detailsVisible = detailsOpen || hasAdvancedError;

  return (
    <fieldset
      id={`${row.editorKey}-row`}
      tabIndex={-1}
      className={`border-t bg-white focus:outline-hidden focus:ring-3 focus:ring-inset focus:ring-error-500/10 dark:bg-white/[0.02] ${
        hasRowError
          ? "border-error-500 dark:border-error-500"
          : "border-gray-200 dark:border-gray-800"
      }`}
    >
      <legend className="sr-only">Riga {index + 1}</legend>

      <div className={`${rowGridClass} px-3 py-2.5`}>
        <div className="flex h-11 items-center gap-2">
          <span
            ref={handleRef}
            className="inline-flex size-8 shrink-0 cursor-grab items-center justify-center rounded-md border border-dashed border-gray-300 text-gray-500 dark:border-gray-700 dark:text-gray-400"
            title="Trascina per riordinare"
          >
            <HorizontaLDots className="size-4" aria-hidden="true" />
          </span>
          <span className="text-xs font-semibold text-gray-700 dark:text-gray-200">
            {index + 1}
          </span>
        </div>

        <div>
          <Select
            id={`${row.editorKey}-type`}
            ariaLabel={`Tipo riga ${index + 1}`}
            options={rowTypeOptions}
            value={row.type}
            onChange={(type) => onChange({
              type,
              ...(type === "actual" ? { is_current_planning: false } : {}),
            })}
            disabled={disabled}
            error={Boolean(errorFor("type"))}
            hint={errorFor("type")}
          />
        </div>

        <div>
          <Select
            id={`${row.editorKey}-vendor`}
            ariaLabel={`Fornitore riga ${index + 1}`}
            options={vendorOptions}
            value={row.vendor_id ? String(row.vendor_id) : ""}
            placeholder="Nessuno"
            allowEmpty
            onChange={(value) => onChange({ vendor_id: value ? Number(value) : undefined })}
            disabled={disabled}
            error={Boolean(errorFor("vendor_id"))}
            hint={errorFor("vendor_id")}
          />
        </div>

        <div>
          <InputField
            id={`${row.editorKey}-description`}
            ariaLabel={`Descrizione riga ${index + 1}`}
            value={row.description}
            onChange={(event) => onChange({ description: event.target.value })}
            disabled={disabled}
            error={Boolean(errorFor("description"))}
            hint={errorFor("description")}
          />
        </div>

        <div>
          <Select
            id={`${row.editorKey}-funded-plafond`}
            ariaLabel={`Copertura Plafond riga ${index + 1}`}
            options={plafondOptions}
            value={row.funded_plafond_expense_id ? String(row.funded_plafond_expense_id) : ""}
            placeholder="Nessuna copertura"
            allowEmpty
            onChange={(value) => onChange({ funded_plafond_expense_id: value ? Number(value) : null })}
            disabled={disabled}
            error={Boolean(errorFor("funded_plafond_expense_id"))}
            hint={errorFor("funded_plafond_expense_id") ?? "Copre integralmente questa riga; il Centro di Costo può essere differente."}
          />
        </div>

        <div>
          <DecimalInput
            id={`${row.editorKey}-quantity`}
            ariaLabel={`Quantità riga ${index + 1}`}
            value={row.quantity ?? ""}
            onChange={(quantity) => onChange({ quantity: quantity || undefined, entered_amount: quantity ? undefined : row.entered_amount })}
            maxScale={2}
            trimTrailingZeros
            suffix="pz"
            disabled={disabled}
            error={Boolean(errorFor("quantity"))}
            hint={errorFor("quantity")}
          />
        </div>

        <div>
          <DecimalInput
            id={`${row.editorKey}-unit-price`}
            ariaLabel={`Prezzo Unitario riga ${index + 1}`}
            value={row.unit_price ?? ""}
            onChange={(unit_price) => onChange({ unit_price: unit_price || undefined, entered_amount: unit_price ? undefined : row.entered_amount })}
            maxScale={2}
            trimTrailingZeros
            suffix="€"
            disabled={disabled}
            error={Boolean(errorFor("unit_price"))}
            hint={errorFor("unit_price")}
          />
        </div>

        <div>
          <DecimalInput
            id={`${row.editorKey}-entered-amount`}
            ariaLabel={`Importo riga ${index + 1}`}
            value={row.entered_amount ?? ""}
            onChange={(entered_amount) => onChange({ entered_amount: entered_amount || undefined, quantity: undefined, unit_price: undefined })}
            fixedScale={2}
            maxScale={2}
            readOnly={calculatedMode}
            suffix="€"
            disabled={disabled}
            error={Boolean(errorFor("entered_amount"))}
            hint={errorFor("entered_amount")}
          />
        </div>

        <div>
          <div className="flex h-11 items-center justify-center">
            <Checkbox
              id={`${row.editorKey}-vat-included`}
              label="IVA inclusa"
              hideLabel
              checked={row.amount_includes_vat}
              onChange={(amount_includes_vat) => onChange({ amount_includes_vat })}
              disabled={disabled}
              error={Boolean(errorFor("amount_includes_vat"))}
              hint={errorFor("amount_includes_vat")}
            />
          </div>
        </div>

        <div>
          <DatePicker
            id={`${row.editorKey}-spend-date`}
            label={`Data riga ${index + 1}`}
            hideLabel
            staticPosition={false}
            placeholder="Data"
            defaultDate={row.spend_date || undefined}
            onChange={(_, spend_date) => onChange({ spend_date: spend_date || undefined })}
            disabled={disabled}
            error={Boolean(errorFor("spend_date"))}
            hint={errorFor("spend_date")}
          />
        </div>

        <div className="flex h-11 items-center">
          {row.type !== "actual" ? (
            <Checkbox
              id={`${row.editorKey}-current`}
              label="Corrente"
              checked={row.is_current_planning ?? false}
              onChange={(is_current_planning) => onChange({ is_current_planning })}
              disabled={disabled}
              error={Boolean(errorFor("is_current_planning"))}
              hint={errorFor("is_current_planning")}
            />
          ) : (
            <span
              id={`${row.editorKey}-current`}
              tabIndex={-1}
              aria-invalid={errorFor("is_current_planning") ? true : undefined}
              aria-describedby={errorFor("is_current_planning") ? `${row.editorKey}-current-hint` : undefined}
              className={`text-xs focus:outline-hidden focus:ring-3 focus:ring-error-500/10 ${
                errorFor("is_current_planning") ? "text-error-500" : "text-gray-500 dark:text-gray-400"
              }`}
            >
              Actual
              {errorFor("is_current_planning") ? (
                <span id={`${row.editorKey}-current-hint`} className="mt-1 block">
                  {errorFor("is_current_planning")}
                </span>
              ) : null}
            </span>
          )}
        </div>

        <div className="flex h-11 items-center justify-end gap-2">
          <IconButton
            icon={ChevronDownIcon}
            label={`${detailsVisible ? "Nascondi" : "Mostra"} dettagli riga ${index + 1}`}
            onClick={() => setDetailsOpen((current) => !current)}
            disabled={disabled}
            ariaExpanded={detailsVisible}
            ariaControls={`${row.editorKey}-advanced-details`}
            iconClassName={`transition-transform ${detailsVisible ? "rotate-180" : ""}`}
          />
          <IconButton
            icon={TrashBinIcon}
            label={`Rimuovi la riga ${index + 1}`}
            onClick={onRemove}
            disabled={disabled || count === 1}
            destructive
          />
        </div>
      </div>

      {structuralError ? (
        <p className="px-4 pb-2 text-xs text-error-500">{structuralError}</p>
      ) : null}

      <section
        id={`${row.editorKey}-advanced-details`}
        aria-labelledby={`${row.editorKey}-advanced-details-title`}
        className={`${detailsVisible ? "grid" : "hidden"} mx-3 mb-3 gap-4 rounded-xl border border-gray-200 bg-gray-50/80 p-4 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 sm:p-5 md:grid-cols-2 lg:grid-cols-12`}
      >
        <header className="md:col-span-2 lg:col-span-12">
          <h4
            id={`${row.editorKey}-advanced-details-title`}
            className="text-sm font-semibold text-gray-800 dark:text-white/90"
          >
            Impostazioni Avanzate
          </h4>
          <p className="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
            Configura il trattamento fiscale e i riferimenti opzionali della riga.
          </p>
        </header>

        <div className="space-y-3 md:col-span-1 lg:col-span-5">
          <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Trattamento
          </p>
          <div>
            <label htmlFor={`${row.editorKey}-notes`} className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Note riga</label>
            <InputField id={`${row.editorKey}-notes`} value={row.notes ?? ""} onChange={(event) => onChange({ notes: event.target.value || undefined })} disabled={disabled} />
          </div>
          <div>
            <label
              htmlFor={`${row.editorKey}-vat-rate`}
              className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
            >
              IVA
            </label>
            <DecimalInput
              id={`${row.editorKey}-vat-rate`}
              ariaLabel={`IVA riga ${index + 1}`}
              value={row.id === undefined && row.vat_rate === undefined
                ? defaultVatRate ?? ""
                : row.vat_rate ?? ""}
              onChange={(vat_rate) => onChange({ vat_rate: vat_rate || undefined })}
              fixedScale={2}
              maxScale={2}
              suffix="%"
              disabled={disabled}
              error={Boolean(errorFor("vat_rate"))}
              hint={errorFor("vat_rate") ?? (row.id === undefined && row.vat_rate === undefined
                ? "Valore predefinito del Tenant; modificalo per applicare un override."
                : undefined)}
            />
          </div>
        </div>

        <div className="space-y-3 md:col-span-1 lg:col-span-7">
          <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Riferimenti
          </p>
          <div>
            <label
              htmlFor={`${row.editorKey}-external`}
              className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
            >
              Riferimento esterno
            </label>
            <InputField
              id={`${row.editorKey}-external`}
              value={row.external_reference ?? ""}
              onChange={(event) => onChange({ external_reference: event.target.value })}
              disabled={disabled}
              error={Boolean(errorFor("external_reference"))}
              hint={errorFor("external_reference")}
            />
          </div>
        </div>
      </section>
    </fieldset>
  );
}

export default function ExpenseEditorRows({
  rows,
  defaultVatRate = null,
  vendors,
  plafonds = [],
  onChange,
  onMove,
  onRemove,
  disabled = false,
  validationErrors = {},
}: ExpenseEditorRowsProps) {
  return (
    <div className="max-w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
      <div className="min-w-[1430px]">
        <div
          className={`${rowGridClass} border-b border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-800 dark:bg-gray-900`}
        >
          {columnHeadings.map((heading) => (
            <div key={heading} data-erp-column-heading className={columnHeadingClass}>
              {heading}
            </div>
          ))}
        </div>

        {rows.map((row, index) => (
      <DraggableExpenseRow
        key={row.editorKey}
        row={row}
        defaultVatRate={defaultVatRate}
            index={index}
            count={rows.length}
            vendors={vendors}
            plafonds={plafonds}
            onChange={(patch) => onChange(index, patch)}
            onMove={onMove}
            onRemove={() => onRemove(index)}
            disabled={disabled}
            validationErrors={validationErrors}
          />
        ))}
      </div>
    </div>
  );
}
