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
  listEligiblePlafondExpenses,
  listExpenseVendors,
  updateExpense,
  type ExpenseContractOption,
  type ExpenseDetail,
  type ExpenseLookupOption,
  type PlafondExpenseOption,
  type ExpenseUpdate,
  type ExpenseWrite,
} from "../../api/expenses";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";
import { useWorkspaceContextGuard } from "../../hooks/useWorkspaceContextGuard";
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
import { formatEditableDecimal } from "../../presentation/formatters";
import { routes } from "../../navigation/routes";
import { listProjectOptions, type ProjectLookupOption } from "../../api/projects";
import { useInvalidFieldFocus, type InvalidFieldFocusRequest } from "../../hooks/useInvalidFieldFocus";

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
  project_id: number | null;
  contract_id: number | null;
}

const expenseFieldIds: Record<string, string> = {
  planning_year_id: "expense-editor-year",
  cost_center_id: "expense-editor-cost-center",
  kind: "expense-editor-kind",
  title: "expense-editor-title",
  notes: "expense-editor-notes",
  project_id: "expense-editor-project",
  contract_id: "expense-editor-contract",
  rows: "expense-editor-rows",
};

const expenseRowFieldIdSuffixes: Record<string, string> = {
  type: "type",
  vendor_id: "vendor",
  description: "description",
  quantity: "quantity",
  unit_price: "unit-price",
  entered_amount: "entered-amount",
  amount_includes_vat: "vat-included",
  vat_rate: "vat-rate",
  spend_date: "spend-date-display",
  external_reference: "external",
  funded_plafond_expense_id: "funded-plafond",
  is_current_planning: "current",
};

function validationSuggestion(field: string): string {
  const segments = field.split(".");
  const name = segments[segments.length - 1] ?? field;
  const suggestions: Record<string, string> = {
    planning_year_id: "Seleziona un anno disponibile.",
    cost_center_id: "Seleziona un Centro di Costo disponibile.",
    kind: "Seleziona un tipo di Spesa valido.",
    title: "Inserisci un titolo valido.",
    notes: "Controlla il testo inserito.",
    project_id: "Seleziona un Progetto disponibile.",
    contract_id: "Seleziona un Contratto disponibile.",
    type: "Seleziona un tipo di riga valido.",
    vendor_id: "Seleziona un Fornitore per questa riga.",
    description: "Inserisci una descrizione valida.",
    quantity: "Inserisci una quantità valida con massimo 2 decimali.",
    unit_price: "Inserisci un prezzo valido con massimo 2 decimali.",
    entered_amount: "Inserisci un importo valido con massimo 2 decimali.",
    vat_rate: "Inserisci un'IVA valida con massimo 2 decimali.",
    spend_date: "Inserisci una data reale valida.",
    amount_includes_vat: "Controlla questa opzione.",
    external_reference: "Controlla il riferimento inserito.",
    funded_plafond_expense_id: "Seleziona un Plafond valido oppure rimuovi la copertura.",
    is_current_planning: "Seleziona come corrente soltanto una Stima o un Preventivo.",
  };
  if (suggestions[name]) return suggestions[name];
  if (field === "rows") return "Controlla le righe inserite.";
  if (field.startsWith("rows.")) return "Controlla i dati di questa riga.";

  return "Controlla il valore inserito.";
}

function apiValidationErrors(error: ApiError): Record<string, string> {
  if (error.handledStatus !== 422) return {};

  return Object.fromEntries(Object.keys(error.fields).map((field) => [field, validationSuggestion(field)]));
}

function validationFieldId(field: string, rows: ExpenseEditorRow[]): string {
  const rowField = /^rows\.(\d+)(?:\.([a-z_]+))?$/.exec(field);
  if (rowField) {
    const row = rows[Number(rowField[1])];
    if (!row) return "expense-editor-rows";
    const suffix = rowField[2] ? expenseRowFieldIdSuffixes[rowField[2]] : undefined;
    return suffix ? `${row.editorKey}-${suffix}` : `${row.editorKey}-row`;
  }

  return expenseFieldIds[field] ?? "expense-editor-form";
}

function expenseErrorMessage(error: ApiError): string {
  const correlation = error.correlationId
    ? ` Riferimento tecnico: ${error.correlationId}`
    : "";
  const guidance = error.handledStatus === 422 ? " Correggi il campo evidenziato." : "";

  return `${error.message}${guidance}${correlation}`;
}

