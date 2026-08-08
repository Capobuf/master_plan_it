import { useRef } from "react";
import { useDrag, useDrop } from "react-dnd";
import type { ExpenseLookupOption } from "../../api/expenses";
import Checkbox from "../form/input/Checkbox";
import DatePicker from "../form/date-picker";
import InputField from "../form/input/InputField";
import Label from "../form/Label";
import Select from "../form/Select";
import Button from "../ui/button/Button";
import {
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
} from "../ui/table";
import type { ExpenseEditorRow } from "./expenseEditorTypes";

const NONE = "__none__";
const rowTypeOptions = [
  { value: "estimate", label: "Stima" },
  { value: "quote", label: "Preventivo" },
  { value: "actual", label: "Effettiva" },
];
const distributionOptions = [
  { value: "all", label: "Tutto" },
  { value: "start", label: "Inizio" },
  { value: "end", label: "Fine" },
];

interface ExpenseEditorRowsProps {
  rows: ExpenseEditorRow[];
  vendors: ExpenseLookupOption[];
  onChange: (index: number, patch: Partial<ExpenseEditorRow>) => void;
  onMove: (from: number, to: number) => void;
  onRemove: (index: number) => void;
  disabled?: boolean;
}

interface DragItem {
  index: number;
}

function EditorSelect({
  id,
  value,
  options,
  onChange,
  disabled,
}: {
  id: string;
  value: string | undefined;
  options: Array<{ value: string; label: string }>;
  onChange: (value: string | undefined) => void;
  disabled: boolean;
}) {
  const selected = value || NONE;
  return (
    <Select
      key={`${id}-${selected}`}
      options={[{ value: NONE, label: "—" }, ...options]}
      defaultValue={selected}
      onChange={(next) => {
        if (!disabled) onChange(next === NONE ? undefined : next);
      }}
      className={disabled ? "pointer-events-none opacity-60" : "min-w-32"}
    />
  );
}

