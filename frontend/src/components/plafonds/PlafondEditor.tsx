import { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import { addAllocationAdjustment, createPlafond, previewAllocationAdjustment, type AllocationAdjustmentInput, type PlafondDetail } from "../../api/plafonds";
import { ApiError } from "../../api/client";
import { listExpenseCostCenters, type ExpenseLookupOption } from "../../api/expenses";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";
import { useWorkspaceContextGuard } from "../../hooks/useWorkspaceContextGuard";
import { routes } from "../../navigation/routes";
import Alert from "../ui/alert/Alert";
import Button from "../ui/button/Button";
import ComponentCard from "../common/ComponentCard";
import DatePicker from "../form/date-picker";
import DecimalInput from "../form/input/DecimalInput";
import InputField from "../form/input/InputField";
import TextArea from "../form/input/TextArea";
import Label from "../form/Label";
import Select from "../form/Select";
import { normalizeDecimal } from "../expenses/expenseEditorTypes";
import PlafondImpactPanel from "./PlafondImpactPanel";

const today = new Date().toISOString().slice(0, 10);
const emptyAdjustment = (): AllocationAdjustmentInput => ({ description: "", notes: null, entered_amount: "", amount_includes_vat: false, date: today });

export default function PlafondEditor({ plafond, onSaved }: { plafond?: PlafondDetail; onSaved?: (detail: PlafondDetail) => void }) {
  const navigate = useNavigate();
  const { data: context, hasAbility } = useApplicationContext();
  const { selectedPlanningYearId } = usePlanningYear();
  const creating = plafond === undefined;
  const canSubmit = hasAbility(creating ? "expense.create" : "expense.update");
  const readOnly = plafond?.budget_context.read_only ?? false;
  const [centers, setCenters] = useState<ExpenseLookupOption[]>([]);
  const [title, setTitle] = useState(plafond?.title ?? "");
  const [notes, setNotes] = useState(plafond?.notes ?? "");
  const [costCenterId, setCostCenterId] = useState<number | null>(plafond?.cost_center.id ?? null);
  const [adjustment, setAdjustment] = useState<AllocationAdjustmentInput>(emptyAdjustment);
  const [impact, setImpact] = useState<Awaited<ReturnType<typeof previewAllocationAdjustment>> | null>(null);
  const [error, setError] = useState<ApiError | null>(null);
  const [busy, setBusy] = useState(false);
  const [dirty, setDirty] = useState(false);
  useWorkspaceContextGuard(creating ? "plafond-create" : `plafond-adjustment-${plafond.id}`, dirty);

  useEffect(() => {
    if (creating && hasAbility("cost-center.view")) {
      void listExpenseCostCenters().then(setCenters).catch((cause: unknown) => setError(ApiError.from(cause)));
    }
  }, [creating, hasAbility]);

  const disabled = busy || readOnly;
  const updateAdjustment = (patch: Partial<AllocationAdjustmentInput>) => {
    setDirty(true); setAdjustment((current) => ({ ...current, ...patch })); setImpact(null); setError(null);
  };
  const direct = adjustment.entered_amount?.trim() ?? "";
  const hasCalculatedField = Boolean(adjustment.quantity?.trim() || adjustment.unit_price?.trim());
  const validAmount = Boolean(direct) !== hasCalculatedField && (!hasCalculatedField || Boolean(adjustment.quantity?.trim() && adjustment.unit_price?.trim()));
  const valid = Boolean(adjustment.description.trim() && validAmount && adjustment.date && (!creating || (title.trim() && costCenterId !== null && selectedPlanningYearId !== null)));

  async function preview() {
    if (!plafond || !valid) return;
    setBusy(true); setError(null);
    try { setImpact(await previewAllocationAdjustment(plafond.id, { lock_version: plafond.lock_version, adjustment: requestAdjustment() })); }
    catch (cause: unknown) { setError(ApiError.from(cause)); }
    finally { setBusy(false); }
  }
  async function submit(event: React.FormEvent) {
    event.preventDefault(); if (!valid || !canSubmit || readOnly) return;
    setBusy(true); setError(null);
    try {
      const saved = creating
        ? await createPlafond({ planning_year_id: selectedPlanningYearId as number, cost_center_id: costCenterId as number, title: title.trim(), ...(notes.trim() ? { notes: notes.trim() } : {}), initial_allocation: requestAdjustment() })
        : await addAllocationAdjustment(plafond.id, { lock_version: plafond.lock_version, adjustment: requestAdjustment() });
      setDirty(false); onSaved?.(saved);
      if (creating) navigate(routes.plafond(saved.id)); else { setAdjustment(emptyAdjustment()); setImpact(null); }
    } catch (cause: unknown) { setError(ApiError.from(cause)); }
    finally { setBusy(false); }
  }
  function requestAdjustment(): AllocationAdjustmentInput {
    const enteredAmount = normalizeDecimal(adjustment.entered_amount);
    const quantity = normalizeDecimal(adjustment.quantity);
    const unitPrice = normalizeDecimal(adjustment.unit_price);
    const vatRate = normalizeDecimal(adjustment.vat_rate);
    return {
      description: adjustment.description.trim(),
      ...(adjustment.notes?.trim() ? { notes: adjustment.notes.trim() } : {}),
      ...(enteredAmount ? { entered_amount: enteredAmount } : {}),
      ...(quantity ? { quantity } : {}),
      ...(unitPrice ? { unit_price: unitPrice } : {}),
      amount_includes_vat: adjustment.amount_includes_vat,
      ...(vatRate ? { vat_rate: vatRate } : {}),
      date: adjustment.date,
    };
  }
  if (!canSubmit) return <Alert variant="warning" title="Operazione non disponibile" message="Non disponi dell’autorizzazione necessaria." />;
  if (readOnly) return <Alert variant="info" title="Plafond in sola lettura" message="L’anno economico non è in preparazione; l’Allocazione non può essere modificata." />;
  return <form className="space-y-4" onSubmit={(event) => void submit(event)}>
    {error ? <Alert variant="error" title="Salvataggio non riuscito" message={error.message} /> : null}
    <ComponentCard title={creating ? "Nuovo Plafond" : "Variazione Allocazione"} compact>
      <div className="grid gap-4 md:grid-cols-2">
        {creating ? <>
          <div><Label htmlFor="plafond-title">Titolo</Label><InputField id="plafond-title" value={title} onChange={(event) => { setDirty(true); setTitle(event.target.value); }} disabled={disabled} /></div>
          <div><Label htmlFor="plafond-center">Centro di Costo</Label><Select id="plafond-center" value={costCenterId === null ? "" : String(costCenterId)} options={centers.map((center) => ({ value: String(center.id), label: center.name }))} placeholder="Seleziona un Centro di Costo" allowEmpty onChange={(value) => { setDirty(true); setCostCenterId(value ? Number(value) : null); }} disabled={disabled} /></div>
          <div className="md:col-span-2"><Label htmlFor="plafond-notes">Note</Label><TextArea id="plafond-notes" value={notes} onChange={(value) => { setDirty(true); setNotes(value); }} disabled={disabled} /></div>
        </> : null}
        <div><Label htmlFor="plafond-adjustment-description">Descrizione</Label><InputField id="plafond-adjustment-description" value={adjustment.description} onChange={(event) => updateAdjustment({ description: event.target.value })} disabled={disabled} /></div>
        <div><Label htmlFor="plafond-adjustment-amount">Variazione Allocazione</Label><DecimalInput id="plafond-adjustment-amount" value={adjustment.entered_amount ?? ""} onChange={(entered_amount) => updateAdjustment({ entered_amount, quantity: undefined, unit_price: undefined })} fixedScale={2} maxScale={2} suffix="€" disabled={disabled} hint="Importo diretto, oppure usa quantità e prezzo unitario." /></div>
        <div><Label htmlFor="plafond-adjustment-quantity">Quantità (alternativa)</Label><DecimalInput id="plafond-adjustment-quantity" value={adjustment.quantity ?? ""} onChange={(quantity) => updateAdjustment({ quantity: quantity || undefined, entered_amount: undefined })} maxScale={2} disabled={disabled} /></div>
        <div><Label htmlFor="plafond-adjustment-unit-price">Prezzo unitario (alternativa)</Label><DecimalInput id="plafond-adjustment-unit-price" value={adjustment.unit_price ?? ""} onChange={(unit_price) => updateAdjustment({ unit_price: unit_price || undefined, entered_amount: undefined })} maxScale={2} suffix="€" disabled={disabled} /></div>
        <div><Label htmlFor="plafond-adjustment-date">Data</Label><DatePicker id="plafond-adjustment-date" label="Data variazione" hideLabel defaultDate={adjustment.date} onChange={(_, date) => updateAdjustment({ date })} disabled={disabled} /></div>
        <div><Label htmlFor="plafond-adjustment-vat">IVA</Label><DecimalInput id="plafond-adjustment-vat" value={adjustment.vat_rate ?? context?.tenant?.default_vat_rate ?? ""} onChange={(vat_rate) => updateAdjustment({ vat_rate: vat_rate || undefined })} fixedScale={2} maxScale={2} suffix="%" disabled={disabled} hint="Predefinito del Tenant; modificalo per applicare un override." /></div>
        <div className="md:col-span-2"><Label htmlFor="plafond-adjustment-notes">Note</Label><TextArea id="plafond-adjustment-notes" value={adjustment.notes ?? ""} onChange={(value) => updateAdjustment({ notes: value || null })} disabled={disabled} /></div>
      </div>
    </ComponentCard>
    {!valid && adjustment.description ? <Alert variant="warning" title="Importo richiesto" message="Inserisci l’importo diretto oppure la coppia completa quantità e prezzo unitario." /> : null}
    {impact ? <PlafondImpactPanel impact={impact} canOpenRows={hasAbility("expense.view")} /> : null}
    <div className="flex flex-wrap justify-end gap-2">
      {!creating ? <Button type="button" variant="outline" disabled={disabled || !valid} onClick={() => void preview()}>{busy ? "Verifica…" : "Verifica impatto"}</Button> : null}
      <Button disabled={disabled || !valid}>{busy ? "Salvataggio…" : creating ? "Crea Plafond" : "Aggiungi variazione"}</Button>
    </div>
  </form>;
}
