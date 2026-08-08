import { useEffect, useMemo, useState } from "react";
import { DndProvider } from "react-dnd";
import { HTML5Backend } from "react-dnd-html5-backend";
import { useNavigate } from "react-router";
import { ApiError } from "../../api/client";
import {
  createExpense,
  getExpense,
  listExpenseContracts,
  listExpenseCostCenters,
  listExpensePlanningYears,
  listExpenseVendors,
  updateExpense,
  type ExpenseContractOption,
  type ExpenseDetail,
  type ExpenseLookupOption,
  type ExpenseUpdate,
  type ExpenseWrite,
} from "../../api/expenses";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";
import Alert from "../ui/alert/Alert";
import Button from "../ui/button/Button";
import InputField from "../form/input/InputField";
import Label from "../form/Label";
import Select from "../form/Select";
import TextArea from "../form/input/TextArea";
import ComponentCard from "../common/ComponentCard";
import ExpenseEditorRows from "./ExpenseEditorRows";
import {
  isDecimalText,
  newExpenseEditorRow,
  normalizeDecimal,
  type ExpenseEditorRow,
} from "./expenseEditorTypes";

const NONE = "__none__";

interface ExpenseEditorProps {
  expenseId?: number;
}

interface EditorHeader {
  planning_year_id: number | null;
  cost_center_id: number | null;
  kind: string;
  title: string;
  notes: string;
  contract_id: number | null;
}

function initialHeader(selectedPlanningYearId: number | null): EditorHeader {
  return {
    planning_year_id: selectedPlanningYearId,
    cost_center_id: null,
    kind: "ordinary",
    title: "",
    notes: "",
    contract_id: null,
  };
}

function toEditorRows(detail: ExpenseDetail): ExpenseEditorRow[] {
  return detail.rows.map((row) => ({
    editorKey: `existing-${row.id}`,
    id: row.id,
    position: row.position,
    vendor_id: row.vendor_id ?? undefined,
    type: row.type,
    description: row.description,
    quantity: row.quantity === null ? undefined : normalizeDecimal(row.quantity),
    unit_price: row.unit_price === null ? undefined : normalizeDecimal(row.unit_price),
    entered_amount: normalizeDecimal(row.entered_amount),
    amount_includes_vat: row.amount_includes_vat,
    vat_rate: row.vat_rate === null ? undefined : normalizeDecimal(row.vat_rate),
    is_extra: row.is_extra,
    funded_plafond_expense_id: row.funded_plafond_expense_id ?? undefined,
    spend_date: row.spend_date ?? undefined,
    period_start: row.period_start ?? undefined,
    period_end: row.period_end ?? undefined,
    distribution: row.distribution ?? undefined,
    external_reference: row.external_reference ?? undefined,
    lock_version: row.lock_version,
  }));
}

function EditorSelect({
  id,
  value,
  options,
  onChange,
  disabled,
}: {
  id: string;
  value: string;
  options: Array<{ value: string; label: string }>;
  onChange: (value: string) => void;
  disabled: boolean;
}) {
  return (
    <Select
      key={`${id}-${value}`}
      options={options}
      defaultValue={value}
      onChange={(next) => {
        if (!disabled) onChange(next);
      }}
      className={disabled ? "pointer-events-none opacity-60" : ""}
    />
  );
}

function byIdOption(
  options: ExpenseLookupOption[],
  id: number | null,
  label: string,
): ExpenseLookupOption[] {
  if (id !== null && !options.some((option) => option.id === id)) {
    return [{ id, name: label }, ...options];
  }
  return options;
}