function initialHeader(selectedPlanningYearId: number | null): EditorHeader {
  return {
    planning_year_id: selectedPlanningYearId,
    cost_center_id: null,
    kind: "ordinary",
    title: "",
    notes: "",
    project_id: null,
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
    quantity: row.quantity === null ? undefined : formatEditableDecimal(row.quantity, { trimTrailingZeros: true }),
    unit_price: row.unit_price === null ? undefined : formatEditableDecimal(row.unit_price, { trimTrailingZeros: true }),
    entered_amount: row.entered_amount ? formatEditableDecimal(row.entered_amount, { fixedScale: 2 }) : undefined,
    notes: row.notes ?? undefined,
    amount_includes_vat: row.amount_includes_vat,
    vat_rate: row.vat_rate === null ? undefined : formatEditableDecimal(row.vat_rate, { fixedScale: 2 }),
    spend_date: row.spend_date ?? undefined,
    is_current_planning: row.is_current_planning,
    external_reference: row.external_reference ?? undefined,
    funded_plafond_expense_id: row.funded_plafond?.id ?? null,
    lock_version: row.lock_version,
  }));
}

function EditorSelect({
  id,
  value,
  options,
  onChange,
  disabled,
  error,
  hint,
}: {
  id: string;
  value: string;
  options: Array<{ value: string; label: string }>;
  onChange: (value: string) => void;
  disabled: boolean;
  error?: boolean;
  hint?: string;
}) {
  return (
    <Select
      id={id}
      options={options}
      value={value}
      onChange={(next) => {
        if (!disabled) onChange(next);
      }}
      className={disabled ? "pointer-events-none opacity-60" : ""}
      error={error}
      hint={hint}
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
  const { selectedPlanningYearId, selectPlanningYear } = usePlanningYear();
  const editing = expenseId !== undefined;
  const tenantId = applicationContext?.tenant?.id ?? null;
  const tenantDefaultVatRate = applicationContext?.tenant?.default_vat_rate;
  const canSubmit = hasAbility(editing ? "expense.update" : "expense.create");
  const canView = hasAbility("expense.view");
  const canUseLookups = ["cost-center.view", "planning-year.view"].every(hasAbility);
  const canViewVendors = hasAbility("vendor.view");
  const canViewContracts = hasAbility("contract.view");
  const canViewProjects = hasAbility("project.view");
  const [header, setHeader] = useState<EditorHeader>(() => initialHeader(selectedPlanningYearId));
  const [rows, setRows] = useState<ExpenseEditorRow[]>(() => [newExpenseEditorRow(1)]);
  const [deletedRows, setDeletedRows] = useState<Array<{ id: number; lock_version: number }>>([]);
  const [vendors, setVendors] = useState<ExpenseLookupOption[]>([]);
  const [costCenters, setCostCenters] = useState<ExpenseLookupOption[]>([]);
  const [contracts, setContracts] = useState<ExpenseContractOption[]>([]);
  const [projects, setProjects] = useState<ProjectLookupOption[]>([]);
  const [plafonds, setPlafonds] = useState<PlafondExpenseOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [validationMessage, setValidationMessage] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});
  const [focusRequest, setFocusRequest] = useState<InvalidFieldFocusRequest | null>(null);
  const [lockVersion, setLockVersion] = useState<number | null>(null);
  const [dirty, setDirty] = useState(false);

  useInvalidFieldFocus(focusRequest);

  useWorkspaceContextGuard("expense-editor", dirty);

  useEffect(() => {
    if (selectedPlanningYearId !== null && !editing) {
      setHeader((current) => ({ ...current, planning_year_id: selectedPlanningYearId }));
    }
  }, [editing, selectedPlanningYearId]);

  useEffect(() => {
    if (
      contextLoading ||
      tenantId === null ||
      !canSubmit ||
      !canUseLookups ||
      selectedPlanningYearId === null ||
      (editing && (!canView || expenseId === undefined))
    ) {
      return;
    }

    let active = true;
    setLoading(true);
    setError(null);
    const detailRequest = editing ? getExpense(expenseId as number, selectedPlanningYearId) : Promise.resolve(null);

    void Promise.all([
      canViewVendors ? listExpenseVendors() : Promise.resolve([]),
      listExpenseCostCenters(),
      canViewContracts ? listExpenseContracts() : Promise.resolve([]),
      canViewProjects ? listProjectOptions() : Promise.resolve([]),
      detailRequest,
    ])
      .then(([vendorOptions, costCenterOptions, contractOptions, projectOptions, detail]) => {
        if (!active) return;
        setVendors(vendorOptions);
        setCostCenters(costCenterOptions);
        setContracts(contractOptions);
        setProjects(projectOptions);
        if (detail) {
          setHeader({
            planning_year_id: detail.planning_year_id,
            cost_center_id: detail.cost_center_id,
            kind: detail.kind,
            title: detail.title,
            notes: detail.notes ?? "",
            project_id: detail.project_id,
            contract_id: detail.contract_id,
          });
          setRows(toEditorRows(detail));
          setLockVersion(detail.lock_version);
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

    void listEligiblePlafondExpenses(selectedPlanningYearId)
      .then((plafondOptions) => { if (active) setPlafonds(plafondOptions); })
      .catch(() => { if (active) setPlafonds([]); });

    return () => {
      active = false;
    };
  }, [canSubmit, canUseLookups, canView, canViewContracts, canViewProjects, canViewVendors, contextLoading, editing, expenseId, selectedPlanningYearId, tenantId]);

  const disabled = loading || submitting || !canSubmit;
  const costCenterOptions = useMemo(
    () => byIdOption(costCenters, header.cost_center_id, "Centro di Costo corrente").map((option) => ({ value: String(option.id), label: option.name })),
    [costCenters, header.cost_center_id],
  );
  const contractOptions = useMemo(
    () => [
      { value: NONE, label: "Nessun contratto" },
      ...(header.contract_id !== null && !contracts.some((contract) => contract.id === header.contract_id)
        ? [{ value: String(header.contract_id), label: "Contratto corrente" }]
        : []),
      ...contracts
        .filter((contract) => contract.active !== false || contract.id === header.contract_id)
        .map((contract) => ({ value: String(contract.id), label: contract.title })),
    ],
    [contracts, header.contract_id],
  );
  const projectOptions = useMemo(
    () => [
      { value: NONE, label: "Nessun progetto" },
      ...(header.project_id !== null && !projects.some((project) => project.id === header.project_id)
        ? [{ value: String(header.project_id), label: "Progetto corrente" }]
        : []),
      ...projects.map((project) => ({ value: String(project.id), label: project.title })),
    ],
    [header.project_id, projects],
  );

  function showValidationErrors(next: Record<string, string>) {
    setValidationErrors(next);
    const firstField = Object.keys(next)[0];
    if (firstField) {
      setFocusRequest({ id: validationFieldId(firstField, rows) });
    }
  }

  function clearValidationFields(fields: string[]) {
    setError(null);
    setValidationMessage(null);
    setValidationErrors((current) => {
      const next = { ...current };
      fields.forEach((field) => delete next[field]);
      return next;
    });
  }

  function clearAllValidationErrors() {
    setError(null);
    setValidationMessage(null);
    setValidationErrors({});
  }

  function updateHeader(patch: Partial<EditorHeader>) {
    setDirty(true);
    setHeader((current) => ({ ...current, ...patch }));
    clearValidationFields(Object.keys(patch));
  }

  const fieldError = (field: string) => validationErrors[field];

  function updateRow(index: number, patch: Partial<ExpenseEditorRow>) {
    setDirty(true);
    setRows((current) => current.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)));
    clearValidationFields(Object.keys(patch).map((field) => `rows.${index}.${field}`));
  }

  function moveRow(from: number, to: number) {
    setDirty(true);
    clearAllValidationErrors();
    setRows((current) => {
      const next = [...current];
      const [moved] = next.splice(from, 1);
      if (!moved) return current;
      next.splice(to, 0, moved);
      return next.map((row, index) => ({ ...row, position: index + 1 }));
    });
  }

  function removeRow(index: number) {
    setDirty(true);
    clearAllValidationErrors();
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
    setError(null);
    setValidationMessage(null);
    setValidationErrors({});
    const planningYearId = header.planning_year_id;
    const costCenterId = header.cost_center_id;
    const headerErrors: Record<string, string> = {};
    if (planningYearId === null) headerErrors.planning_year_id = "Seleziona un anno valido.";
    if (costCenterId === null) headerErrors.cost_center_id = "Seleziona un Centro di Costo.";
    if (!header.title.trim()) headerErrors.title = "Inserisci un titolo.";
    if (planningYearId === null || costCenterId === null || !header.title.trim()) {
      setValidationMessage("Correggi il campo evidenziato.");
      showValidationErrors(headerErrors);
      return;
    }
    if (rows.length === 0) {
      setValidationMessage("Inserisci almeno una riga.");
      showValidationErrors({ rows: "Aggiungi almeno una riga." });
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
    const rowErrors: Record<string, string> = {};
    preparedRows.forEach(({ row, entered, quantity, unitPrice, vatRate }, index) => {
      if (!row.description.trim()) rowErrors[`rows.${index}.description`] = "Inserisci una descrizione.";
      const directMode = entered !== "";
      const calculatedMode = quantity !== "" || unitPrice !== "";
      if (directMode === calculatedMode) rowErrors[`rows.${index}.entered_amount`] = "Usa l'importo oppure la coppia quantità e prezzo unitario.";
      if (quantity !== "" && !isDecimalText(quantity)) rowErrors[`rows.${index}.quantity`] = "Inserisci una quantità valida con massimo 2 decimali.";
      if (unitPrice !== "" && !isDecimalText(unitPrice)) rowErrors[`rows.${index}.unit_price`] = "Inserisci un prezzo valido con massimo 2 decimali.";
      if (calculatedMode && (quantity === "" || unitPrice === "")) rowErrors[`rows.${index}.quantity`] = "Quantità e prezzo unitario devono essere entrambi presenti.";
      if (vatRate !== "" && !/^\d+(?:\.\d{1,2})?$/.test(vatRate)) rowErrors[`rows.${index}.vat_rate`] = "Inserisci un'IVA valida con massimo 2 decimali.";
    });
    if (Object.keys(rowErrors).length > 0) {
      setValidationMessage("Correggi il campo evidenziato.");
      showValidationErrors(rowErrors);
      return;
    }

    const input: ExpenseWrite = {
      planning_year_id: planningYearId,
      cost_center_id: costCenterId,
      kind: header.kind,
      title: header.title.trim(),
      ...(header.notes.trim() ? { notes: header.notes.trim() } : {}),
      ...(header.project_id !== null ? { project_id: header.project_id } : {}),
      ...(header.contract_id !== null ? { contract_id: header.contract_id } : {}),
      rows: preparedRows.map(({ row, position, entered, quantity, unitPrice, vatRate }) => ({
        ...(row.id !== undefined ? { id: row.id } : {}),
        position,
        ...(row.vendor_id !== undefined ? { vendor_id: row.vendor_id } : {}),
        type: row.type,
        description: row.description.trim(),
        ...(quantity ? { quantity } : {}),
        ...(unitPrice ? { unit_price: unitPrice } : {}),
        ...(entered ? { entered_amount: entered } : {}),
        ...(row.notes?.trim() ? { notes: row.notes.trim() } : {}),
        amount_includes_vat: row.amount_includes_vat,
        ...(vatRate ? { vat_rate: vatRate } : {}),
        ...(row.spend_date ? { spend_date: row.spend_date } : {}),
        ...(row.is_current_planning ? { is_current_planning: true } : {}),
        ...(row.funded_plafond_expense_id !== undefined ? { funded_plafond_expense_id: row.funded_plafond_expense_id } : {}),
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
      if (saved.planning_year_id !== selectedPlanningYearId) selectPlanningYear(saved.planning_year_id);
      setDirty(false);
      navigate(routes.spesa(saved.id));
    } catch (requestError: unknown) {
      const apiError = ApiError.from(requestError);
      setError(apiError);
      const nextValidationErrors = apiValidationErrors(apiError);
      if (Object.keys(nextValidationErrors).length > 0) {
        showValidationErrors(nextValidationErrors);
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (contextLoading) return <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant e delle autorizzazioni in corso." />;
  if (tenantId === null) return <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant prima di modificare una spesa." />;
  if (!canSubmit || (editing && !canView)) return <Alert variant="warning" title="Editor non disponibile" message="Non disponi dell'autorizzazione necessaria per questa operazione." />;
  if (!canUseLookups) return <Alert variant="warning" title="Dati di supporto non disponibili" message="Non puoi selezionare anno e centro di costo nel contesto corrente." />;
  if (error && !header.title && loading) return <Alert variant="error" title="Editor non disponibile" message={error.message} />;

  return (
    <form id="expense-editor-form" tabIndex={-1} className="space-y-6 focus:outline-hidden" onSubmit={(event) => void submit(event)}>
      {error && <Alert variant="error" title={error.handledStatus === 422 ? "Controlla i dati" : "Salvataggio non riuscito"} message={expenseErrorMessage(error)} />}
      {validationMessage && <Alert variant="warning" title="Controlla i campi" message={validationMessage} />}
      <ComponentCard title="Dati generali" compact>
        <div className="grid gap-6 xl:grid-cols-12 xl:items-start">
          <section className="grid gap-4 xl:col-span-5">
            <div>
              <Label htmlFor="expense-editor-title">Titolo</Label>
              <InputField id="expense-editor-title" value={header.title} onChange={(event) => updateHeader({ title: event.target.value })} disabled={disabled} error={Boolean(fieldError("title"))} hint={fieldError("title")} />
            </div>
            <div>
              <Label htmlFor="expense-editor-notes">Note</Label>
              <TextArea id="expense-editor-notes" value={header.notes} onChange={(notes) => updateHeader({ notes })} disabled={disabled} placeholder="Note opzionali" error={Boolean(fieldError("notes"))} hint={fieldError("notes")} />
            </div>
          </section>

          <section className="xl:col-span-7 xl:border-l xl:border-gray-100 xl:pl-6 dark:xl:border-gray-800">
            <h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">
              Classificazione
            </h4>
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <Label htmlFor="expense-editor-cost-center">Centro di Costo</Label>
                <EditorSelect
                  id="expense-editor-cost-center"
                  value={header.cost_center_id === null ? NONE : String(header.cost_center_id)}
                  options={[{ value: NONE, label: "Seleziona un Centro di Costo" }, ...costCenterOptions]}
                  onChange={(value) => updateHeader({ cost_center_id: value === NONE ? null : Number(value) })}
                  disabled={disabled}
                  error={Boolean(fieldError("cost_center_id"))}
                  hint={fieldError("cost_center_id")}
                />
              </div>
              <div>
                <Label htmlFor="expense-editor-kind">Tipo spesa</Label>
                <EditorSelect
                  id="expense-editor-kind"
                  value={header.kind}
                  options={[{ value: "ordinary", label: "Ordinaria" }]}
                  onChange={() => undefined}
                  disabled
                  error={Boolean(fieldError("kind"))}
                  hint="I Plafond sono creati dal Registro Plafond dedicato."
                />
              </div>
              <div>
                <Label htmlFor="expense-editor-contract">Contratto (opzionale)</Label>
                <EditorSelect
                  id="expense-editor-contract"
                  value={header.contract_id === null ? NONE : String(header.contract_id)}
                  options={contractOptions}
                  onChange={(value) => updateHeader({ contract_id: value === NONE ? null : Number(value) })}
                  disabled={disabled}
                  error={Boolean(fieldError("contract_id"))}
                  hint={fieldError("contract_id")}
                />
              </div>
              {canViewProjects ? <div>
                <Label htmlFor="expense-editor-project">Progetto (opzionale)</Label>
                <EditorSelect
                  id="expense-editor-project"
                  value={header.project_id === null ? NONE : String(header.project_id)}
                  options={projectOptions}
                  onChange={(value) => updateHeader({ project_id: value === NONE ? null : Number(value) })}
                  disabled={disabled}
                  error={Boolean(fieldError("project_id"))}
                  hint={fieldError("project_id")}
                />
              </div> : null}
            </div>
          </section>
        </div>
      </ComponentCard>
      <ComponentCard title="Righe della Spesa" desc="Campi frequenti inline; usa l’icona a fine riga per le opzioni secondarie." compact>
        <div id="expense-editor-rows" tabIndex={-1} className="focus:outline-hidden focus:ring-3 focus:ring-error-500/10">
          {fieldError("rows") ? <p className="mb-3 text-xs text-error-500">{fieldError("rows")}</p> : null}
          <DndProvider backend={HTML5Backend}>
            <ExpenseEditorRows rows={rows} defaultVatRate={tenantDefaultVatRate} vendors={vendors} plafonds={plafonds} onChange={updateRow} onMove={moveRow} onRemove={removeRow} disabled={disabled} validationErrors={validationErrors} />
          </DndProvider>
        </div>
        <div className="flex justify-end">
          <Button type="button" size="sm" variant="outline" onClick={() => { clearAllValidationErrors(); setDirty(true); setRows((current) => [...current, newExpenseEditorRow(current.length + 1)]); }} disabled={disabled}>
            + Aggiungi Riga
          </Button>
        </div>
      </ComponentCard>
      <div className="flex flex-wrap justify-end gap-3">
        <Button type="button" variant="outline" onClick={() => navigate(editing ? routes.spesa(expenseId as number) : routes.spese)} disabled={submitting}>
          Annulla
        </Button>
        <Button disabled={disabled}>{submitting ? "Salvataggio…" : editing ? "Salva modifiche" : "Crea spesa"}</Button>
      </div>
    </form>
  );
}