function DraggableExpenseRow({
  row,
  index,
  vendors,
  onChange,
  onMove,
  onRemove,
  disabled,
}: {
  row: ExpenseEditorRow;
  index: number;
  vendors: ExpenseLookupOption[];
  onChange: (patch: Partial<ExpenseEditorRow>) => void;
  onMove: (from: number, to: number) => void;
  onRemove: () => void;
  disabled: boolean;
}) {
  const handleRef = useRef<HTMLSpanElement>(null);
  const [, drop] = useDrop<DragItem>({
    accept: "EXPENSE_EDITOR_ROW",
    hover(item) {
      if (item.index === index) return;
      onMove(item.index, index);
      item.index = index;
    },
  });
  const [, drag] = useDrag({
    type: "EXPENSE_EDITOR_ROW",
    item: { index },
  });
  drag(drop(handleRef));

  const vendorOptions = vendors
    .filter((vendor) => vendor.active !== false || vendor.id === row.vendor_id)
    .map((vendor) => ({ value: String(vendor.id), label: vendor.name }));
  if (row.vendor_id && !vendors.some((vendor) => vendor.id === row.vendor_id)) {
    vendorOptions.unshift({ value: String(row.vendor_id), label: `Vendor #${row.vendor_id}` });
  }

  return (
    <TableRow>
      <TableCell className="px-3 py-4 align-top">
        <span
          ref={handleRef}
          title="Trascina per riordinare"
          className="inline-flex cursor-grab select-none rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
        >
          {index + 1}
        </span>
      </TableCell>
      <TableCell className="min-w-36 px-3 py-4 align-top">
        <Label>Tipo</Label>
        <EditorSelect
          id={`${row.editorKey}-type`}
          value={row.type}
          options={rowTypeOptions}
          onChange={(type) => onChange({ type: type ?? "estimate" })}
          disabled={disabled}
        />
      </TableCell>
      <TableCell className="min-w-48 px-3 py-4 align-top">
        <Label>Vendor</Label>
        <EditorSelect
          id={`${row.editorKey}-vendor`}
          value={row.vendor_id ? String(row.vendor_id) : undefined}
          options={vendorOptions}
          onChange={(vendor) => onChange({ vendor_id: vendor ? Number(vendor) : undefined })}
          disabled={disabled}
        />
      </TableCell>
      <TableCell className="min-w-64 px-3 py-4 align-top">
        <Label htmlFor={`${row.editorKey}-description`}>Descrizione</Label>
        <InputField
          id={`${row.editorKey}-description`}
          value={row.description}
          onChange={(event) => onChange({ description: event.target.value })}
          disabled={disabled}
        />
      </TableCell>
      <TableCell className="min-w-32 px-3 py-4 align-top">
        <Label htmlFor={`${row.editorKey}-quantity`}>Quantità</Label>
        <InputField
          id={`${row.editorKey}-quantity`}
          value={row.quantity ?? ""}
          onChange={(event) => onChange({ quantity: event.target.value })}
          disabled={disabled}
        />
      </TableCell>
      <TableCell className="min-w-32 px-3 py-4 align-top">
        <Label htmlFor={`${row.editorKey}-unit-price`}>Prezzo unitario</Label>
        <InputField
          id={`${row.editorKey}-unit-price`}
          value={row.unit_price ?? ""}
          onChange={(event) => onChange({ unit_price: event.target.value })}
          disabled={disabled}
        />
      </TableCell>
      <TableCell className="min-w-32 px-3 py-4 align-top">
        <Label htmlFor={`${row.editorKey}-entered-amount`}>Importo</Label>
        <InputField
          id={`${row.editorKey}-entered-amount`}
          value={row.entered_amount}
          onChange={(event) => onChange({ entered_amount: event.target.value })}
          disabled={disabled}
        />
      </TableCell>
      <TableCell className="min-w-32 px-3 py-4 align-top">
        <Label htmlFor={`${row.editorKey}-vat-rate`}>IVA</Label>
        <InputField
          id={`${row.editorKey}-vat-rate`}
          value={row.vat_rate ?? ""}
          onChange={(event) => onChange({ vat_rate: event.target.value })}
          disabled={disabled}
        />
      </TableCell>
      <TableCell className="min-w-36 px-3 py-4 align-top">
        <Checkbox
          label="Importo include IVA"
          checked={row.amount_includes_vat}
          onChange={(checked) => onChange({ amount_includes_vat: checked })}
          disabled={disabled}
        />
        <div className="mt-3">
          <Checkbox
            label="Extra"
            checked={row.is_extra}
            onChange={(checked) => onChange({ is_extra: checked })}
            disabled={disabled}
          />
        </div>
      </TableCell>
      <TableCell className="min-w-40 px-3 py-4 align-top">
        <div className={disabled ? "pointer-events-none opacity-60" : ""}>
          <DatePicker
            id={`${row.editorKey}-spend-date`}
            label="Data spesa"
            defaultDate={row.spend_date || undefined}
            onChange={(_, dateString) => onChange({ spend_date: dateString || undefined })}
          />
        </div>
      </TableCell>
      <TableCell className="min-w-40 px-3 py-4 align-top">
        <div className={disabled ? "pointer-events-none opacity-60" : ""}>
          <DatePicker
            id={`${row.editorKey}-period-start`}
            label="Periodo da"
            defaultDate={row.period_start || undefined}
            onChange={(_, dateString) => onChange({ period_start: dateString || undefined })}
          />
        </div>
        <div className="mt-3">
          <div className={disabled ? "pointer-events-none opacity-60" : ""}>
            <DatePicker
              id={`${row.editorKey}-period-end`}
              label="Periodo a"
              defaultDate={row.period_end || undefined}
              onChange={(_, dateString) => onChange({ period_end: dateString || undefined })}
            />
          </div>
        </div>
      </TableCell>
      <TableCell className="min-w-36 px-3 py-4 align-top">
        <Label>Distribuzione</Label>
        <EditorSelect
          id={`${row.editorKey}-distribution`}
          value={row.distribution}
          options={distributionOptions}
          onChange={(distribution) => onChange({ distribution })}
          disabled={disabled}
        />
      </TableCell>
      <TableCell className="min-w-36 px-3 py-4 align-top">
        <Label htmlFor={`${row.editorKey}-funded`}>Plafond ID</Label>
        <InputField
          id={`${row.editorKey}-funded`}
          type="number"
          min="1"
          value={row.funded_plafond_expense_id ?? ""}
          onChange={(event) =>
            onChange({
              funded_plafond_expense_id: event.target.value ? Number(event.target.value) : undefined,
            })
          }
          disabled={disabled}
        />
        <Label htmlFor={`${row.editorKey}-external`} className="mt-3">Riferimento</Label>
        <InputField
          id={`${row.editorKey}-external`}
          value={row.external_reference ?? ""}
          onChange={(event) => onChange({ external_reference: event.target.value })}
          disabled={disabled}
        />
      </TableCell>
      <TableCell className="px-3 py-4 align-top">
        <Button type="button" size="sm" variant="outline" onClick={onRemove} disabled={disabled}>
          Rimuovi
        </Button>
      </TableCell>
    </TableRow>
  );
}

export default function ExpenseEditorRows({
  rows,
  vendors,
  onChange,
  onMove,
  onRemove,
  disabled = false,
}: ExpenseEditorRowsProps) {
  return (
    <div className="max-w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-white/[0.05]">
        <Table>
          <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
            <TableRow>
              {["#", "Tipo", "Vendor", "Descrizione", "Quantità", "Prezzo unitario", "Importo", "IVA", "Flags", "Data", "Periodo", "Distribuzione", "Finanziamento", "Azioni"].map((heading) => (
                <TableCell key={heading} isHeader className="whitespace-nowrap px-3 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                  {heading}
                </TableCell>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
            {rows.map((row, index) => (
              <DraggableExpenseRow
                key={row.editorKey}
                row={row}
                index={index}
                vendors={vendors}
                onChange={(patch) => onChange(index, patch)}
                onMove={onMove}
                onRemove={() => onRemove(index)}
                disabled={disabled}
              />
            ))}
          </TableBody>
        </Table>
      </div>
  );
}