export default function ExpenseEditor({ expenseId }: ExpenseEditorProps) {
  const navigate = useNavigate();
  const { data: applicationContext, loading: contextLoading, hasAbility } = useApplicationContext();
  const { selectedPlanningYearId } = usePlanningYear();
  const editing = expenseId !== undefined;
  const tenantId = applicationContext?.tenant?.id ?? null;
  const canSubmit = hasAbility(editing ? "expense.update" : "expense.create");
  const canView = hasAbility("expense.view");
  const canUseLookups = [
    "vendor.view",
    "cost-center.view",
    "planning-year.view",
    "contract.view",
  ].every(hasAbility);
  const [header, setHeader] = useState<EditorHeader>(() => initialHeader(selectedPlanningYearId));
  const [rows, setRows] = useState<ExpenseEditorRow[]>(() => [newExpenseEditorRow(1)]);
  const [deletedRows, setDeletedRows] = useState<Array<{ id: number; lock_version: number }>>([]);
  const [vendors, setVendors] = useState<ExpenseLookupOption[]>([]);
  const [costCenters, setCostCenters] = useState<ExpenseLookupOption[]>([]);
  const [planningYears, setPlanningYears] = useState<ExpenseLookupOption[]>([]);
  const [contracts, setContracts] = useState<ExpenseContractOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [validationMessage, setValidationMessage] = useState<string | null>(null);
  const [loadedId, setLoadedId] = useState<number | null>(null);
  const [lockVersion, setLockVersion] = useState<number | null>(null);

  useEffect(() => {
    if (selectedPlanningYearId !== null && !editing && header.planning_year_id === null) {
      setHeader((current) => ({ ...current, planning_year_id: selectedPlanningYearId }));
    }
  }, [editing, header.planning_year_id, selectedPlanningYearId]);

  useEffect(() => {
    if (
      contextLoading ||
      tenantId === null ||
      !canSubmit ||
      !canUseLookups ||
      (editing && (!canView || expenseId === undefined))
    ) {
      return;
    }

    let active = true;
    setLoading(true);
    setError(null);
    const detailRequest = editing ? getExpense(expenseId as number) : Promise.resolve(null);

    void Promise.all([
      listExpenseVendors(),
      listExpenseCostCenters(),
      listExpensePlanningYears(),
      listExpenseContracts(),
      detailRequest,
    ])
      .then(([vendorOptions, costCenterOptions, yearOptions, contractOptions, detail]) => {
        if (!active) return;
        setVendors(vendorOptions);
        setCostCenters(costCenterOptions);
        setPlanningYears(yearOptions.map((year) => ({ id: year.id, name: String(year.label), active: year.active })));
        setContracts(contractOptions);
        if (detail) {
          setHeader({
            planning_year_id: detail.planning_year_id,
            cost_center_id: detail.cost_center_id,
            kind: detail.kind,
            title: detail.title,
            notes: detail.notes ?? "",
            contract_id: detail.contract_id,
          });
          setRows(toEditorRows(detail));
          setLockVersion(detail.lock_version);
          setLoadedId(detail.id);
        } else {
          setHeader((current) => ({
            ...current,
            planning_year_id: current.planning_year_id ?? selectedPlanningYearId,
          }));
        }
      })
      .catch((requestError: unknown) => {
        if (active) setError(ApiError.from(requestError));
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [canSubmit, canUseLookups, canView, contextLoading, editing, expenseId, selectedPlanningYearId, tenantId]);

  const disabled = loading || submitting || !canSubmit;
  const costCenterOptions = useMemo(
    () => byIdOption(costCenters, header.cost_center_id, header.cost_center_id ? `Centro #${header.cost_center_id}` : "").map((option) => ({ value: String(option.id), label: option.name })),
    [costCenters, header.cost_center_id],
  );
  const planningYearOptions = useMemo(
    () => byIdOption(planningYears, header.planning_year_id, header.planning_year_id ? `Anno #${header.planning_year_id}` : "").map((option) => ({ value: String(option.id), label: option.name })),
    [header.planning_year_id, planningYears],
  );
  const contractOptions = useMemo(
    () => [
      { value: NONE, label: "Nessun contratto" },
      ...(header.contract_id !== null && !contracts.some((contract) => contract.id === header.contract_id)
        ? [{ value: String(header.contract_id), label: `Contratto #${header.contract_id}` }]
        : []),
      ...contracts
        .filter((contract) => contract.active !== false || contract.id === header.contract_id)
        .map((contract) => ({ value: String(contract.id), label: contract.title })),
    ],
    [contracts, header.contract_id],
  );

  function updateRow(index: number, patch: Partial<ExpenseEditorRow>) {
    setRows((current) => current.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)));
  }

  function moveRow(from: number, to: number) {
    setRows((current) => {
      const next = [...current];
      const [moved] = next.splice(from, 1);
      if (!moved) return current;
      next.splice(to, 0, moved);
      return next.map((row, index) => ({ ...row, position: index + 1 }));
    });
  }

  function removeRow(index: number) {
    setRows((current) => {
      const target = current[index];
      if (target?.id && target.lock_version) {
        setDeletedRows((deleted) =>
          deleted.some((row) => row.id === target.id)
            ? deleted
            : [...deleted, { id: target.id as number, lock_version: target.lock_version as number }],
        );
      }
      const next = current.filter((_, rowIndex) => rowIndex !== index);
      return next.map((row, rowIndex) => ({ ...row, position: rowIndex + 1 }));
    });
  }

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setValidationMessage(null);
    if (header.planning_year_id === null || header.cost_center_id === null || !header.title.trim()) {
      setValidationMessage("Anno, centro di costo e titolo sono obbligatori.");
      return;
    }
    if (rows.length === 0) {
      setValidationMessage("Inserisci almeno una riga.");
      return;
    }

    const preparedRows = rows.map((row, index) => ({
      row,
      position: index + 1,
      entered: normalizeDecimal(row.entered_amount),
      quantity: normalizeDecimal(row.quantity),
      unitPrice: normalizeDecimal(row.unit_price),
      vatRate: normalizeDecimal(row.vat_rate),
    }));
    const invalid = preparedRows.find(({ row, entered, quantity, unitPrice, vatRate }) =>
      !row.description.trim() ||
      !isDecimalText(entered) ||
      (quantity !== "" && !isDecimalText(quantity)) ||
      (unitPrice !== "" && !isDecimalText(unitPrice)) ||
      (vatRate !== "" && !/^\d+(?:\.\d{1,6})?$/.test(vatRate)),
    );
    if (invalid) {
      setValidationMessage("Controlla descrizione e valori decimali delle righe (massimo 6 cifre).");
      return;
    }

    const input: ExpenseWrite = {
      planning_year_id: header.planning_year_id,
      cost_center_id: header.cost_center_id,
      kind: header.kind,
      title: header.title.trim(),
      ...(header.notes.trim() ? { notes: header.notes.trim() } : {}),
      ...(header.contract_id !== null ? { contract_id: header.contract_id } : {}),
      rows: preparedRows.map(({ row, position, entered, quantity, unitPrice, vatRate }) => ({
        ...(row.id !== undefined ? { id: row.id } : {}),
        position,
        ...(row.vendor_id !== undefined ? { vendor_id: row.vendor_id } : {}),
        type: row.type,
        description: row.description.trim(),
        ...(quantity ? { quantity } : {}),
        ...(unitPrice ? { unit_price: unitPrice } : {}),
        entered_amount: entered,
        amount_includes_vat: row.amount_includes_vat,
        ...(vatRate ? { vat_rate: vatRate } : {}),
        is_extra: row.is_extra,
        ...(row.funded_plafond_expense_id !== undefined ? { funded_plafond_expense_id: row.funded_plafond_expense_id } : {}),
        ...(row.spend_date ? { spend_date: row.spend_date } : {}),
        ...(row.period_start ? { period_start: row.period_start } : {}),
        ...(row.period_end ? { period_end: row.period_end } : {}),
        ...(row.distribution ? { distribution: row.distribution } : {}),
        ...(row.external_reference?.trim() ? { external_reference: row.external_reference.trim() } : {}),
        ...(row.lock_version !== undefined ? { lock_version: row.lock_version } : {}),
      })),
    };

    setSubmitting(true);
    setError(null);
    try {
      const saved = editing
        ? await updateExpense(expenseId as number, {
            ...input,
            lock_version: lockVersion as number,
            ...(deletedRows.length > 0 ? { deleted_rows: deletedRows } : {}),
          } satisfies ExpenseUpdate)
        : await createExpense(input);
      navigate(`/expenses/${saved.id}`);
    } catch (requestError: unknown) {
      setError(ApiError.from(requestError));
    } finally {
      setSubmitting(false);
    }
  }

  if (contextLoading) return <Alert variant="info" title="Caricamento contesto" message="Verifica del tenant e delle autorizzazioni." />;
  if (tenantId === null) return <Alert variant="warning" title="Tenant richiesto" message="Seleziona un tenant prima di modificare una spesa." />;
  if (!canSubmit || (editing && !canView)) return <Alert variant="warning" title="Editor non disponibile" message="Il contesto corrente non concede le abilità necessarie per questa operazione." />;
  if (!canUseLookups) return <Alert variant="warning" title="Lookup non disponibili" message="Servono le abilità di lettura per vendor, centri di costo, anni e contratti." />;
  if (error && !header.title && loading) return <Alert variant="error" title="Editor non disponibile" message={error.message} />;

  return (
    <form className="space-y-6" onSubmit={(event) => void submit(event)}>
      {error && <Alert variant="error" title="Salvataggio non riuscito" message={`${error.message}${error.correlationId ? ` Correlation ID: ${error.correlationId}` : ""}`} />}
      {validationMessage && <Alert variant="warning" title="Controlla i campi" message={validationMessage} />}
      <ComponentCard title="Dati spesa" desc={editing ? `Spesa #${loadedId ?? expenseId} · versione ${lockVersion ?? "—"}` : "Nuova spesa"}>
        <div className="grid gap-4 md:grid-cols-2">
          <div>
            <Label>Anno di pianificazione</Label>
            <EditorSelect
              id="expense-editor-year"
              value={header.planning_year_id === null ? NONE : String(header.planning_year_id)}
              options={[{ value: NONE, label: "Seleziona un anno" }, ...planningYearOptions]}
              onChange={(value) => setHeader((current) => ({ ...current, planning_year_id: value === NONE ? null : Number(value) }))}
              disabled={disabled}
            />
          </div>
          <div>
            <Label>Centro di costo</Label>
            <EditorSelect
              id="expense-editor-cost-center"
              value={header.cost_center_id === null ? NONE : String(header.cost_center_id)}
              options={[{ value: NONE, label: "Seleziona un centro" }, ...costCenterOptions]}
              onChange={(value) => setHeader((current) => ({ ...current, cost_center_id: value === NONE ? null : Number(value) }))}
              disabled={disabled}
            />
          </div>
          <div>
            <Label>Tipo spesa</Label>
            <EditorSelect
              id="expense-editor-kind"
              value={header.kind}
              options={[{ value: "ordinary", label: "Ordinaria" }, { value: "plafond", label: "Plafond" }]}
              onChange={(kind) => setHeader((current) => ({ ...current, kind }))}
              disabled={disabled}
            />
          </div>
          <div>
            <Label>Contratto (opzionale)</Label>
            <EditorSelect
              id="expense-editor-contract"
              value={header.contract_id === null ? NONE : String(header.contract_id)}
              options={contractOptions}
              onChange={(value) => setHeader((current) => ({ ...current, contract_id: value === NONE ? null : Number(value) }))}
              disabled={disabled}
            />
          </div>
          <div className="md:col-span-2">
            <Label htmlFor="expense-editor-title">Titolo</Label>
            <InputField id="expense-editor-title" value={header.title} onChange={(event) => setHeader((current) => ({ ...current, title: event.target.value }))} disabled={disabled} />
          </div>
          <div className="md:col-span-2">
            <Label>Note</Label>
            <TextArea value={header.notes} onChange={(notes) => setHeader((current) => ({ ...current, notes }))} disabled={disabled} placeholder="Note opzionali" />
          </div>
        </div>
      </ComponentCard>
      <ComponentCard title="Righe" desc="Riordina le righe trascinando il relativo indicatore. Gli importi sono inviati al server senza calcoli locali.">
        <DndProvider backend={HTML5Backend}>
          <ExpenseEditorRows rows={rows} vendors={vendors} onChange={updateRow} onMove={moveRow} onRemove={removeRow} disabled={disabled} />
        </DndProvider>
        <div className="flex justify-end">
          <Button size="sm" variant="outline" onClick={() => setRows((current) => [...current, newExpenseEditorRow(current.length + 1)])} disabled={disabled}>
            Aggiungi riga
          </Button>
        </div>
      </ComponentCard>
      <div className="flex flex-wrap justify-end gap-3">
        <Button variant="outline" onClick={() => navigate(editing ? `/expenses/${expenseId}` : "/expenses")} disabled={submitting}>
          Annulla
        </Button>
        <Button disabled={disabled}>{submitting ? "Salvataggio…" : editing ? "Salva modifiche" : "Crea spesa"}</Button>
      </div>
    </form>
  );
}
